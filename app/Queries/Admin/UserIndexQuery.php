<?php

namespace App\Queries\Admin;

use App\Enums\Admin\UserRole;
use App\Enums\Admin\UserStatus;
use App\Enums\Admin\UserTwoFactorStatus;
use App\Enums\Admin\UserVerificationStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UserIndexQuery
{
    /**
     * @param  array{search?: string, verification?: string, role?: string, two_factor?: string, status?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        // Convert untrusted query-string values into finite states once. The
        // query below then branches on enums instead of repeating magic
        // strings and has a safe Active default for a missing status.
        $status = UserStatus::tryFrom($filters['status'] ?? '') ?? UserStatus::Active;
        $verification = UserVerificationStatus::tryFrom($filters['verification'] ?? '') ?? UserVerificationStatus::All;
        $role = UserRole::tryFrom($filters['role'] ?? '') ?? UserRole::All;
        $twoFactor = UserTwoFactorStatus::tryFrom($filters['two_factor'] ?? '') ?? UserTwoFactorStatus::All;

        $query = User::query()
            ->when($status === UserStatus::Deleted, fn (Builder $query): Builder => $query->onlyTrashed())
            ->when($status === UserStatus::All, fn (Builder $query): Builder => $query->withTrashed())
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'is_admin',
                'two_factor_confirmed_at',
                'deleted_at',
                'created_at',
            ])
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);

                // MySQL FULLTEXT avoids scanning all 10k+ rows. Very short
                // terms are intentionally kept on LIKE because InnoDB's
                // default minimum token length would otherwise hide results.
                $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $tokens = array_values(array_filter(
                    $tokens,
                    fn (string $token): bool => mb_strlen($token) >= 3,
                ));

                if (DB::connection()->getDriverName() === 'mysql' && $tokens !== []) {
                    // Boolean mode requires every meaningful token and uses a
                    // prefix wildcard, avoiding the broad matches caused by
                    // common email tokens such as "example" and "com".
                    $booleanSearch = implode(' ', array_map(
                        fn (string $token): string => '+'.$token.'*',
                        $tokens,
                    ));
                    $query->whereFullText(['name', 'email'], $booleanSearch, ['mode' => 'boolean']);

                    return;
                }

                $term = '%'.$search.'%';
                $query->where(fn (Builder $query): Builder => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($verification !== UserVerificationStatus::All, function (Builder $query) use ($verification): void {
                $verification === UserVerificationStatus::Verified
                    ? $query->verified()
                    : $query->unverified();
            })
            ->when($role !== UserRole::All, function (Builder $query) use ($role): void {
                $role === UserRole::Administrator
                    ? $query->administrators()
                    : $query->regularUsers();
            })
            ->when($twoFactor !== UserTwoFactorStatus::All, function (Builder $query) use ($twoFactor): void {
                $twoFactor === UserTwoFactorStatus::Enabled
                    ? $query->twoFactorEnabled()
                    : $query->twoFactorDisabled();
            });

        return $query->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();
    }
}
