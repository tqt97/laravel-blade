<?php

use App\Booking\Models\Booking;
use App\Models\BookableResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows administrators to manage resources and bookings', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.resources.store'), [
        'name' => 'Meeting room A',
        'slug' => 'meeting-room-a',
        'description' => 'A quiet room',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'is_active' => '1',
    ])->assertRedirect(route('admin.resources.index'));

    $resource = BookableResource::query()->firstOrFail();
    expect($resource->is_active)->toBeTrue();

    $this->actingAs($admin)->get(route('admin.resources.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk();

    $booking = Booking::factory()->for($resource, 'resource')->create();
    $this->actingAs($admin)->patch(route('admin.bookings.cancel', $booking))->assertRedirect();
    expect($booking->refresh()->status->value)->toBe('cancelled');
});

it('does not expose admin booking management to regular users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.resources.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.bookings.index'))->assertForbidden();
});
