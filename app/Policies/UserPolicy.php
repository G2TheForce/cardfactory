<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        // Only a Super Admin may edit a Super Admin (handled by Gate::before).
        return $actor->can('users.manage') && ! $target->hasRole(User::ROLE_SUPER_ADMIN);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.manage')
            && ! $actor->is($target)
            && ! $target->hasRole(User::ROLE_SUPER_ADMIN);
    }
}
