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
        Schema::create('screening_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('screening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('available')->index();
            $table->string('hold_token', 128)->nullable();
            $table->dateTime('held_until')->nullable()->index();
            $table->unsignedBigInteger('price_minor_units');
            $table->char('currency', 3);
            $table->dateTime('sold_at')->nullable();
            $table->timestamps();
            $table->unique(['screening_id', 'seat_id']);
            $table->index(['screening_id', 'status', 'held_until']);
            $table->index(['hold_token', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screening_seats');
    }
};
