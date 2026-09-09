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
        Schema::table('screening_seats', function (Blueprint $table): void {
            $table->foreignId('held_by_booking_id')->nullable()->after('hold_token')->constrained('bookings')->nullOnDelete();
            $table->index(['held_by_booking_id', 'status'], 'screening_seats_hold_owner_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screening_seats', function (Blueprint $table): void {
            $table->dropIndex('screening_seats_hold_owner_status_index');
            $table->dropForeign(['held_by_booking_id']);
            $table->dropColumn('held_by_booking_id');
        });
    }
};
