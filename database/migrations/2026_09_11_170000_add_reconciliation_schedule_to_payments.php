<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('next_reconcile_at')->nullable()->after('reconciliation_attempted_at');
            $table->timestamp('reconciliation_deadline')->nullable()->after('next_reconcile_at');
            $table->text('last_reconciliation_error')->nullable()->after('reconciliation_deadline');
            $table->index(['status', 'next_reconcile_at'], 'payments_reconciliation_schedule_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_reconciliation_schedule_index');
            $table->dropColumn(['next_reconcile_at', 'reconciliation_deadline', 'last_reconciliation_error']);
        });
    }
};
