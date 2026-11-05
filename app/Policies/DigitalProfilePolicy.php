<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * profiles.manage and customers.view-any see every profile; profiles.manage and
 * customers.manage edit them. Designers see (not edit) profiles of customers
 * assigned to them. A customer account sees and edits only its own profiles.
 * Linking physical NFC cards needs nfc.manage.
 */
class DigitalProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DigitalProfile $profile): bool
    {
        $customer = $profile->customer()->withTrashed()->first();

        return $this->staffView($user) || ($customer && ((int) $customer->user_id === (int) $user->id || (int) $customer->assigned_designer_id === (int) $user->id));
    }

    /** Creating a profile in general; the customer is checked with createFor. */
    public function create(User $user): bool
    {
        return $this->staffEdit($user) || $user->hasRole('customer');
    }

    public function createFor(User $user, Customer $customer): bool
    {
        return $this->staffEdit($user) || (int) $customer->user_id === (int) $user->id;
    }

    public function update(User $user, DigitalProfile $profile): bool
    {
        return $this->staffEdit($user) || $this->owns($user, $profile);
    }

    public function delete(User $user, DigitalProfile $profile): bool
    {
        return $this->update($user, $profile);
    }

    public function manageNfc(User $user, DigitalProfile $profile): bool
    {
        return $user->can('nfc.manage');
    }

    public static function visibleTo(User $user): Builder
    {
        $query = DigitalProfile::query();
        if ($user->can('profiles.manage') || $user->can('customers.view-any')) {
            return $query;
        }

        return $query->whereHas('customer', fn ($q) => $q->where('user_id', $user->id)->orWhere('assigned_designer_id', $user->id));
    }

    private function owns(User $user, DigitalProfile $profile): bool
    {
        return (int) $profile->customer()->withTrashed()->value('user_id') === (int) $user->id;
    }

    private function staffView(User $user): bool
    {
        return $user->can('profiles.manage') || $user->can('customers.view-any');
    }

    private function staffEdit(User $user): bool
    {
        return $user->can('profiles.manage') || $user->can('customers.manage');
    }
}
