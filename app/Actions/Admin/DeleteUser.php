<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Admin\LastAdministratorGuard;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __construct(
        private readonly UserManagementAuditLogger $auditLogger,
        private readonly LastAdministratorGuard $lastAdministratorGuard,
    ) {}

    public function execute(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $user = User::query()->whereKey($user)->lockForUpdate()->firstOrFail();

            if ($user->is($actor)) {
                throw ValidationException::withMessages([
                    'user' => __('admin.users.errors.cannot_delete_self'),
                ]);
            }

            if ($user->is_admin) {
                $this->lastAdministratorGuard->ensureAdministratorRemains(
                    administratorsBeingRemoved: 1,
                    includeTrashed: false,
                    errorKey: 'user',
                );
            }

            $user->delete();
            // SoftDeletes keeps the row available for restore and lets the audit
            // record retain the original target identity.
            $this->auditLogger->log('deleted', $actor, $user);
        }, 3);
    }
}
