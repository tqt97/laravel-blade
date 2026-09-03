<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');

Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::view('/samples', 'admin.samples')->name('samples');
Route::view('/blank', 'admin.blank')->name('blank');
Route::view('/settings/security', 'admin.settings.security')->name('settings.security');
