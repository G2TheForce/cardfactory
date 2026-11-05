<x-layouts.app title="Activity log">
    <x-page-header title="Activity log" description="An audit trail of sign-ins and changes made across the platform." />
    <div class="card overflow-hidden">
        <form method="GET" class="grid grid-cols-1 gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search description or user" class="input-base lg:col-span-2" aria-label="Search">
            <select name="action" class="select-base" aria-label="Action">
                <option value="">All actions</option>
                @foreach ($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach
            </select>
            <div class="flex gap-2 lg:col-span-2">
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input-base" aria-label="From">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input-base" aria-label="To">
                <button class="btn-secondary">Filter</button>
            </div>
            @error('to')<p class="form-error sm:col-span-2 lg:col-span-5">{{ $message }}</p>@enderror
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-gray-500" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td><span class="badge-gray font-mono">{{ $log->action }}</span></td>
                            <td class="max-w-md truncate whitespace-normal">{{ $log->description }}</td>
                            <td class="font-mono text-xs text-gray-500">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="scroll-text" title="No activity recorded" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $logs->links() }}</div>@endif
    </div>
</x-layouts.app>
