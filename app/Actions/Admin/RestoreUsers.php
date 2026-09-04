<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $users = User::onlyTrashed()
                ->select(['id', 'name', 'email', 'is_admin', 'deleted_at'])
                ->whereKey($userIds)
                ->lockForUpdate()
                ->get();

            if ($users->count() !== count(array_unique($userIds))) {
                throw ValidationException::withMessages([
                    'ids' => __('admin.users.errors.invalid_bulk_state'),
                ]);
            }

            try {
                $restoredCount = User::onlyTrashed()->whereKey($users->modelKeys())->restore();
            } catch (UniqueConstraintViolationException $exception) {
                throw ValidationException::withMessages([
                    'ids' => __('admin.users.errors.restore_email_conflict'),
                ]);
            }

            $this->auditLogger->logMany('restored', $actor, $users);

            return $restoredCount;
        }, 3);
    }
}
