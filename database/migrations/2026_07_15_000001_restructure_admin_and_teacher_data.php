<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['guru', 'kepala_sekolah', 'bendahara'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $user): void {
                if (! DB::table('teachers')->where('user_id', $user->id)->exists()) {
                    DB::table('teachers')->insert([
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->name ?: $user->nip,
                        'email' => $user->email,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        if (Schema::hasColumn('teachers', 'attendance_session_id')) {
            DB::table('teachers')
                ->whereNotNull('attendance_session_id')
                ->orderBy('id')
                ->lazy()
                ->each(function (object $teacher): void {
                    DB::table('attendance_session_teacher')->updateOrInsert(
                        [
                            'teacher_id' => $teacher->id,
                            'attendance_session_id' => $teacher->attendance_session_id,
                        ],
                        ['updated_at' => now(), 'created_at' => now()]
                    );
                });
        }

        Schema::table('teachers', function (Blueprint $table): void {
            if (Schema::hasColumn('teachers', 'attendance_session_id')) {
                $table->dropConstrainedForeignId('attendance_session_id');
            }

            $unusedColumns = collect(['email', 'foto'])
                ->filter(fn (string $column): bool => Schema::hasColumn('teachers', $column))
                ->all();

            if ($unusedColumns !== []) {
                $table->dropColumn($unusedColumns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            if (! Schema::hasColumn('teachers', 'attendance_session_id')) {
                $table->foreignId('attendance_session_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('attendance_sessions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('teachers', 'email')) {
                $table->string('email', 100)->nullable();
            }

            if (! Schema::hasColumn('teachers', 'foto')) {
                $table->string('foto')->nullable();
            }
        });
    }
};
