<?php

namespace App\Actions\Admin;

use App\DTO\AdminUserData;
use App\Models\User;
use App\Support\Admin\LastAdministratorGuard;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function __construct(
        private readonly UserManagementAuditLogger $auditLogger,
        private readonly LastAdministratorGuard $lastAdministratorGuard,
    ) {}

    /**
     * Update an admin-managed user.
     */
    public function execute(User $user, AdminUserData $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            // Re-read and lock the target so concurrent role changes cannot
            // bypass the minimum-administrator invariant.
            $user = User::query()->whereKey($user)->lockForUpdate()->firstOrFail();
            $isDemotingAdministrator = $user->is_admin && ! $data->isAdmin;

            if ($isDemotingAdministrator) {
                if ($user->is($actor)) {
                    throw ValidationException::withMessages([
                        'is_admin' => __('admin.users.errors.cannot_demote_self'),
                    ]);
                }

                $this->lastAdministratorGuard->ensureAdministratorRemains(
                    administratorsBeingRemoved: 1,
                    includeTrashed: false,
                    messageKey: 'admin.users.errors.cannot_demote_last_admin',
                );
            }

            $attributes = $data->toArray();

            if (filled($data->password)) {
                $attributes['password'] = Hash::make($data->password);
            } else {
                unset($attributes['password']);
            }

            $user->update($attributes);

            // The update query may have changed timestamps/casts and model
            // events can mutate attributes. Refresh gives the caller a snapshot
            // that matches the database instead of the stale in-memory object.
            $user->refresh();
            $this->auditLogger->log('updated', $actor, $user);

            return $user;
        }, 3);
    }
}
