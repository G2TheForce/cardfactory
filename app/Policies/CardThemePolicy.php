<?php

namespace App\Policies;

use App\Models\CardTheme;
use App\Models\User;
use App\Services\ThemeManagementService;

class CardThemePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CardTheme $theme): bool
    {
        // A private theme is a user's own customisation: only its owner sees it.
        return $theme->status === CardTheme::STATUS_PUBLISHED
            || $theme->created_by === $user->id
            || ($user->can('themes.manage') && $theme->status !== ThemeManagementService::STATUS_PRIVATE);
    }

    /** Create from scratch (admin theme editor). */
    public function create(User $user): bool
    {
        return $user->can('themes.manage');
    }

    public function update(User $user, CardTheme $theme): bool
    {
        if ($this->ownsPrivate($user, $theme)) {
            return true;
        }

        return $user->can('themes.manage') && $theme->status !== ThemeManagementService::STATUS_PRIVATE;
    }

    /** Customise / save as new / duplicate / favourite: anyone who can see the theme. */
    public function use(User $user, CardTheme $theme): bool
    {
        return $this->view($user, $theme);
    }

    public function reset(User $user, CardTheme $theme): bool
    {
        return $theme->parent_id !== null && $this->update($user, $theme);
    }

    public function publish(User $user, CardTheme $theme): bool
    {
        return $user->can('themes.publish');
    }

    public function delete(User $user, CardTheme $theme): bool
    {
        return $this->ownsPrivate($user, $theme)
            || ($user->can('themes.publish') && $theme->status !== ThemeManagementService::STATUS_PRIVATE);
    }

    private function ownsPrivate(User $user, CardTheme $theme): bool
    {
        return $theme->created_by === $user->id && $theme->status === ThemeManagementService::STATUS_PRIVATE;
    }
}
