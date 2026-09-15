<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('reserved')
                ->comment('CouponReservationStatus enum: reserved, redeemed or released.');
            $table->timestamps();

            // One reservation record per coupon/booking, even when expiry cleanup is retried.
            $table->unique(['coupon_id', 'booking_id']);
            $table->index(['coupon_id', 'status']);
        });

        Schema::create('coupon_user_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('reserved')
                ->comment('CouponReservationStatus enum; one active use is allowed per user.');
            $table->timestamps();

            $table->unique(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user_usages');
        Schema::dropIfExists('coupon_reservations');
    }
};
