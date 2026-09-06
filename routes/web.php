<?php

use App\Http\Controllers\Cinema\PublicCinemaController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
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
Route::get('/movies', [PublicCinemaController::class, 'index'])->name('cinema.movies.index');
Route::get('/movies/{movie:slug}', [PublicCinemaController::class, 'movie'])->name('cinema.movies.show');
Route::get('/showtimes/{screening}', [PublicCinemaController::class, 'screening'])->name('cinema.screenings.show');
Route::post('/showtimes/{screening}/hold', [PublicCinemaController::class, 'hold'])->middleware('throttle:booking-mutations')->name('cinema.screenings.hold');

Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.stripe');
