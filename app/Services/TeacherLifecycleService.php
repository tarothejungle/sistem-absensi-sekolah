<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TeacherLifecycleService
{
    /**
     * @return array{action: 'archived'|'deleted', teacher_id: int|null, user_id: int}
     */
    public function archiveOrDeleteUser(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $teacher = Teacher::query()->where('user_id', $lockedUser->id)->lockForUpdate()->first();

            if ($teacher && $this->hasOperationalHistory($teacher)) {
                $lockedUser->status = 'nonaktif';
                $lockedUser->save();

                return [
                    'action' => 'archived',
                    'teacher_id' => $teacher->id,
                    'user_id' => $lockedUser->id,
                ];
            }

            $teacherId = $teacher?->id;
            $teacher?->salary()->delete();
            $teacher?->delete();
            $lockedUser->delete();

            return [
                'action' => 'deleted',
                'teacher_id' => $teacherId,
                'user_id' => $user->id,
            ];
        });
    }

    /**
     * @return array{action: 'archived'|'deleted', teacher_id: int|null, user_id: int}
     */
    public function archiveOrDeleteTeacher(Teacher $teacher): array
    {
        $teacher->loadMissing('user');

        if (! $teacher->user || ! in_array($teacher->user->role, Teacher::DATA_GURU_ROLES, true)) {
            throw new RuntimeException('Akun bukan bagian dari data guru/PTK.');
        }

        return $this->archiveOrDeleteUser($teacher->user);
    }

    public function hasOperationalHistory(Teacher $teacher): bool
    {
        return $teacher->attendances()->exists()
            || $teacher->leaves()->exists()
            || $teacher->infalLeaves()->exists()
            || $teacher->payrollItems()->exists();
    }
}
