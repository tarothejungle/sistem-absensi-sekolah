<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_photo')) {
                $table->string('profile_photo')->nullable()->after('name');
            }

            if (!Schema::hasColumn('users', 'instansi_mengajar')) {
                $table->string('instansi_mengajar')->nullable()->after('profile_photo');
            }

            if (!Schema::hasColumn('users', 'tempat_lahir')) {
                $table->string('tempat_lahir')->nullable()->after('instansi_mengajar');
            }

            if (!Schema::hasColumn('users', 'tanggal_lahir')) {
                $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            }

            if (!Schema::hasColumn('users', 'pendidikan_terakhir')) {
                $table->string('pendidikan_terakhir')->nullable()->after('tanggal_lahir');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'profile_photo')) {
                $table->dropColumn('profile_photo');
            }

            if (Schema::hasColumn('users', 'instansi_mengajar')) {
                $table->dropColumn('instansi_mengajar');
            }

            if (Schema::hasColumn('users', 'tempat_lahir')) {
                $table->dropColumn('tempat_lahir');
            }

            if (Schema::hasColumn('users', 'tanggal_lahir')) {
                $table->dropColumn('tanggal_lahir');
            }

            if (Schema::hasColumn('users', 'pendidikan_terakhir')) {
                $table->dropColumn('pendidikan_terakhir');
            }
        });
    }
};