<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_face_enrollments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('recapture_count')->default(0)->after('model_version');
        });

        Schema::table('login_activities', function (Blueprint $table): void {
            $table->index('created_at', 'login_activities_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('login_activities', function (Blueprint $table): void {
            $table->dropIndex('login_activities_created_at_index');
        });

        Schema::table('teacher_face_enrollments', function (Blueprint $table): void {
            $table->dropColumn('recapture_count');
        });
    }
};
