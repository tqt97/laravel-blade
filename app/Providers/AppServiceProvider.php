<?php

namespace App\Providers;

use App\Booking\Models\Booking;
use App\Booking\Policies\BookingPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catch accidental N+1 queries during local development and tests.
        // Production keeps lazy loading available for compatibility, while
        // read paths should still explicitly select/eager-load what they use.
        Model::preventLazyLoading(! app()->isProduction());

        Gate::define('manage-users', fn (User $user): bool => $user->is_admin);
        Gate::policy(Booking::class, BookingPolicy::class);
    }
}
