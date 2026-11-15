<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\LandingPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * landing-pages.manage and customers.manage build pages for any customer;
 * customers.view-any sees them all. Designers see (not edit) pages of
 * customers assigned to them. A customer account sees and edits only its own.
 */
class LandingPagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LandingPage $page): bool
    {
        $customer = $page->customer()->withTrashed()->first();

        return $this->staffView($user) || ($customer && ((int) $customer->user_id === (int) $user->id || (int) $customer->assigned_designer_id === (int) $user->id));
    }

    /** Creating a page in general; the customer is checked with createFor. */
    public function create(User $user): bool
    {
        return $this->staffEdit($user) || $user->hasRole(User::ROLE_CUSTOMER);
    }

    public function createFor(User $user, Customer $customer): bool
    {
        return $this->staffEdit($user) || (int) $customer->user_id === (int) $user->id;
    }

    public function update(User $user, LandingPage $page): bool
    {
        return $this->staffEdit($user) || $this->owns($user, $page);
    }

    public function delete(User $user, LandingPage $page): bool
    {
        return $this->update($user, $page);
    }

    public static function visibleTo(User $user): Builder
    {
        $query = LandingPage::query();
        if ((new self)->staffView($user)) {
            return $query;
        }

        return $query->whereHas('customer', fn ($q) => $q->where('user_id', $user->id)->orWhere('assigned_designer_id', $user->id));
    }

    private function owns(User $user, LandingPage $page): bool
    {
        return (int) $page->customer()->withTrashed()->value('user_id') === (int) $user->id;
    }

    private function staffView(User $user): bool
    {
        return $user->can('landing-pages.manage') || $user->can('customers.view-any');
    }

    private function staffEdit(User $user): bool
    {
        return $user->can('landing-pages.manage') || $user->can('customers.manage');
    }
}
