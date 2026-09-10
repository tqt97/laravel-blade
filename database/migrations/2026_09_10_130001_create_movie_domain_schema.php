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
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('rating', 16)->nullable();
            $table->string('poster_path')->nullable();
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
            $table->string('status', 16)->default('scheduled')->index();
            $table->unsignedBigInteger('base_price_minor_units')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamps();

            $table->index(['screening_room_id', 'starts_at', 'ends_at']);
            $table->index(['movie_id', 'starts_at']);
            $table->index(['status', 'starts_at'], 'screenings_bookable_window_index');
        });
        Schema::create('screening_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('screening_id')->constrained()->cascadeOnDelete();
            $table->string('seat_type', 16);
            $table->unsignedBigInteger('price_minor_units');
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['screening_id', 'seat_type']);
        });
        Schema::create('concessions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sku', 64)->unique();
            $table->unsignedBigInteger('price_minor_units');
            $table->char('currency', 3);
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'currency', 'name'], 'concessions_active_currency_name_index');
        });
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('type', 16);
            $table->unsignedInteger('value');
            $table->unsignedBigInteger('maximum_discount_minor_units')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 32)->nullable();
            $table->foreignId('screening_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount_minor_units')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('subtotal_minor_units')->default(0);
            $table->unsignedBigInteger('discount_minor_units')->default(0);
            $table->unsignedBigInteger('total_minor_units')->default(0);
            $table->char('pricing_currency', 3)->default('USD');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->string('idempotency_hash', 64)->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['screening_id', 'status', 'created_at']);
            $table->index(['status', 'reminder_sent_at']);
            $table->index('created_at', 'bookings_created_at_index');
            $table->index(['user_id', 'screening_id', 'status', 'expires_at', 'id'], 'bookings_active_hold_ordered_index');
            $table->index(['user_id', 'screening_id', 'status', 'id', 'expires_at'], 'bookings_active_hold_order_index');
        });
        Schema::create('screening_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('screening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('available')->index();
            $table->string('hold_token', 128)->nullable();
            $table->foreignId('held_by_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->dateTime('held_until')->nullable()->index();
            $table->unsignedBigInteger('price_minor_units');
            $table->char('currency', 3);
            $table->dateTime('sold_at')->nullable();
            $table->timestamps();

            $table->unique(['screening_id', 'seat_id']);
            $table->index(['screening_id', 'status', 'held_until']);
            $table->index(['hold_token', 'status']);
            $table->index(['held_by_booking_id', 'status'], 'screening_seats_hold_owner_status_index');
        });
        Schema::create('booking_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screening_seat_id')->constrained()->restrictOnDelete();
            $table->string('ticket_code', 64)->unique();
            $table->string('qr_token_hash', 64)->nullable()->unique();
            $table->unsignedTinyInteger('qr_payload_version')->default(1);
            $table->unsignedBigInteger('price_minor_units');
            $table->char('currency', 3);
            $table->string('status', 16)->default('issued')->index();
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'screening_seat_id']);
            $table->index(['booking_id', 'status']);
        });
        Schema::create('booking_concessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concession_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor_units');
            $table->unsignedBigInteger('total_minor_units');
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['booking_id', 'concession_id']);
        });
        Schema::create('coupon_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('reserved');
            $table->timestamps();

            $table->unique(['coupon_id', 'booking_id']);
            $table->index(['coupon_id', 'status']);
        });
        Schema::create('booking_transition_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_transition_audits');
        Schema::dropIfExists('coupon_reservations');
        Schema::dropIfExists('booking_concessions');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('screening_seats');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('concessions');
        Schema::dropIfExists('screening_prices');
        Schema::dropIfExists('screenings');
        Schema::dropIfExists('seats');
        Schema::dropIfExists('screening_rooms');
        Schema::dropIfExists('movies');
    }
};
