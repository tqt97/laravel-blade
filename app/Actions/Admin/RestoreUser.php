<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;

class RestoreUser
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    public function execute(User $user, ?User $actor = null): void
    {
        $user->restore();
        // Keep the audit trail for lifecycle transitions, not only deletes.
        $this->auditLogger->log('restored', $actor, $user);
    }
}
