<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('school_locations', 'max_gps_accuracy')) {
            Schema::table('school_locations', function (Blueprint $table) {
                $table->integer('max_gps_accuracy')->default(100)->after('radius_meter');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('school_locations', 'max_gps_accuracy')) {
            Schema::table('school_locations', function (Blueprint $table) {
                $table->dropColumn('max_gps_accuracy');
            });
        }
    }
};