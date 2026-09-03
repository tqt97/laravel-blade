<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\Hash;

class UpdateUser
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    /**
     * Update an admin-managed user.
     *
     * @param  array{name: string, email: string, password?: string|null, is_admin: bool}  $data
     */
    public function execute(User $user, array $data, ?User $actor = null): User
    {
        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        // The update query may have changed timestamps/casts and model
        // events can mutate attributes. Refresh gives the caller a snapshot
        // that matches the database instead of the stale in-memory object.
        $user->refresh();
        $this->auditLogger->log('updated', $actor, $user);

        return $user;
    }
}
