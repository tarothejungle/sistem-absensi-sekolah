<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_face_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('face_descriptor');
            $table->string('model_version', 50)->default('face-api.js-0.22.2');
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enrolled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_face_enrollments');
    }
};
