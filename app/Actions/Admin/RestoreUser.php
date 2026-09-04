<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreUser
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    public function execute(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $user = User::onlyTrashed()->whereKey($user)->lockForUpdate()->firstOrFail();
            try {
                $user->restore();
            } catch (UniqueConstraintViolationException $exception) {
                throw ValidationException::withMessages([
                    'email' => __('admin.users.errors.restore_email_conflict'),
                ]);
            }
            // Keep the audit trail for lifecycle transitions, not only deletes.
            $this->auditLogger->log('restored', $actor, $user);
        }, 3);
    }
}
