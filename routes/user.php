<?php

// User routes belong here and inherit the user route group's web/auth middleware.

use App\Http\Controllers\Cinema\PublicCinemaController;
use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\ScreeningController;
use App\Http\Controllers\User\TicketController;
use App\Models\Cinema\BookingItem;
use Illuminate\Support\Facades\Route;

Route::view('/dashboard', 'user.dashboard')->name('dashboard');
Route::get('/screenings', fn () => to_route('cinema.movies.index'))->name('screenings.index');
Route::get('/cinema/hold/resume', [PublicCinemaController::class, 'resumeHold'])->name('cinema.hold.resume');
Route::get('/screenings/{screening}', [ScreeningController::class, 'show'])->name('screenings.show');
Route::post('/screenings/{screening}/hold', [ScreeningController::class, 'hold'])->middleware('throttle:booking-mutations')->name('screenings.hold');
Route::get('/tickets/{ticket}', TicketController::class)->name('tickets.show');
Route::get('/ticket-verify/{ticket}', fn (string $ticket) => view('user.tickets.verify', ['ticket' => BookingItem::query()->where('ticket_code', $ticket)->firstOrFail()]))->middleware('signed')->name('tickets.verify');
Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->middleware('throttle:booking-mutations')->name('bookings.confirm');
Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay'])->middleware('throttle:booking-mutations')->name('bookings.pay');
Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:booking-mutations')->name('bookings.cancel');
