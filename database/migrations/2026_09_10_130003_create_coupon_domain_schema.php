<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('type', 16)
                ->comment('CouponType enum: fixed or percentage.');
            $table->unsignedInteger('value')
                ->comment('Percentage points for percentage coupons, or minor units for fixed coupons.');
            $table->unsignedBigInteger('maximum_discount_minor_units')->nullable()
                ->comment('Optional maximum discount cap in minor currency units.');
            $table->char('currency', 3)->nullable()
                ->comment('Required for fixed coupons; nullable for percentage coupons.');
            $table->unsignedInteger('usage_limit')->nullable()
                ->comment('NULL means unlimited total usage.');
            $table->unsignedInteger('reserved_count')->default(0)
                ->comment('Transactional count of currently reserved coupon uses.');
            $table->unsignedInteger('redeemed_count')->default(0)
                ->comment('Transactional count of coupon uses completed by successful payment.');
            $table->string('pricing_scope', 32)->default('all')
                ->comment('CouponPricingScope enum: all, tickets_only or concessions_only.');
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Lookup for coupons that are active inside the current validity window.
            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index(['pricing_scope', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
