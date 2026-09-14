<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TicketVerificationController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// Public locale/session preference.
Route::post('/locale', LocaleController::class)->name('locale.update');

// Public catalogue and SEO endpoints.
Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', [MovieController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/movies', [MovieController::class, 'index'])->name('cinema.movies.index');
Route::get('/movies/{movie:slug}', [MovieController::class, 'show'])->name('cinema.movies.show');

// Public seat selection endpoints with scoped movie/screening bindings.
Route::scopeBindings()->group(function (): void {
    Route::get('/movies/{movie:slug}/showtimes/{screening}', [MovieController::class, 'screening'])->name('cinema.screenings.show');
    Route::get('/movies/{movie:slug}/showtimes/{screening}/availability', [MovieController::class, 'availability'])
        ->middleware('throttle:availability')
        ->name('cinema.screenings.availability');
    Route::post('/movies/{movie:slug}/showtimes/{screening}/hold', [MovieController::class, 'hold'])
        ->middleware('throttle:booking-mutations')
        ->name('cinema.screenings.hold');
});

// Public signed ticket verification and provider webhook endpoints.
Route::get('/ticket-verify/{ticket}', TicketVerificationController::class)->middleware('signed')->name('user.tickets.verify');
Route::post('/webhooks/stripe', StripeWebhookController::class)->withoutMiddleware([PreventRequestForgery::class])->name('webhooks.stripe');
