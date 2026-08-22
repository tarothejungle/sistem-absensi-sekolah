<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceDeleteRule('teachers', 'user_id', 'users', 'restrict');
        $this->replaceDeleteRule('attendances', 'teacher_id', 'teachers', 'restrict');
        $this->replaceDeleteRule('leave_requests', 'teacher_id', 'teachers', 'restrict');
        $this->replaceDeleteRule('teacher_salaries', 'teacher_id', 'teachers', 'restrict');
        $this->replaceDeleteRule('payroll_items', 'teacher_id', 'teachers', 'restrict');
        $this->replaceDeleteRule('leave_request_infal', 'teacher_id', 'teachers', 'restrict');
    }

    public function down(): void
    {
        $this->replaceDeleteRule('teachers', 'user_id', 'users', 'cascade');
        $this->replaceDeleteRule('attendances', 'teacher_id', 'teachers', 'cascade');
        $this->replaceDeleteRule('leave_requests', 'teacher_id', 'teachers', 'cascade');
        $this->replaceDeleteRule('teacher_salaries', 'teacher_id', 'teachers', 'cascade');
        $this->replaceDeleteRule('payroll_items', 'teacher_id', 'teachers', 'cascade');
        $this->replaceDeleteRule('leave_request_infal', 'teacher_id', 'teachers', 'cascade');
    }

    private function replaceDeleteRule(string $table, string $column, string $references, string $rule): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $constraint = $this->foreignKeyName($table, $column);

            if ($constraint !== null) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    str_replace('`', '``', $table),
                    str_replace('`', '``', $constraint),
                ));
            }
        } else {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });
        }

        if (DB::getDriverName() === 'mysql' && $constraint === null) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $rule): void {
            $foreign = $blueprint->foreign($column)->references('id')->on($references);

            if ($rule === 'cascade') {
                $foreign->cascadeOnDelete();
            } else {
                $foreign->restrictOnDelete();
            }
        });
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$table, $column],
        );

        return $row?->CONSTRAINT_NAME;
    }
};
