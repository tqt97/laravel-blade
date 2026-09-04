<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Admin\LastAdministratorGuard;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForceDeleteUser
{
    public function __construct(
        private readonly UserManagementAuditLogger $auditLogger,
        private readonly LastAdministratorGuard $lastAdministratorGuard,
    ) {}

    public function execute(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $user = User::onlyTrashed()->whereKey($user)->lockForUpdate()->firstOrFail();

            if ($user->is($actor)) {
                throw ValidationException::withMessages([
                    'user' => __('admin.users.errors.cannot_delete_self'),
                ]);
            }

            if ($user->is_admin) {
                $this->lastAdministratorGuard->ensureAdministratorRemains(
                    administratorsBeingRemoved: 1,
                    includeTrashed: true,
                    errorKey: 'user',
                );
            }

            // Record the snapshot before deletion because the target row may no
            // longer exist after forceDelete(). The audit FK is nullable so the
            // audit history survives the irreversible operation.
            $this->auditLogger->log('force_deleted', $actor, $user);
            $user->forceDelete();
        }, 3);
    }
}
