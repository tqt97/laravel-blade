<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;

class ForceDeleteUser
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    public function execute(User $user, ?User $actor = null): void
    {
        // Record the snapshot before deletion because the target row may no
        // longer exist after forceDelete(). The audit FK is nullable so the
        // audit history survives the irreversible operation.
        $this->auditLogger->log('force_deleted', $actor, $user);
        $user->forceDelete();
    }
}
