<?php

// User routes belong here and inherit the user route group's web/auth middleware.

use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\ResourceController;
use Illuminate\Support\Facades\Route;

Route::view('/dashboard', 'user.dashboard')->name('dashboard');
Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
