<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, User $managedUser): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, User $managedUser): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, User $managedUser): bool
    {
        if (! $user->is_admin || $user->is($managedUser)) {
            return false;
        }

        return true;
    }

    public function restore(User $user, User $managedUser): bool
    {
        return $user->is_admin;
    }

    public function forceDelete(User $user, User $managedUser): bool
    {
        if (! $user->is_admin || $user->is($managedUser)) {
            return false;
        }

        return true;
    }
}
