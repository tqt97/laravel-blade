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
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->string('provider_payment_id')->nullable()->after('event_id');
            $table->timestamp('orphaned_at')->nullable()->after('processed_at');
            $table->unsignedSmallInteger('processing_attempts')->default(0)->after('orphaned_at');
            $table->timestamp('last_attempt_at')->nullable()->after('processing_attempts');
            $table->index(['provider', 'provider_payment_id'], 'payment_webhook_provider_payment_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->dropIndex('payment_webhook_provider_payment_index');
            $table->dropColumn(['provider_payment_id', 'orphaned_at', 'processing_attempts', 'last_attempt_at']);
        });
    }
};
