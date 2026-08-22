<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendances', 'temporary_leave_request_id') || $this->hasForeignKey()) {
            return;
        }

        DB::table('attendances')
            ->whereNotNull('temporary_leave_request_id')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('leave_requests')
                ->whereColumn('leave_requests.id', 'attendances.temporary_leave_request_id'))
            ->update(['temporary_leave_request_id' => null]);

        Schema::table('attendances', function (Blueprint $table): void {
            $table->foreign('temporary_leave_request_id', 'att_temporary_leave_fk')
                ->references('id')
                ->on('leave_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if ($this->hasForeignKey()) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->dropForeign('att_temporary_leave_fk');
            });
        }
    }

    private function hasForeignKey(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'attendances')
            ->where('COLUMN_NAME', 'temporary_leave_request_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
