<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneAttendancePhotos extends Command
{
    protected $signature = 'attendance:prune-photos {--days= : Override the configured retention period}';

    protected $description = 'Delete expired private attendance photos without deleting attendance records';

    public function handle(): int
    {
        $days = $this->option('days')
            ?? config('attendance.photo_retention_days', 30);

        if (filter_var($days, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('Masa retensi harus berupa bilangan bulat minimal 1 hari.');

            return self::FAILURE;
        }

        $cutoff = today()->subDays((int) $days)->toDateString();
        $deleted = 0;

        Attendance::query()
            ->whereDate('tanggal', '<', $cutoff)
            ->where(function ($query): void {
                $query->whereNotNull('check_in_face_photo')
                    ->orWhereNotNull('check_out_face_photo');
            })
            ->select(['id', 'check_in_face_photo', 'check_out_face_photo'])
            ->chunkById(100, function ($attendances) use (&$deleted): void {
                foreach ($attendances as $attendance) {
                    $updates = [];

                    foreach (['check_in_face_photo', 'check_out_face_photo'] as $column) {
                        $path = $attendance->{$column};

                        if (! is_string($path) || ! str_starts_with($path, 'attendance_faces/') || str_contains($path, '..')) {
                            if ($path !== null) {
                                Log::warning('Skipped unsafe attendance photo path.', [
                                    'attendance_id' => $attendance->id,
                                    'column' => $column,
                                ]);
                            }

                            continue;
                        }

                        $disk = Storage::disk('local');
                        if ($disk->exists($path) && ! $disk->delete($path)) {
                            Log::warning('Unable to delete expired attendance photo.', [
                                'attendance_id' => $attendance->id,
                                'column' => $column,
                            ]);

                            continue;
                        }

                        $updates[$column] = null;
                        $deleted++;
                    }

                    if ($updates !== []) {
                        $attendance->update($updates);
                    }
                }
            });

        $this->info("{$deleted} foto absensi kedaluwarsa dibersihkan.");

        return self::SUCCESS;
    }
}
