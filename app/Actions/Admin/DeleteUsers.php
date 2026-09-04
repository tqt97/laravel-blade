<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Admin\LastAdministratorGuard;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUsers
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
            // Lock the selected rows before checking invariants. Without the
            // lock, two concurrent admins could both observe the same last
            // administrator and delete/demote it at the same time.
            $users = User::query()
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
                throw ValidationException::withMessages([
                    'ids' => __('admin.users.errors.cannot_delete_self'),
                ]);
            }

            $selectedAdminCount = $users->filter(fn (User $user): bool => $user->is_admin)->count();
            if ($selectedAdminCount > 0) {
                $this->lastAdministratorGuard->ensureAdministratorRemains(
                    administratorsBeingRemoved: $selectedAdminCount,
                    includeTrashed: false,
                    errorKey: 'ids',
                );
            }

            $deletedCount = User::query()->whereKey($users->modelKeys())->delete();

            $this->auditLogger->logMany('deleted', $actor, $users);

            return $deletedCount;
        }, 3);
    }
}
