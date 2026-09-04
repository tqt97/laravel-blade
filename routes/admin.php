<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');

Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::view('/samples', 'admin.samples')->name('samples');
Route::view('/blank', 'admin.blank')->name('blank');
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
