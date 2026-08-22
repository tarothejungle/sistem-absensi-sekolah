<?php

namespace App\Console\Commands;

use App\Casts\EncryptedFaceDescriptor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class EncryptFaceDescriptors extends Command
{
    protected $signature = 'biometrics:encrypt-templates {--dry-run : Validasi tanpa memperbarui database}';

    protected $description = 'Encrypt legacy plaintext face templates in place using APP_KEY';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $encrypted = 0;
        $alreadyEncrypted = 0;
        $invalid = [];

        DB::table('teacher_face_enrollments')
            ->select(['id', 'face_descriptor'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$encrypted, &$alreadyEncrypted, &$invalid): void {
                foreach ($rows as $row) {
                    if (EncryptedFaceDescriptor::isEncrypted($row->face_descriptor)) {
                        $alreadyEncrypted++;

                        continue;
                    }

                    try {
                        $encoded = EncryptedFaceDescriptor::encode(
                            EncryptedFaceDescriptor::decode($row->face_descriptor)
                        );

                        if (! $dryRun) {
                            DB::table('teacher_face_enrollments')
                                ->where('id', $row->id)
                                ->update(['face_descriptor' => $encoded]);
                        }

                        $encrypted++;
                    } catch (Throwable $exception) {
                        $invalid[] = ['id' => $row->id, 'message' => $exception->getMessage()];
                    }
                }
            });

        $this->info(($dryRun ? 'Dry run: ' : '')."{$encrypted} template siap/berhasil dienkripsi; {$alreadyEncrypted} sudah terenkripsi.");

        foreach ($invalid as $failure) {
            $this->error("Enrollment {$failure['id']}: {$failure['message']}");
        }

        return $invalid === [] ? self::SUCCESS : self::FAILURE;
    }
}
