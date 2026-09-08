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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['user_id', 'screening_id', 'status', 'expires_at'], 'bookings_active_hold_lookup_index');
            $table->index('created_at', 'bookings_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_active_hold_lookup_index');
            $table->dropIndex('bookings_created_at_index');
        });
    }
};
