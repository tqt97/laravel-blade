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
            $table->foreignId('coupon_id')->nullable()->after('id')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 32)->nullable()->after('coupon_id');
            $table->dateTime('reminder_sent_at')->nullable()->after('expires_at');
            $table->index(['status', 'reminder_sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['coupon_id']);
            $table->dropIndex(['status', 'reminder_sent_at']);
            $table->dropColumn(['coupon_id', 'coupon_code', 'reminder_sent_at']);
        });
    }
};
