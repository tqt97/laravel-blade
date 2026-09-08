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
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('processing_started_at')->nullable()->after('attempts');
            $table->timestamp('last_attempt_at')->nullable()->after('processing_started_at');
            $table->index(['status', 'processing_started_at'], 'payments_processing_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_processing_lookup_index');
            $table->dropColumn(['attempts', 'processing_started_at', 'last_attempt_at']);
        });
    }
};
