<?php

namespace App\Policies;

use App\Models\BrandKit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Brand kits belong to the user who made them; designs.manage sees and edits them all. */
class BrandKitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, BrandKit $kit): bool
    {
        return $this->update($user, $kit);
    }

    public function update(User $user, BrandKit $kit): bool
    {
        return $kit->user_id === $user->id || $user->can('designs.manage');
    }

    public function delete(User $user, BrandKit $kit): bool
    {
        return $this->update($user, $kit);
    }

    public static function visibleTo(User $user): Builder
    {
        return $user->can('designs.manage') ? BrandKit::query() : BrandKit::where('user_id', $user->id);
    }
}
