@php
    $statusBadges = ['active' => 'badge-green', 'inactive' => 'badge-gray', 'archived' => 'badge-amber'];
    $deleted = ($filters['status'] ?? null) === 'deleted';
@endphp
<x-layouts.app title="Customers">
    <x-page-header title="Customers" description="The people whose details appear on cards and digital profiles.">
        <x-slot:actions>
            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}" class="btn-primary"><x-lucide-user-plus class="h-4 w-4" /> Add customer</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Customers" :value="number_format($counts['total'])" icon="contact" />
        <x-stat-card label="Active" :value="number_format($counts['active'])" icon="circle-check" tone="emerald" />
        <x-stat-card label="With a login" :value="number_format($counts['with_login'])" icon="key-round" tone="violet" />
        <x-stat-card label="New in 30 days" :value="number_format($counts['new'])" icon="sparkles" tone="amber" />
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, phone, title or company" class="input-base pl-9" aria-label="Search customers">
            </div>
            <select name="status" class="select-base lg:w-36" aria-label="Status">
                <option value="">Any status</option>
                @foreach (\App\Support\CustomerFields::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
                @can('customers.manage')<option value="deleted" @selected($deleted)>Deleted</option>@endcan
            </select>
            @if ($designers->isNotEmpty())
                <select name="designer" class="select-base lg:w-44" aria-label="Designer">
                    <option value="">Any designer</option>
                    @foreach ($designers as $designer)
                        <option value="{{ $designer->ulid }}" @selected(($filters['designer'] ?? '') === $designer->ulid)>{{ $designer->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="sort" class="select-base lg:w-40" aria-label="Sort">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option>
                <option value="updated" @selected(($filters['sort'] ?? '') === 'updated')>Recently updated</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name A–Z</option>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Customer</th><th>Company</th><th>Contact</th><th>Designs</th><th>Status</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr>
                            <td>
                                <a href="{{ $deleted ? '#' : route('customers.show', $customer) }}" class="flex items-center gap-3">
                                    <x-customer-avatar :customer="$customer" size="sm" />
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-900">{{ $customer->full_name }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $customer->professional_title ?: '—' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="text-gray-600">{{ $customer->company?->name ?? '—' }}</td>
                            <td class="text-xs text-gray-600">
                                <span class="block">{{ $customer->email ?? '—' }}</span>
                                <span class="block text-gray-400">{{ $customer->mobile }}</span>
                            </td>
                            <td class="text-gray-600">{{ $customer->card_designs_count }}</td>
                            <td>
                                @if ($deleted)<span class="badge-red">Deleted</span>@else<span class="{{ $statusBadges[$customer->status] ?? 'badge-gray' }}">{{ \App\Support\CustomerFields::STATUSES[$customer->status] ?? $customer->status }}</span>@endif
                                @if ($customer->user_id)<span class="badge-brand" title="Has a login"><x-lucide-key-round class="h-3 w-3" /></span>@endif
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($deleted)
                                        <form method="POST" action="{{ route('customers.restore', $customer->ulid) }}">@csrf
                                            <button class="btn-ghost btn-sm"><x-lucide-rotate-ccw class="h-4 w-4" /> Restore</button>
                                        </form>
                                    @else
                                        <a href="{{ route('customers.show', $customer) }}" class="btn-ghost btn-sm" aria-label="View {{ $customer->full_name }}"><x-lucide-eye class="h-4 w-4" /></a>
                                        @can('update', $customer)
                                            <a href="{{ route('customers.edit', $customer) }}" class="btn-ghost btn-sm" aria-label="Edit {{ $customer->full_name }}"><x-lucide-pencil class="h-4 w-4" /></a>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="contact" title="No customers found" description="Try a different search or filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $customers->links() }}</div>@endif
    </div>
</x-layouts.app>
