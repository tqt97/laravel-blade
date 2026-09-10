<?php

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Http\Controllers\Movie\PublicMovieController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Models\Movie\BookingItem;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/locale', function (Request $request) {
    $locale = $request->string('locale')->toString();

    abort_unless(in_array($locale, ['en', 'vi'], true), 422);

    $request->session()->put('locale', $locale);

    return back();
})->name('locale.update');

Route::get('/', function () {
    return view('welcome');
})->name('home');
Route::get('/sitemap.xml', [PublicMovieController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/movies', [PublicMovieController::class, 'index'])->name('cinema.movies.index');
Route::get('/movies/{movie:slug}', [PublicMovieController::class, 'movie'])->name('cinema.movies.show');
Route::scopeBindings()->group(function (): void {
    Route::get('/movies/{movie:slug}/showtimes/{screening}', [PublicMovieController::class, 'screening'])->name('cinema.screenings.show');
    Route::get('/movies/{movie:slug}/showtimes/{screening}/availability', [PublicMovieController::class, 'availability'])->middleware('throttle:availability')->name('cinema.screenings.availability');
    Route::post('/movies/{movie:slug}/showtimes/{screening}/hold', [PublicMovieController::class, 'hold'])->middleware('throttle:booking-mutations')->name('cinema.screenings.hold');
});
Route::get('/ticket-verify/{ticket}', function (string $ticket) {
    $ticket = BookingItem::query()
        ->with(['booking.screening.movie', 'booking.screening.room', 'screeningSeat.seat'])
        ->where('ticket_code', $ticket)
        ->firstOrFail();
    abort_unless(
        in_array($ticket->booking->getRawOriginal('status'), [BookingStatus::Confirmed->value, BookingStatus::Completed->value], true)
        && in_array($ticket->getRawOriginal('status'), [TicketStatus::Issued->value, TicketStatus::CheckedIn->value], true),
        404,
    );

    return view('user.tickets.verify', compact('ticket'));
})->middleware('signed')->name('user.tickets.verify');

Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.stripe');
