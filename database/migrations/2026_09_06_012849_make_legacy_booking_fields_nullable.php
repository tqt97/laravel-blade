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
            $table->foreignId('resource_id')->nullable()->change();
            $table->dateTime('start_at')->nullable()->change();
            $table->dateTime('end_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('resource_id')->nullable(false)->change();
            $table->dateTime('start_at')->nullable(false)->change();
            $table->dateTime('end_at')->nullable(false)->change();
        });
    }
};
