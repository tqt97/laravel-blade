<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusRetriever;
use App\Enums\Payment\PaymentProvider;
use App\Models\Movie\Booking;
use App\Models\User;
use App\Observers\Movie\BookingObserver;
use App\Policies\Movie\BookingPolicy;
use App\Support\Payment\FakePaymentGateway;
use App\Support\Payment\StripePaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $factory = function (): PaymentGateway {
            $provider = PaymentProvider::configured();

            return match ($provider) {
                PaymentProvider::Stripe => filled(config('services.stripe.secret'))
                    ? new StripePaymentGateway
                    : throw new \LogicException('Stripe payment provider is configured without STRIPE_SECRET.'),
                PaymentProvider::Fake => app()->environment(['local', 'testing'])
                    ? new FakePaymentGateway
                    : throw new \LogicException('Fake payment provider is not allowed outside local/testing environments.'),
            };
        };

        $this->app->bind(PaymentGateway::class, $factory);
        $this->app->bind(PaymentStatusRetriever::class, fn (): PaymentStatusRetriever => $factory());
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
        Booking::observe(BookingObserver::class);
        RateLimiter::for('booking-mutations', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(30)->by((string) ($user !== null ? $user->id : $request->ip()));
        });
        RateLimiter::for('availability', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(120)->by((string) ($user !== null ? $user->id : $request->ip()));
        });
        $slowQueryThreshold = (int) config('booking.observability.slow_query_ms', 0);
        if ($slowQueryThreshold > 0) {
            DB::listen(function (QueryExecuted $query) use ($slowQueryThreshold): void {
                if ($query->time < $slowQueryThreshold) {
                    return;
                }

                Log::warning('database.slow_query', [
                    'connection' => $query->connectionName,
                    'duration_ms' => $query->time,
                    'sql' => $query->toRawSql(),
                ]);
            });
        }
    }
}
