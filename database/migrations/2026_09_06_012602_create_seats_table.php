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
        Schema::create('seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('screening_room_id')->constrained()->cascadeOnDelete();
            $table->string('row_label', 8);
            $table->unsignedSmallInteger('seat_number');
            $table->string('seat_type', 16)->default('regular');
            $table->unsignedBigInteger('price_minor_units')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['screening_room_id', 'row_label', 'seat_number']);
            $table->index(['screening_room_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
