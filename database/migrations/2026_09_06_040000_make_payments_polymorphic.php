<?php

use App\Models\Cinema\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('payable_type')->nullable()->after('id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
            $table->index(['payable_type', 'payable_id']);
        });

        DB::table('payments')->whereNotNull('booking_id')->update([
            'payable_type' => Booking::class,
        ]);
        DB::statement('UPDATE payments SET payable_id = booking_id WHERE booking_id IS NOT NULL');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id']);
            $table->dropColumn('booking_id');
            $table->string('payable_type')->nullable(false)->change();
            $table->unsignedBigInteger('payable_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('booking_id')->nullable()->after('id');
        });

        DB::statement("UPDATE payments SET booking_id = payable_id WHERE payable_type = '".addslashes(Booking::class)."'");

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['payable_type', 'payable_id']);
            $table->dropColumn(['payable_type', 'payable_id']);
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->unique('booking_id');
        });
    }
};
