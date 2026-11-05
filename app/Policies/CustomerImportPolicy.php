<?php

namespace App\Policies;

use App\Models\CustomerImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bulk generation needs imports.manage. Imports hold personal data, so each
 * one is visible to the person who uploaded it and to administrators
 * (users.manage); Super Admin passes everything.
 */
class CustomerImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('imports.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('imports.manage');
    }

    public function view(User $user, CustomerImport $import): bool
    {
        return $user->can('imports.manage') && ((int) $import->user_id === (int) $user->id || $user->can('users.manage'));
    }

    public function update(User $user, CustomerImport $import): bool
    {
        return $this->view($user, $import);
    }

    public function delete(User $user, CustomerImport $import): bool
    {
        return $this->view($user, $import);
    }

    public static function visibleTo(User $user): Builder
    {
        return CustomerImport::query()->when(! $user->can('users.manage'), fn ($q) => $q->where('user_id', $user->id));
    }
}
