<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('coupons')->select('id', 'code')->get() as $coupon) {
            DB::table('coupons')->where('id', $coupon->id)->update([
                'code' => strtoupper(trim((string) $coupon->code)),
            ]);
        }

        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedInteger('reserved_count')->default(0)->after('usage_limit');
            $table->unsignedInteger('redeemed_count')->default(0)->after('reserved_count');
            $table->string('pricing_scope', 32)->default('all')->after('redeemed_count');
            $table->index(['pricing_scope', 'is_active']);
        });

        Schema::create('coupon_user_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('reserved');
            $table->timestamps();

            $table->unique(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'status']);
        });

        foreach (DB::table('coupons')->select('id')->get() as $coupon) {
            DB::table('coupons')->where('id', $coupon->id)->update([
                'reserved_count' => DB::table('coupon_reservations')->where('coupon_id', $coupon->id)->where('status', 'reserved')->count(),
                'redeemed_count' => DB::table('coupon_reservations')->where('coupon_id', $coupon->id)->where('status', 'redeemed')->count(),
            ]);
        }

        $seenUsers = [];
        $existingUsages = DB::table('coupon_reservations')
            ->join('bookings', 'bookings.id', '=', 'coupon_reservations.booking_id')
            ->whereNotNull('bookings.user_id')
            ->whereIn('coupon_reservations.status', ['reserved', 'redeemed'])
            ->orderBy('coupon_reservations.coupon_id')
            ->orderBy('bookings.user_id')
            ->orderByRaw("CASE WHEN coupon_reservations.status = 'redeemed' THEN 0 ELSE 1 END")
            ->orderByDesc('coupon_reservations.created_at')
            ->get(['coupon_reservations.coupon_id', 'bookings.user_id', 'coupon_reservations.booking_id', 'coupon_reservations.status']);

        foreach ($existingUsages as $usage) {
            $key = $usage->coupon_id.':'.$usage->user_id;
            if (isset($seenUsers[$key])) {
                continue;
            }

            DB::table('coupon_user_usages')->insert([
                'coupon_id' => $usage->coupon_id,
                'user_id' => $usage->user_id,
                'booking_id' => $usage->booking_id,
                'status' => $usage->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $seenUsers[$key] = true;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user_usages');
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropIndex(['pricing_scope', 'is_active']);
            $table->dropColumn(['reserved_count', 'redeemed_count', 'pricing_scope']);
        });
    }
};
