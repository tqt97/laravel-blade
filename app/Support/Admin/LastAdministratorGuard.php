<?php

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class LastAdministratorGuard
{
    /**
     * The caller must already be inside a transaction. Locking the full admin
     * set makes every user-management mutation use the same invariant boundary.
     */
    public function ensureAdministratorRemains(
        int $administratorsBeingRemoved,
        bool $includeTrashed,
        string $messageKey = 'admin.users.errors.last_admin',
        string $errorKey = 'is_admin',
    ): void {
        $query = User::query();

        if ($includeTrashed) {
            $query->withTrashed();
        }

        $remaining = $query->administrators()->lockForUpdate()->count() - $administratorsBeingRemoved;

        if ($remaining < 1) {
            throw ValidationException::withMessages([
                $errorKey => __($messageKey),
            ]);
        }
    }
}
