<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingReportController;
use App\Http\Controllers\Admin\MovieController;
use App\Http\Controllers\Admin\RedirectToDashboardController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Admin landing and static workspace pages.
Route::get('/', RedirectToDashboardController::class)->name('home');

Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::view('/samples', 'admin.samples')->name('samples');
Route::view('/blank', 'admin.blank')->name('blank');

// Booking operations and reports.
Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/reports', BookingReportController::class)->name('reports.index');

// Cinema catalogue, rooms, screenings and concessions.
Route::get('/cinema', [MovieController::class, 'index'])->name('cinema.index');
Route::get('/cinema/concessions', [MovieController::class, 'concessions'])->name('cinema.concessions.index');
Route::get('/cinema/coupons', [MovieController::class, 'coupons'])->name('cinema.coupons.index');
Route::post('/cinema/movies', [MovieController::class, 'storeMovie'])->name('cinema.movies.store');
Route::post('/cinema/rooms', [MovieController::class, 'storeRoom'])->name('cinema.rooms.store');
Route::post('/cinema/screenings', [MovieController::class, 'storeScreening'])->name('cinema.screenings.store');
Route::post('/cinema/concessions', [MovieController::class, 'storeConcession'])->name('cinema.concessions.store');
Route::post('/cinema/coupons', [MovieController::class, 'storeCoupon'])->name('cinema.coupons.store');
Route::patch('/cinema/concessions/{concession}', [MovieController::class, 'updateConcession'])->name('cinema.concessions.update');

// Booking cancellation/refund operations.
Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:booking-mutations')->name('bookings.cancel');
Route::post('/bookings/{booking}/refund', [BookingController::class, 'refund'])->middleware('throttle:booking-mutations')->name('bookings.refund');

// Ticket operations and protected settings.
Route::post('/tickets/check-in', TicketController::class)->middleware('throttle:booking-mutations')->name('tickets.check-in');
Route::view('/settings/security', 'admin.settings.security')
    ->middleware('password.confirm')
    ->name('settings.security');

// User management requires the explicit admin ability in addition to the
// parent authenticated/admin route group from bootstrap/app.php.
Route::middleware('can:manage-users')->group(function (): void {
    Route::patch('/users/bulk-restore', [UserController::class, 'bulkRestore'])->name('users.bulk-restore');
    Route::delete('/users/bulk-force-delete', [UserController::class, 'bulkForceDestroy'])->name('users.bulk-force-delete');
    Route::delete('/users/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
    Route::patch('/users/{userId}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('/users/{userId}/force-delete', [UserController::class, 'forceDestroy'])->name('users.force-delete');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});
