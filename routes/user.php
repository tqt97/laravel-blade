<?php

// User routes belong here and inherit the user route group's web/auth middleware.

use App\Http\Controllers\Cinema\PublicCinemaController;
use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\ScreeningController;
use App\Http\Controllers\User\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class)->name('dashboard');
Route::get('/screenings', fn () => to_route('cinema.movies.index'))->name('screenings.index');
Route::get('/cinema/hold/resume', [PublicCinemaController::class, 'resumeHold'])->name('cinema.hold.resume');
Route::get('/screenings/{screening}', [ScreeningController::class, 'show'])->name('screenings.show');
Route::post('/screenings/{screening}/hold', [ScreeningController::class, 'hold'])->middleware('throttle:booking-mutations')->name('screenings.hold');
Route::get('/tickets/{ticket}', TicketController::class)->name('tickets.show');
Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
Route::get('/bookings/{booking}/checkout', [BookingController::class, 'checkout'])->name('bookings.checkout');
Route::get('/bookings/{booking}/success', [BookingController::class, 'success'])->name('bookings.success');
Route::get('/bookings/{booking}/payment-action', [BookingController::class, 'paymentAction'])->name('bookings.payment-action');
Route::get('/bookings/{booking}/payment-status', [BookingController::class, 'paymentStatus'])->name('bookings.payment-status');
Route::get('/bookings/{booking}/combo-availability', [BookingController::class, 'comboAvailability'])->middleware('throttle:availability')->name('bookings.combo-availability');
Route::get('/bookings/{booking}/combos', [BookingController::class, 'combos'])->name('bookings.combos');
Route::post('/bookings/{booking}/combos', [BookingController::class, 'addCombos'])->middleware('throttle:booking-mutations')->name('bookings.combos.store');
Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay'])->middleware('throttle:booking-mutations')->name('bookings.pay');
Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:booking-mutations')->name('bookings.cancel');
