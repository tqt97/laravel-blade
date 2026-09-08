<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CinemaController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Queries\Cinema\BookingReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');

Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::view('/samples', 'admin.samples')->name('samples');
Route::view('/blank', 'admin.blank')->name('blank');
Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/reports', function (BookingReport $report) {
    $summary = $report->summary(CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth());

    return view('admin.reports.index', compact('summary'));
})->name('reports.index');
Route::get('/cinema', [CinemaController::class, 'index'])->name('cinema.index');
Route::get('/cinema/concessions', [CinemaController::class, 'concessions'])->name('cinema.concessions.index');
Route::post('/cinema/movies', [CinemaController::class, 'storeMovie'])->name('cinema.movies.store');
Route::post('/cinema/rooms', [CinemaController::class, 'storeRoom'])->name('cinema.rooms.store');
Route::post('/cinema/screenings', [CinemaController::class, 'storeScreening'])->name('cinema.screenings.store');
Route::post('/cinema/concessions', [CinemaController::class, 'storeConcession'])->name('cinema.concessions.store');
Route::patch('/cinema/concessions/{concession}', [CinemaController::class, 'updateConcession'])->name('cinema.concessions.update');
Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:booking-mutations')->name('bookings.cancel');
Route::post('/bookings/{booking}/refund', [BookingController::class, 'refund'])->middleware('throttle:booking-mutations')->name('bookings.refund');
Route::post('/tickets/check-in', TicketController::class)->middleware('throttle:booking-mutations')->name('tickets.check-in');
Route::view('/settings/security', 'admin.settings.security')
    ->middleware('password.confirm')
    ->name('settings.security');

Route::middleware('can:manage-users')->group(function (): void {
    Route::patch('/users/bulk-restore', [UserController::class, 'bulkRestore'])->name('users.bulk-restore');
    Route::delete('/users/bulk-force-delete', [UserController::class, 'bulkForceDestroy'])->name('users.bulk-force-delete');
    Route::delete('/users/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
    Route::patch('/users/{userId}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('/users/{userId}/force-delete', [UserController::class, 'forceDestroy'])->name('users.force-delete');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});
