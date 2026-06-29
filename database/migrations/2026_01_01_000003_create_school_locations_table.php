<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('school_locations', function (Blueprint $table) { $table->id(); $table->string('nama_lokasi',100); $table->decimal('latitude',10,8); $table->decimal('longitude',11,8); $table->integer('radius_meter')->default(100); $table->enum('status',['aktif','nonaktif'])->default('aktif'); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('school_locations'); } };
