@php $currency = config('smartcard.currency', 'USD'); @endphp
<x-layouts.app title="Orders">
    <x-page-header title="Orders" description="Card orders, payments and the NFC cards shipped with each one.">
        <x-slot:actions><a href="{{ route('orders.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New order</a></x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Open orders" :value="$totals['open']" icon="clipboard-list" />
        <x-stat-card label="Revenue, last 30 days" :value="Illuminate\Support\Number::currency($totals['revenue_30'], $currency)" icon="banknote" tone="emerald" />
        <x-stat-card label="Cards sold, last 30 days" :value="number_format($totals['cards_30'])" icon="nfc" tone="sky" />
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-4">
            <div class="min-w-48 flex-1"><label for="search" class="form-label">Search</label><input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="input-base" placeholder="Order number or customer"></div>
            <div><label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="select-base"><option value="">Any</option>@foreach (\App\Models\CardOrder::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
            <button class="btn-secondary">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Order</th><th>Customer</th><th class="text-right">Cards</th><th class="text-right">Total</th><th>Status</th><th>Created</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td><a href="{{ route('orders.show', $order) }}" class="font-mono text-sm font-semibold text-gray-900 hover:text-brand-700">{{ $order->order_number }}</a></td>
                            <td class="text-sm text-gray-700">{{ $order->customer?->full_name ?? '—' }}</td>
                            <td class="text-right text-sm">{{ $order->quantity }}</td>
                            <td class="text-right text-sm font-medium">{{ Illuminate\Support\Number::currency((float) $order->total, $order->currency) }}</td>
                            <td>@include('orders._status', ['status' => $order->status])</td>
                            <td class="text-xs text-gray-500">{{ $order->created_at->toFormattedDateString() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="shopping-bag" title="No orders" description="Record card orders to track payments, revenue and which cards went to whom." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $orders->links() }}</div>@endif
    </div>
</x-layouts.app>
