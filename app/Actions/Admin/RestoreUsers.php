<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;

class RestoreUsers
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    /**
     * @param  array<int, int>  $userIds
     */
    public function execute(User $actor, array $userIds): int
    {
        return DB::transaction(function () use ($actor, $userIds): int {
            // Lock the trashed rows so a restore cannot race with a force
            // delete or another restore request for the same IDs.
            $users = User::onlyTrashed()->whereKey($userIds)->lockForUpdate()->get();
            $restoredCount = User::onlyTrashed()->whereKey($users->modelKeys())->restore();

            foreach ($users as $user) {
                $this->auditLogger->log('restored', $actor, $user, ['bulk' => true]);
            }

            return $restoredCount;
        });
    }
}
