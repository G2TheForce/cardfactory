<x-layouts.app :title="'Order '.$order->order_number">
    <x-slot:breadcrumbs>
        <a href="{{ route('orders.index') }}" class="hover:text-gray-700">Orders</a><span>/</span><span class="text-gray-900">{{ $order->order_number }}</span>
    </x-slot:breadcrumbs>

    <x-page-header :title="$order->order_number" :description="'Created '.$order->created_at->toFormattedDateString().($order->creator ? ' by '.$order->creator->name : '')">
        <x-slot:actions>
            @include('orders._status', ['status' => $order->status])
            <a href="{{ route('orders.edit', $order) }}" class="btn-secondary"><x-lucide-pencil class="h-4 w-4" /> Edit</a>
            @unless (in_array($order->status, \App\Models\CardOrder::REVENUE_STATUSES, true))
                <form method="POST" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('Delete this order? Cards on it go back into stock.')">@csrf @method('DELETE')<button class="btn-secondary"><x-lucide-trash-2 class="h-4 w-4" /> Delete</button></form>
            @endunless
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Items</h2></div>
                <table class="table">
                    <thead><tr><th>Product</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($order->items ?? [] as $item)
                            <tr>
                                <td><span class="font-mono text-xs text-gray-500">{{ $item['sku'] }}</span><span class="block text-sm text-gray-900">{{ $item['name'] }}</span></td>
                                <td class="text-right text-sm">{{ $item['qty'] }}</td>
                                <td class="text-right text-sm">{{ Illuminate\Support\Number::currency($item['unit_price'], $order->currency) }}</td>
                                <td class="text-right text-sm">{{ Illuminate\Support\Number::currency($item['line_total'] ?? $item['unit_price'] * $item['qty'], $order->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="text-sm">
                        <tr><td colspan="3" class="text-right text-gray-500">Subtotal</td><td class="text-right">{{ Illuminate\Support\Number::currency((float) $order->subtotal, $order->currency) }}</td></tr>
                        <tr><td colspan="3" class="text-right text-gray-500">Tax</td><td class="text-right">{{ Illuminate\Support\Number::currency((float) $order->tax, $order->currency) }}</td></tr>
                        <tr><td colspan="3" class="text-right font-semibold">Total</td><td class="text-right font-semibold">{{ Illuminate\Support\Number::currency((float) $order->total, $order->currency) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            <div class="card overflow-hidden">
                <div class="card-header"><div><h2 class="font-semibold text-gray-900">NFC cards on this order</h2><p class="text-sm text-gray-500">{{ $order->nfcCards->count() }} of {{ $order->quantity }} added. Cards from stock are marked sold.</p></div></div>
                @if ($order->nfcCards->isNotEmpty())
                    <table class="table">
                        <thead><tr><th>Card</th><th>Status</th><th>Profile</th><th></th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($order->nfcCards as $card)
                                <tr>
                                    <td>@can('nfc.manage')<a href="{{ route('nfc.show', $card) }}" class="font-mono text-sm text-brand-700 hover:underline">{{ $card->card_identifier }}</a>@else<span class="font-mono text-sm">{{ $card->card_identifier }}</span>@endcan<span class="block font-mono text-xs text-gray-500">{{ $card->uid }}</span></td>
                                    <td>@include('nfc._status', ['status' => $card->status])</td>
                                    <td class="text-xs text-gray-500">{{ $card->currentAssignment?->digitalProfile ? '/card/'.$card->currentAssignment->digitalProfile->slug : '—' }}</td>
                                    <td class="text-right"><form method="POST" action="{{ route('orders.cards.detach', [$order, $card]) }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm" aria-label="Remove {{ $card->card_identifier }}"><x-lucide-x class="h-4 w-4" /></button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <form method="POST" action="{{ route('orders.cards.attach', $order) }}" class="flex flex-wrap items-end gap-3 border-t border-gray-100 p-5">
                    @csrf
                    <div class="min-w-64 flex-1">
                        <label for="identifiers" class="form-label">Add cards by ID</label>
                        <textarea id="identifiers" name="identifiers" rows="2" class="input-base font-mono text-sm" placeholder="SC-7K2M9QXA, SC-8P3N4R5T">{{ old('identifiers') }}</textarea>
                        @error('identifiers')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn-secondary">Add cards</button>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Customer</h2></div>
                <div class="card-body text-sm">
                    @if ($order->customer)
                        <a href="{{ route('customers.show', $order->customer) }}" class="font-semibold text-gray-900 hover:text-brand-700">{{ $order->customer->full_name }}</a>
                        <p class="text-gray-500">{{ $order->customer->email }}</p>
                    @else<p class="text-gray-500">No customer (stock sale).</p>@endif
                    @if ($order->paid_at)<p class="mt-3 text-gray-600">Paid {{ $order->paid_at->toFormattedDateString() }}</p>@endif
                    @if ($order->notes)<p class="mt-3 whitespace-pre-line text-gray-600">{{ $order->notes }}</p>@endif
                </div>
            </div>
            <form method="POST" action="{{ route('orders.status', $order) }}" class="card">
                @csrf @method('PATCH')
                <div class="card-header"><h2 class="font-semibold text-gray-900">Update status</h2></div>
                <div class="card-body flex gap-2">
                    <select name="status" class="select-base" aria-label="Order status">@foreach (\App\Models\CardOrder::STATUSES as $s)<option value="{{ $s }}" @selected($order->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                    <button class="btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
