<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 32)->nullable();
            $table->foreignId('screening_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->index()
                ->comment('BookingStatus enum: held, pending_payment, confirmed, cancelled, expired, completed or no_show.');
            $table->unsignedBigInteger('amount_minor_units')->default(0)
                ->comment('Payment-facing amount snapshot in minor currency units.');
            $table->char('currency', 3)->default('USD')
                ->comment('ISO 4217 currency code for booking totals.');
            $table->unsignedBigInteger('subtotal_minor_units')->default(0)
                ->comment('Pre-discount subtotal snapshot in minor currency units.');
            $table->unsignedBigInteger('discount_minor_units')->default(0)
                ->comment('Applied discount snapshot in minor currency units.');
            $table->unsignedBigInteger('total_minor_units')->default(0)
                ->comment('Final payable total snapshot in minor currency units.');
            $table->char('pricing_currency', 3)->default('USD');
            $table->dateTime('expires_at')->nullable()
                ->comment('Hold/payment deadline; cleanup releases seats, combos and coupons after this time.');
            $table->dateTime('reminder_sent_at')->nullable();
            $table->string('idempotency_key', 128)->nullable()
                ->comment('Client retry key used to return the same booking for a duplicate hold request.');
            $table->string('idempotency_hash', 64)->nullable()
                ->comment('Server fingerprint used to detect key reuse with different request data.');
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            // Guarantees one result for the same user/request key; NULL remains reusable by SQL semantics.
            $table->unique(['user_id', 'idempotency_key']);
            // Required by the composite seat-owner foreign key below.
            $table->unique(['id', 'screening_id'], 'bookings_id_screening_unique');
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
            $table->string('status', 16)->default('available')->index()
                ->comment('ScreeningSeatStatus enum: available, held or sold.');
            $table->string('hold_token', 128)->nullable()
                ->comment('Opaque browser correlation token for the current seat hold.');
            $table->foreignId('held_by_booking_id')->nullable()
                ->comment('Booking that owns the hold; NULL when the seat is not held.');
            $table->dateTime('held_until')->nullable()->index()
                ->comment('Seat hold expiry; expired holds are treated as available.');
            $table->unsignedBigInteger('price_minor_units')
                ->comment('Price snapshot captured when the seat is held, in minor units.');
            $table->char('currency', 3)
                ->comment('ISO 4217 currency code for the seat price snapshot.');
            $table->dateTime('sold_at')->nullable();
            $table->timestamps();

            $table->unique(['screening_id', 'seat_id']);
            $table->index(['screening_id', 'status', 'held_until']);
            $table->index(['hold_token', 'status']);
            $table->index(['held_by_booking_id', 'status'], 'screening_seats_hold_owner_status_index');

            // MySQL enforces that a booking can only own seats from the same screening.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->foreign(['held_by_booking_id', 'screening_id'], 'screening_seats_booking_screening_foreign')
                    ->references(['id', 'screening_id'])
                    ->on('bookings')
                    ->restrictOnDelete();
            } else {
                $table->foreign('held_by_booking_id')->references('id')->on('bookings')->nullOnDelete();
            }
        });

        Schema::create('booking_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screening_seat_id')->constrained()->restrictOnDelete();
            $table->string('ticket_code', 64)->unique()
                ->comment('Public ticket identifier; unique across all issued tickets.');
            $table->string('qr_token_hash', 64)->nullable()->unique()
                ->comment('Hash of the private QR token; raw token must never be persisted.');
            $table->unsignedTinyInteger('qr_payload_version')->default(1)
                ->comment('Version of the signed QR payload format used for ticket validation.');
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
        Schema::dropIfExists('booking_concessions');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('screening_seats');
        Schema::dropIfExists('bookings');
    }
};
