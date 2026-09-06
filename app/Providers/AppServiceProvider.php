<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Cinema\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Support\Payment\FakePaymentGateway;
use App\Support\Payment\StripePaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => filled(config('services.stripe.secret'))
            ? new StripePaymentGateway
            : new FakePaymentGateway);
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
        RateLimiter::for('booking-mutations', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(30)->by((string) ($user !== null ? $user->id : $request->ip()));
        });
    }
}
