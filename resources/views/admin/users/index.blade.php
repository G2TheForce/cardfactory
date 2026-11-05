<x-layouts.app title="Users">
    <x-page-header title="Users" description="Manage staff and customer accounts and their roles.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn-primary"><x-lucide-user-plus class="h-4 w-4" /> Add user</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="All accounts" :value="number_format($counts['total'])" icon="users" />
        <x-stat-card label="Active" :value="number_format($counts['active'])" icon="circle-check" tone="emerald" />
        <x-stat-card label="Staff" :value="number_format($counts['staff'])" icon="shield-check" tone="violet" />
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email" class="input-base pl-9" aria-label="Search users">
            </div>
            <select name="role" class="select-base sm:w-44" aria-label="Filter by role">
                <option value="">All roles</option>
                @foreach ($roles as $key => $role)
                    <option value="{{ $key }}" @selected(($filters['role'] ?? '') === $key)>{{ $role['label'] }}</option>
                @endforeach
            </select>
            <select name="status" class="select-base sm:w-36" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last sign-in</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :user="$user" size="sm" />
                                    <div><p class="font-medium text-gray-900">{{ $user->name }}</p><p class="text-xs text-gray-500">{{ $user->email }}</p></div>
                                </div>
                            </td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <span class="badge-brand">{{ config("smartcard.roles.{$role->name}.label", $role->name) }}</span>
                                @endforeach
                            </td>
                            <td>@if ($user->is_active)<span class="badge-green">Active</span>@else<span class="badge-red">Inactive</span>@endif</td>
                            <td class="text-gray-500">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @can('update', $user)
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn-ghost btn-sm" aria-label="Edit {{ $user->name }}"><x-lucide-pencil class="h-4 w-4" /></a>
                                    @endcan
                                    @can('delete', $user)
                                        @unless ($user->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" x-data @submit="if (! confirm('Remove {{ e($user->name) }}? Their designs and history are kept.')) $event.preventDefault()">
                                                @csrf @method('DELETE')
                                                <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50 hover:text-red-700" aria-label="Remove {{ $user->name }}"><x-lucide-trash-2 class="h-4 w-4" /></button>
                                            </form>
                                        @endunless
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="users" title="No users match these filters" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $users->links() }}</div>@endif
    </div>
</x-layouts.app>
