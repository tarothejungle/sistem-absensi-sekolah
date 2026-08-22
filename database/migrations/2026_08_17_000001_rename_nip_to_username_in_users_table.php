<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'nip') && ! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('nip', 'username');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username') && ! Schema::hasColumn('users', 'nip')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('username', 'nip');
            });
        }
    }
};
