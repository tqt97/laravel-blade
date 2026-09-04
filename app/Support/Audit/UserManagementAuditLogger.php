<?php

namespace App\Support\Audit;

use App\Models\User;
use App\Models\UserManagementAudit;
use Illuminate\Support\Facades\Log;

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

        Log::channel(config('audit.monitoring.log_channel', 'stack'))->info('user_management.audit_recorded', [
            'action' => $action,
            'actor_id' => $actor?->getKey(),
            'target_user_id' => $target?->getKey(),
        ]);
    }

    /**
     * @param  iterable<User>  $targets
     * @param  array<string, mixed>  $metadata
     */
    public function logMany(string $action, ?User $actor, iterable $targets, array $metadata = []): void
    {
        $now = now();
        $rows = [];

        foreach ($targets as $target) {
            $rows[] = [
                'actor_id' => $actor?->getKey(),
                'target_user_id' => $target->getKey(),
                'action' => $action,
                'target_snapshot' => json_encode($target->only(['id', 'name', 'email', 'is_admin', 'deleted_at']), JSON_THROW_ON_ERROR),
                'metadata' => json_encode($metadata + ['bulk' => true], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            UserManagementAudit::query()->insert($rows);

            Log::channel(config('audit.monitoring.log_channel', 'stack'))->info('user_management.bulk_audit_recorded', [
                'action' => $action,
                'actor_id' => $actor?->getKey(),
                'count' => count($rows),
            ]);
        }
    }
}
