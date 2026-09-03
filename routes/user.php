<?php

// User routes belong here and inherit the user route group's web/auth middleware.

use Illuminate\Support\Facades\Route;

Route::view('/dashboard', 'user.dashboard')->name('dashboard');
