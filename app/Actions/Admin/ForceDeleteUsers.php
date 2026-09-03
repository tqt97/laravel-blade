<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForceDeleteUsers
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    /**
     * @param  array<int, int>  $userIds
     */
    public function execute(User $actor, array $userIds): int
    {
        return DB::transaction(function () use ($actor, $userIds): int {
            // A force delete is irreversible. Lock the trashed targets and
            // the administrator set in one transaction so concurrent bulk
            // operations cannot violate the minimum-admin invariant.
            $users = User::onlyTrashed()->select(['id', 'is_admin'])->whereKey($userIds)->lockForUpdate()->get();

            if ($users->contains(fn (User $user): bool => $user->is($actor))) {
                throw ValidationException::withMessages(['ids' => __('admin.users.errors.cannot_delete_self')]);
            }

            $selectedAdminCount = $users->filter(fn (User $user): bool => $user->is_admin)->count();
            $remainingAdmins = User::withTrashed()->administrators()->lockForUpdate()->count() - $selectedAdminCount;

            if ($remainingAdmins < 1) {
                throw ValidationException::withMessages(['ids' => __('admin.users.errors.last_admin')]);
            }

            foreach ($users as $user) {
                $this->auditLogger->log('force_deleted', $actor, $user, ['bulk' => true]);
            }

            return User::onlyTrashed()->whereKey($users->modelKeys())->forceDelete();
        });
    }
}
