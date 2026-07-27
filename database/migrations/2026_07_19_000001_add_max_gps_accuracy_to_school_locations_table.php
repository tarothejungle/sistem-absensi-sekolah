<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('school_locations', function (Blueprint $table) { $table->integer('max_gps_accuracy')->default(100)->after('radius_meter'); }); } public function down(): void { Schema::table('school_locations', function (Blueprint $table) { $table->dropColumn('max_gps_accuracy'); }); } };
