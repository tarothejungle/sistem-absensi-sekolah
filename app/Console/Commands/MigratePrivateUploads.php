<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MigratePrivateUploads extends Command
{
    protected $signature = 'uploads:migrate-private {--dry-run : Tampilkan perubahan tanpa memindahkan file}';

    protected $description = 'Move legacy profile photos and leave attachments to private local storage';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;
        $missing = 0;

        if (Schema::hasTable('users')) {
            DB::table('users')->whereNotNull('profile_photo')->orderBy('id')->chunkById(100, function ($users) use ($dryRun, &$migrated, &$missing): void {
                foreach ($users as $user) {
                    $current = $this->normalize($user->profile_photo);
                    if ($current && str_starts_with($current, 'profile_photos/') && Storage::disk('local')->exists($current)) {
                        continue;
                    }

                    $source = $this->firstExisting($this->profileCandidates($current));
                    if (! $source) {
                        $missing++;
                        $this->warn("Foto profil user {$user->id} tidak ditemukan: {$current}");

                        continue;
                    }

                    $destination = 'profile_photos/'.$user->id.'/'.basename($source);
                    $this->migrateFile($source, $destination, 'profile-photos', $dryRun);

                    if (! $dryRun) {
                        DB::table('users')->where('id', $user->id)->update(['profile_photo' => $destination]);
                    }

                    $migrated++;
                }
            });
        }

        if (Schema::hasTable('leave_requests')) {
            DB::table('leave_requests')->whereNotNull('lampiran')->orderBy('id')->chunkById(100, function ($leaves) use ($dryRun, &$migrated, &$missing): void {
                foreach ($leaves as $leave) {
                    $current = $this->normalize($leave->lampiran);
                    if ($current && str_starts_with($current, 'leave_attachments/') && Storage::disk('local')->exists($current)) {
                        continue;
                    }

                    $source = $this->firstExisting([
                        $current ? base_path($current) : null,
                        $current ? public_path($current) : null,
                        $current ? storage_path('app/public/'.$current) : null,
                    ]);

                    if (! $source) {
                        $missing++;
                        $this->warn("Lampiran izin {$leave->id} tidak ditemukan: {$current}");

                        continue;
                    }

                    $destination = 'leave_attachments/'.$leave->id.'/'.basename($source);
                    $this->migrateFile($source, $destination, 'leave-attachments/'.$leave->id, $dryRun);

                    if (! $dryRun) {
                        DB::table('leave_requests')->where('id', $leave->id)->update(['lampiran' => $destination]);
                    }

                    $migrated++;
                }
            });
        }

        $this->info(($dryRun ? 'Dry run: ' : '')."{$migrated} file siap/berhasil dimigrasikan; {$missing} file tidak ditemukan.");

        return $missing === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function migrateFile(string $source, string $destination, string $group, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("{$source} -> storage/app/{$destination}");

            return;
        }

        $contents = file_get_contents($source);
        if ($contents === false || ! Storage::disk('local')->put($destination, $contents)) {
            throw new RuntimeException("Gagal menyalin {$source} ke private storage.");
        }

        $privatePath = Storage::disk('local')->path($destination);
        if (! hash_equals(hash_file('sha256', $source), hash_file('sha256', $privatePath))) {
            Storage::disk('local')->delete($destination);
            throw new RuntimeException("Verifikasi hash gagal untuk {$source}.");
        }

        $quarantine = 'security-quarantine/'.now()->format('Y-m-d').'/'.$group.'/'.basename($source);
        Storage::disk('local')->put($quarantine, $contents);

        if (! hash_equals(hash_file('sha256', $source), hash_file('sha256', Storage::disk('local')->path($quarantine)))) {
            throw new RuntimeException("Verifikasi quarantine gagal untuk {$source}.");
        }

        unlink($source);
    }

    private function profileCandidates(?string $path): array
    {
        $basename = $path ? basename($path) : null;

        return [
            $path ? public_path($path) : null,
            $basename ? public_path('profile_photo/'.$basename) : null,
            $basename ? public_path('profile_photos/'.$basename) : null,
            $path ? storage_path('app/public/'.$path) : null,
        ];
    }

    private function firstExisting(array $paths): ?string
    {
        foreach (array_filter($paths) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalize(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        return str_contains($normalized, '..') ? null : $normalized;
    }
}
