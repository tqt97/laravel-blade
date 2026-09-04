<?php

use App\Booking\Actions\CreateBooking;
use App\Booking\Actions\ExpireBooking;
use App\Booking\Data\CreateBookingData;
use App\Booking\Enums\BookingStatus;
use App\Booking\Exceptions\BookingConflict;
use App\Booking\Exceptions\IdempotencyConflict;
use App\Booking\Models\Booking;
use App\Booking\ValueObjects\BookingPeriod;
use App\Models\BookableResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a user create and manage a booking', function (): void {
    $user = User::factory()->create();
    $resource = BookableResource::factory()->create();

    $this->actingAs($user)->post(route('user.bookings.store'), [
        'resource_id' => $resource->id,
        'start_at' => now()->addDay()->format('Y-m-d\\TH:i'),
        'end_at' => now()->addDay()->addHour()->format('Y-m-d\\TH:i'),
        'idempotency_key' => 'booking-1',
    ])->assertRedirect();

    $booking = Booking::query()->firstOrFail();
    expect($booking->status)->toBe(BookingStatus::Held);

    $this->actingAs($user)->post(route('user.bookings.confirm', $booking))->assertRedirect();
    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);

    $this->actingAs($user)->patch(route('user.bookings.cancel', $booking))->assertRedirect();
    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
});

it('rejects overlapping bookings and protects booking ownership', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $resource = BookableResource::factory()->create();
    $booking = Booking::factory()->for($owner)->for($resource, 'resource')->create();

    $this->actingAs($otherUser)->get(route('user.bookings.show', $booking))->assertForbidden();

    $this->actingAs($otherUser)->post(route('user.bookings.store'), [
        'resource_id' => $resource->id,
        'start_at' => $booking->start_at->format('Y-m-d\\TH:i'),
        'end_at' => $booking->end_at->format('Y-m-d\\TH:i'),
    ])->assertSessionHasErrors('start_at');
});

it('returns the same booking for a safe retry and rejects key reuse with another payload', function (): void {
    $user = User::factory()->create();
    $resource = BookableResource::factory()->create();
    $data = new CreateBookingData(
        $resource->id,
        BookingPeriod::fromDateTimes(now()->addDay()->toImmutable(), now()->addDay()->addHour()->toImmutable()),
        'retry-key',
    );

    $first = app(CreateBooking::class)->execute($user, $data);
    $second = app(CreateBooking::class)->execute($user, $data);
    expect($second->id)->toBe($first->id);

    $different = new CreateBookingData(
        $resource->id,
        BookingPeriod::fromDateTimes(now()->addDays(2)->toImmutable(), now()->addDays(2)->addHour()->toImmutable()),
        'retry-key',
    );

    expect(fn () => app(CreateBooking::class)->execute($user, $different))
        ->toThrow(IdempotencyConflict::class);
});

it('allows adjacent half-open booking periods', function (): void {
    $resource = BookableResource::factory()->create();
    $user = User::factory()->create();
    Booking::factory()->for($resource, 'resource')->for($user)->create([
        'start_at' => now()->addDay()->startOfHour(),
        'end_at' => now()->addDay()->startOfHour()->addHour(),
        'status' => BookingStatus::Confirmed,
    ]);

    expect(fn () => app(CreateBooking::class)->execute($user, new CreateBookingData(
        $resource->id,
        BookingPeriod::fromDateTimes(now()->addDay()->startOfHour()->addHour()->toImmutable(), now()->addDay()->startOfHour()->addHours(2)->toImmutable()),
    )))->not->toThrow(BookingConflict::class);
});

it('expires an elapsed hold and frees the period', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Held,
        'expires_at' => now()->subMinute(),
    ]);

    expect(app(ExpireBooking::class)->execute($booking))->toBeTrue();
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired);
});
