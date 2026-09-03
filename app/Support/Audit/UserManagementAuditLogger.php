<?php

namespace App\Support\Audit;

use App\Models\User;
use App\Models\UserManagementAudit;

class UserManagementAuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?User $actor, ?User $target = null, array $metadata = []): void
    {
        UserManagementAudit::query()->create([
            'actor_id' => $actor?->getKey(),
            'target_user_id' => $target?->getKey(),
            'action' => $action,
            'target_snapshot' => $target?->only(['id', 'name', 'email', 'is_admin', 'deleted_at']),
            'metadata' => $metadata,
        ]);
    }
}
