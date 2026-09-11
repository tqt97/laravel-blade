<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('booking_items')
            ->where('status', 'issued')
            ->where('ticket_code', 'like', 'HOLD-%')
            ->update(['status' => 'reserved']);

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unique(['id', 'screening_id'], 'bookings_id_screening_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('screening_seats', function (Blueprint $table): void {
                $table->dropForeign(['held_by_booking_id']);
                $table->foreign(['held_by_booking_id', 'screening_id'], 'screening_seats_booking_screening_foreign')
                    ->references(['id', 'screening_id'])
                    ->on('bookings')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('screening_seats', function (Blueprint $table): void {
                $table->dropForeign('screening_seats_booking_screening_foreign');
                $table->foreign('held_by_booking_id')->references('id')->on('bookings')->nullOnDelete();
            });
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique('bookings_id_screening_unique');
        });
    }
};
