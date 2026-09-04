<?php

namespace App\Models;

use App\Booking\Models\Booking;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'is_admin'])]
// Fortify stores encrypted 2FA material on the model. Hiding it prevents
// accidental exposure through JSON responses, logs, debug pages or audits;
// it remains available to Fortify internally when the user authenticates.
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Limit the query to administrator accounts.
     *
     * Keeping this rule in the model prevents small differences in the
     * definition of an administrator from spreading across policies,
     * actions, seeders and reporting queries.
     */
    public function scopeAdministrators(Builder $query): Builder
    {
        return $query->where('is_admin', true);
    }

    /**
     * Limit the query to non-administrator accounts.
     */
    public function scopeRegularUsers(Builder $query): Builder
    {
        return $query->where('is_admin', false);
    }

    /**
     * Limit the query to users with confirmed email addresses.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Limit the query to users whose email is not confirmed.
     */
    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('email_verified_at');
    }

    /**
     * Limit the query to users who completed 2FA setup.
     */
    public function scopeTwoFactorEnabled(Builder $query): Builder
    {
        return $query->whereNotNull('two_factor_confirmed_at');
    }

    /**
     * Limit the query to users who have not completed 2FA setup.
     */
    public function scopeTwoFactorDisabled(Builder $query): Builder
    {
        return $query->whereNull('two_factor_confirmed_at');
    }
}
