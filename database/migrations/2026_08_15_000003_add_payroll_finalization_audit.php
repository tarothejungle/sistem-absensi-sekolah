<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_periods')) {
            Schema::table('payroll_periods', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_periods', 'finalized_by')) {
                    $table->foreignId('finalized_by')->nullable()->after('generated_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('payroll_periods', 'finalized_at')) {
                    $table->dateTime('finalized_at')->nullable()->after('finalized_by');
                }
            });
        }

        if (! Schema::hasTable('payroll_audit_logs')) {
            Schema::create('payroll_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 30);
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['payroll_period_id', 'created_at'], 'payroll_audit_period_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audit_logs');

        if (Schema::hasTable('payroll_periods')) {
            Schema::table('payroll_periods', function (Blueprint $table): void {
                if (Schema::hasColumn('payroll_periods', 'finalized_by')) {
                    $table->dropConstrainedForeignId('finalized_by');
                }
                if (Schema::hasColumn('payroll_periods', 'finalized_at')) {
                    $table->dropColumn('finalized_at');
                }
            });
        }
    }
};
