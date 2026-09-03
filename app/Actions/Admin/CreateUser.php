<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\Hash;

class CreateUser
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    /**
     * Create an admin-managed user.
     *
     * @param  array{name: string, email: string, password: string, is_admin: bool}  $data
     */
    public function execute(array $data, ?User $actor = null): User
    {
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $this->auditLogger->log('created', $actor, $user);

        return $user;
    }
}
