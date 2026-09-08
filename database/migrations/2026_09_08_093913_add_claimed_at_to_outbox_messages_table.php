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
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->timestamp('claimed_at')->nullable()->after('available_at');
            $table->index(['published_at', 'failed_at', 'claimed_at', 'available_at'], 'outbox_claimable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->dropIndex('outbox_claimable_index');
            $table->dropColumn('claimed_at');
        });
    }
};
