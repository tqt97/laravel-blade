<?php

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
});
