<?php

namespace App\Policies;

use App\Models\CardDesign;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see and edit a card design:
 * - its owner, and the customer account the design belongs to;
 * - staff with designs.manage (any design) or designs.view-any (read only);
 * - designers with designs.assigned, for designs assigned to them.
 */
class CardDesignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CardDesign $design): bool
    {
        return $this->update($user, $design) || $user->can('designs.view-any');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CardDesign $design): bool
    {
        return $design->user_id === $user->id
            || $user->can('designs.manage')
            || ($user->can('designs.assigned') && $design->designer_id === $user->id)
            || ($design->customer_id && $design->customer?->user_id === $user->id);
    }

    public function delete(User $user, CardDesign $design): bool
    {
        return $design->user_id === $user->id || $user->can('designs.manage');
    }

    /** Assign the design to any customer (otherwise only to customers the user may access). */
    public function assignDesigner(User $user): bool
    {
        return $user->can('designs.manage');
    }

    /** Customers a user may attach to a design. */
    public static function attachableCustomers(User $user): Builder
    {
        $query = Customer::query();
        if ($user->can('customers.view-any') || $user->can('designs.manage')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('user_id', $user->id)
            ->orWhere('assigned_designer_id', $user->id)
            ->orWhere('created_by', $user->id));
    }

    /** Designs visible to the user in listings. */
    public static function visibleTo(User $user): Builder
    {
        $query = CardDesign::query();
        if ($user->can('designs.view-any') || $user->can('designs.manage')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('user_id', $user->id)
            ->when($user->can('designs.assigned'), fn ($q) => $q->orWhere('designer_id', $user->id))
            ->orWhereHas('customer', fn ($c) => $c->where('user_id', $user->id)));
    }
}
