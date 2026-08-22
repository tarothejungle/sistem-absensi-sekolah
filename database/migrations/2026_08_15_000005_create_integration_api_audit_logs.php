<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integration_api_audit_logs')) {
            return;
        }

        Schema::create('integration_api_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('integration', 50);
            $table->uuid('request_id');
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('ip_address', 45)->nullable();
            $table->string('key_version', 20)->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->unsignedInteger('duration_ms');
            $table->timestamps();
            $table->unique('request_id');
            $table->index(['integration', 'created_at'], 'integration_audit_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_api_audit_logs');
    }
};
