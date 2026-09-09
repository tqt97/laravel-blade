<?php

namespace App\Models\Movie;

use App\Enums\Infrastructure\OutboxEventType;
use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Payments\Payment;
use App\Models\User;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Carbon\CarbonImmutable;
use Database\Factories\Movie\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Fillable(['user_id', 'screening_id', 'expires_at', 'idempotency_key', 'idempotency_hash', 'cancellation_reason', 'amount_minor_units', 'currency', 'subtotal_minor_units', 'discount_minor_units', 'total_minor_units', 'pricing_currency', 'coupon_id', 'coupon_code', 'reminder_sent_at'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Booking $booking): void {
            OutboxMessage::query()->create([
                'aggregate_type' => self::class,
                'aggregate_id' => $booking->id,
                'event_type' => OutboxEventType::BookingCreated,
                'payload' => ['booking_id' => $booking->id, 'status' => BookingStatus::Held->value],
            ]);
        });

        static::updated(function (Booking $booking): void {
            if (! $booking->wasChanged('status')) {
                return;
            }
            $fromStatus = (string) $booking->getRawOriginal('status');
            $toStatus = (string) $booking->getAttributes()['status'];

            BookingTransitionAudit::query()->create([
                'booking_id' => $booking->id,
                'actor_id' => Auth::id(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $booking->getAttributes()['cancellation_reason'] ?? null,
            ]);

            OutboxMessage::query()->create([
                'aggregate_type' => self::class,
                'aggregate_id' => $booking->id,
                'event_type' => OutboxEventType::BookingStatusChanged,
                'payload' => ['booking_id' => $booking->id, 'from' => $fromStatus, 'to' => $toStatus],
            ]);

            Log::info('booking.status_changed', [
                'booking_id' => $booking->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_id' => Auth::id(),
            ]);
        });
    }

    protected static function newFactory(): Factory
    {
        return BookingFactory::new();
    }

    /** @param array<string, mixed> $attributes */
    public static function createHeld(array $attributes): self
    {
        $booking = new self;
        $booking->fill($attributes);
        $booking->setAttribute('status', BookingStatus::Held);
        $booking->save();

        return $booking;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transitionAudits(): HasMany
    {
        return $this->hasMany(BookingTransitionAudit::class);
    }

    /** @return MorphOne<Payment, $this> */
    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    /** @return BelongsTo<Screening, $this> */
    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /** @return HasMany<BookingConcession, $this> */
    public function concessions(): HasMany
    {
        return $this->hasMany(BookingConcession::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponReservations(): HasMany
    {
        return $this->hasMany(CouponReservation::class);
    }

    public function transitionTo(BookingStatus $target): void
    {
        $current = BookingStatus::tryFrom((string) $this->getRawOriginal('status'));
        if ($current === null || ! $current->canTransitionTo($target)) {
            throw new InvalidBookingTransition(__('booking.messages.invalid_transition'));
        }
        $this->setAttribute('status', $target);
    }

    public function scopeActiveHold(Builder $query, ?CarbonImmutable $now = null): void
    {
        $table = $query->getModel()->getTable();
        $query->whereIn($table.'.status', [BookingStatus::Held, BookingStatus::PendingPayment])
            ->where($table.'.expires_at', '>', $now ?? now()->utc());
    }

    public function scopeExpiredHold(Builder $query, ?CarbonImmutable $now = null): void
    {
        $table = $query->getModel()->getTable();
        $query->whereIn($table.'.status', [BookingStatus::Held, BookingStatus::PendingPayment])
            ->whereNotNull($table.'.expires_at')
            ->where($table.'.expires_at', '<=', $now ?? now()->utc());
    }

    public function scopeUpcoming(Builder $query, ?CarbonImmutable $now = null): void
    {
        $now ??= now()->utc();
        $table = $query->getModel()->getTable();
        $query->where(function ($query) use ($now, $table): void {
            $query->where($table.'.status', BookingStatus::Confirmed)
                ->orWhere(fn ($pendingQuery) => $pendingQuery
                    ->where($table.'.status', BookingStatus::PendingPayment)
                    ->where($table.'.expires_at', '>', $now));
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'expires_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'amount_minor_units' => 'integer',
            'subtotal_minor_units' => 'integer',
            'discount_minor_units' => 'integer',
            'total_minor_units' => 'integer',
        ];
    }
}
