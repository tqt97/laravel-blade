<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('synopsis')->nullable();
            $table->unsignedSmallInteger('duration_minutes')
                ->comment('Movie runtime in minutes; used to calculate screening windows.');
            $table->string('rating', 16)->nullable()
                ->comment('Content rating such as G, PG, PG-13 or R; nullable when unclassified.');
            $table->string('genre')->nullable();
            $table->string('director')->nullable();
            $table->json('cast')->nullable()
                ->comment('JSON array of cast names or structured cast entries.');
            $table->string('language', 32)->nullable();
            $table->string('format', 16)->nullable()
                ->comment('Presentation format such as 2D, 3D or IMAX.');
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('trailer_url')->nullable();
            $table->date('release_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('screening_rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

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

        Schema::create('screenings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained()->restrictOnDelete();
            $table->foreignId('screening_room_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 16)->default('scheduled')->index()
                ->comment('ScreeningStatus enum: scheduled, cancelled or completed.');
            $table->unsignedBigInteger('base_price_minor_units')->default(0)
                ->comment('Base ticket price in minor currency units; never store floating-point money.');
            $table->char('currency', 3)->default('USD')
                ->comment('ISO 4217 currency code for all screening prices.');
            $table->timestamps();

            // Supports room-overlap checks when creating or changing a screening.
            $table->index(['screening_room_id', 'starts_at', 'ends_at']);
            $table->index(['movie_id', 'starts_at']);
            $table->index(['status', 'starts_at'], 'screenings_bookable_window_index');
        });

        Schema::create('screening_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('screening_id')->constrained()->cascadeOnDelete();
            $table->string('seat_type', 16)
                ->comment('SeatType enum value used for this screening price tier.');
            $table->unsignedBigInteger('price_minor_units')
                ->comment('Price override in minor currency units.');
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['screening_id', 'seat_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_prices');
        Schema::dropIfExists('screenings');
        Schema::dropIfExists('seats');
        Schema::dropIfExists('screening_rooms');
        Schema::dropIfExists('movies');
    }
};
