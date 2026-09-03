<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;

class DeleteUser
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    public function execute(User $user, ?User $actor = null): void
    {
        $user->delete();
        // SoftDeletes keeps the row available for restore and lets the audit
        // record retain the original target identity.
        $this->auditLogger->log('deleted', $actor, $user);
    }
}
