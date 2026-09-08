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
            $table->foreign('screening_id')->references('id')->on('screenings')->restrictOnDelete();
            $table->unsignedBigInteger('subtotal_minor_units')->default(0)->after('currency');
            $table->unsignedBigInteger('discount_minor_units')->default(0)->after('subtotal_minor_units');
            $table->unsignedBigInteger('total_minor_units')->default(0)->after('discount_minor_units');
            $table->char('pricing_currency', 3)->default('USD')->after('total_minor_units');
            $table->index(['screening_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['screening_id']);
            $table->dropIndex(['screening_id', 'status', 'created_at']);
            $table->dropColumn(['screening_id', 'subtotal_minor_units', 'discount_minor_units', 'total_minor_units', 'pricing_currency']);
        });
    }
};
