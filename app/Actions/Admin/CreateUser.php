<?php

namespace App\Actions\Admin;

use App\DTO\AdminUserData;
use App\Models\User;
use App\Support\Audit\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateUser
{
    public function __construct(private readonly UserManagementAuditLogger $auditLogger) {}

    /**
     * Create an admin-managed user.
     */
    public function execute(AdminUserData $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $attributes = $data->toArray(includeEmptyPassword: true);
            $attributes['password'] = Hash::make($data->password ?? '');

            $user = User::create($attributes);
            $this->auditLogger->log('created', $actor, $user);

            return $user;
        }, 3);
    }
}
