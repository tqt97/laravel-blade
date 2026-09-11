<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Movie\PublicMovieController;
use App\Http\Controllers\TicketVerificationController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Public locale/session preference.
Route::post('/locale', LocaleController::class)->name('locale.update');

// Public catalogue and SEO endpoints.
Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', [PublicMovieController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/movies', [PublicMovieController::class, 'index'])->name('cinema.movies.index');
Route::get('/movies/{movie:slug}', [PublicMovieController::class, 'movie'])->name('cinema.movies.show');

// Public seat selection endpoints with scoped movie/screening bindings.
Route::scopeBindings()->group(function (): void {
    Route::get('/movies/{movie:slug}/showtimes/{screening}', [PublicMovieController::class, 'screening'])->name('cinema.screenings.show');
    Route::get('/movies/{movie:slug}/showtimes/{screening}/availability', [PublicMovieController::class, 'availability'])->middleware('throttle:availability')->name('cinema.screenings.availability');
    Route::post('/movies/{movie:slug}/showtimes/{screening}/hold', [PublicMovieController::class, 'hold'])->middleware('throttle:booking-mutations')->name('cinema.screenings.hold');
});

// Public signed ticket verification and provider webhook endpoints.
Route::get('/ticket-verify/{ticket}', TicketVerificationController::class)->middleware('signed')->name('user.tickets.verify');
Route::post('/webhooks/stripe', StripeWebhookController::class)->withoutMiddleware([ValidateCsrfToken::class])->name('webhooks.stripe');
