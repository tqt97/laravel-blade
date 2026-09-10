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
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('reconciliation_attempted_at')->nullable()->after('last_attempt_at');
            $table->unsignedInteger('reconciliation_attempts')->default(0)->after('reconciliation_attempted_at');
            $table->index(['status', 'reconciliation_attempted_at'], 'payments_reconciliation_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_reconciliation_lookup_index');
            $table->dropColumn(['reconciliation_attempted_at', 'reconciliation_attempts']);
        });
    }
};
