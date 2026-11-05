<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff with customers.view-any see every customer; customers.manage edits them.
 * Designers see customers assigned to them. A customer account sees and edits
 * only its own record (and cannot change status, owner or designer).
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view-any') || $user->can('designs.assigned') || $user->can('designs.manage');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view-any')
            || $customer->user_id === $user->id
            || $customer->assigned_designer_id === $user->id
            || ($user->can('designs.manage'));
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage') || $customer->user_id === $user->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage') && $customer->user_id !== $user->id;
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage');
    }

    public static function visibleTo(User $user): Builder
    {
        $query = Customer::query();
        if ($user->can('customers.view-any') || $user->can('designs.manage')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('assigned_designer_id', $user->id)->orWhere('user_id', $user->id));
    }
}
