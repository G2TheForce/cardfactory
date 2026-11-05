<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Builds the sidebar for the current user. Items only appear when their route
 * exists (modules are added phase by phase) and the user is authorised.
 */
class Navigation
{
    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, icon: string, active: string}>}>
     */
    public static function for(User $user): array
    {
        $sections = [
            ['label' => null, 'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'active' => 'dashboard'],
            ]],
            ['label' => 'Design', 'items' => [
                ['label' => 'Card designs', 'route' => 'designs.index', 'icon' => 'pen-tool', 'active' => 'designs.*'],
                ['label' => 'Exports', 'route' => 'exports.index', 'icon' => 'download', 'active' => 'exports.*'],
                ['label' => 'Themes', 'route' => 'themes.index', 'icon' => 'palette', 'active' => 'themes.*'],
                ['label' => 'Brand kits', 'route' => 'brand-kits.index', 'icon' => 'swatch-book', 'active' => 'brand-kits.*'],
            ]],
            ['label' => 'Customers', 'items' => [
                ['label' => 'Customers', 'route' => 'customers.index', 'icon' => 'contact', 'active' => 'customers.*', 'policy' => ['viewAny', Customer::class]],
                ['label' => 'Digital profiles', 'route' => 'profiles.index', 'icon' => 'smartphone', 'active' => 'profiles.*'],
                ['label' => 'Bulk generator', 'route' => 'imports.index', 'icon' => 'layers', 'active' => 'imports.*', 'can' => 'imports.manage'],
            ]],
            ['label' => 'NFC & sales', 'items' => [
                ['label' => 'NFC cards', 'route' => 'nfc.index', 'icon' => 'nfc', 'active' => 'nfc.*', 'can' => 'nfc.manage'],
                ['label' => 'Inventory', 'route' => 'inventory.index', 'icon' => 'package', 'active' => 'inventory.*', 'can' => 'inventory.manage'],
                ['label' => 'Orders', 'route' => 'orders.index', 'icon' => 'shopping-bag', 'active' => 'orders.*', 'can' => 'orders.manage'],
                ['label' => 'Analytics', 'route' => 'analytics.index', 'icon' => 'chart-line', 'active' => 'analytics.*'],
            ]],
            ['label' => 'Administration', 'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => 'admin.users.*', 'can' => 'users.manage'],
                ['label' => 'Roles & permissions', 'route' => 'admin.roles.index', 'icon' => 'shield-check', 'active' => 'admin.roles.*', 'can' => 'roles.manage'],
                ['label' => 'Activity log', 'route' => 'admin.activity.index', 'icon' => 'scroll-text', 'active' => 'admin.activity.*', 'can' => 'activity.view'],
            ]],
            ['label' => 'Account', 'items' => [
                ['label' => 'My card details', 'route' => 'my-details', 'icon' => 'id-card', 'active' => 'customers.edit', 'role' => 'customer'],
                ['label' => 'Settings', 'route' => 'settings.profile', 'icon' => 'settings', 'active' => 'settings.*'],
            ]],
        ];

        return collect($sections)
            ->map(function (array $section) use ($user) {
                $section['items'] = array_values(array_filter($section['items'], fn (array $item) => Route::has($item['route'])
                    && (! isset($item['can']) || $user->can($item['can']))
                    && (! isset($item['policy']) || $user->can(...$item['policy']))
                    && (! isset($item['role']) || $user->hasRole($item['role']))));

                return $section;
            })
            ->filter(fn (array $section) => count($section['items']) > 0)
            ->values()
            ->all();
    }
}
