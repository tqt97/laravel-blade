<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUsers
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

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
                ->select(['id', 'is_admin'])
                ->whereKey($userIds)
                ->lockForUpdate()
                ->get();

            if ($users->contains(fn (User $user): bool => $user->is($actor))) {
                throw ValidationException::withMessages([
                    'ids' => __('admin.users.errors.cannot_delete_self'),
                ]);
            }

            $selectedAdminCount = $users->filter(fn (User $user): bool => $user->is_admin)->count();
            $remainingAdmins = User::query()
                ->administrators()
                ->lockForUpdate()
                ->count() - $selectedAdminCount;

            if ($remainingAdmins < 1) {
                throw ValidationException::withMessages([
                    'ids' => __('admin.users.errors.last_admin'),
                ]);
            }

            $deletedCount = User::query()->whereKey($users->modelKeys())->delete();

            foreach ($users as $user) {
                $this->auditLogger->log('deleted', $actor, $user, ['bulk' => true]);
            }

            return $deletedCount;
        });
    }
}
