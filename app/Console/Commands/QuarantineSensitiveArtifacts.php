<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QuarantineSensitiveArtifacts extends Command
{
    protected $signature = 'security:quarantine-artifacts {--dry-run : Tampilkan target tanpa memindahkan file}';

    protected $description = 'Move root SQL dumps and archives into private recoverable quarantine';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $files = collect(array_merge(
            glob(base_path('*.sql')) ?: [],
            glob(base_path('*.zip')) ?: []
        ))->unique()->values();

        foreach ($files as $source) {
            $destination = 'security-quarantine/'.now()->format('Y-m-d').'/root-artifacts/'.basename($source);
            $this->line(($dryRun ? '[dry-run] ' : '')."{$source} -> storage/app/{$destination}");

            if ($dryRun) {
                continue;
            }

            $contents = file_get_contents($source);
            if ($contents === false || ! Storage::disk('local')->put($destination, $contents)) {
                throw new RuntimeException("Gagal mengarantina {$source}.");
            }

            $target = Storage::disk('local')->path($destination);
            if (! hash_equals(hash_file('sha256', $source), hash_file('sha256', $target))) {
                Storage::disk('local')->delete($destination);
                throw new RuntimeException("Verifikasi hash gagal untuk {$source}.");
            }

            unlink($source);
        }

        $this->info($files->count().' artefak siap/berhasil dikarantina.');

        return self::SUCCESS;
    }
}
