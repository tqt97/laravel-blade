<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Admin\LastAdministratorGuard;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForceDeleteUsers
{
    public function __construct(
        private readonly UserManagementAuditLogger $auditLogger,
        private readonly LastAdministratorGuard $lastAdministratorGuard,
    ) {}

    /**
     * @param  array<int, int>  $userIds
     */
    public function execute(User $actor, array $userIds): int
    {
        return DB::transaction(function () use ($actor, $userIds): int {
            // A force delete is irreversible. Lock the trashed targets and
            // the administrator set in one transaction so concurrent bulk
            // operations cannot violate the minimum-admin invariant.
            $users = User::onlyTrashed()
                ->select(['id', 'name', 'email', 'is_admin', 'deleted_at'])
                ->whereKey($userIds)
                ->lockForUpdate()
                ->get();

            if ($users->count() !== count(array_unique($userIds))) {
                throw ValidationException::withMessages([
                    'ids' => __('admin.users.errors.invalid_bulk_state'),
                ]);
            }

            if ($users->contains(fn (User $user): bool => $user->is($actor))) {
                throw ValidationException::withMessages(['ids' => __('admin.users.errors.cannot_delete_self')]);
            }

            $selectedAdminCount = $users->filter(fn (User $user): bool => $user->is_admin)->count();
            if ($selectedAdminCount > 0) {
                $this->lastAdministratorGuard->ensureAdministratorRemains(
                    administratorsBeingRemoved: $selectedAdminCount,
                    includeTrashed: true,
                    errorKey: 'ids',
                );
            }

            $this->auditLogger->logMany('force_deleted', $actor, $users);

            return User::onlyTrashed()->whereKey($users->modelKeys())->forceDelete();
        }, 3);
    }
}
