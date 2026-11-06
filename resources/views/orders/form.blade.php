@php
    $editing = $order->exists;
    $prices = $products->mapWithKeys(fn ($p) => [$p->sku => (float) $p->unit_price]);
    $skuById = $products->pluck('sku', 'id');
    $items = old('items', collect($order->items ?? [])->map(fn ($i) => ['product' => $skuById[$i['card_inventory_id'] ?? 0] ?? ($i['sku'] ?? ''), 'qty' => $i['qty'], 'unit_price' => $i['unit_price']])->all() ?: [['product' => $products->first()?->sku, 'qty' => 10, 'unit_price' => '']]);
    $selectedCustomer = old('customer', $customers->firstWhere('id', $order->customer_id)?->ulid);
@endphp
<x-layouts.app :title="$editing ? 'Edit '.$order->order_number : 'New order'">
    <x-slot:breadcrumbs>
        <a href="{{ route('orders.index') }}" class="hover:text-gray-700">Orders</a><span>/</span>@if ($order->exists)<a href="{{ route('orders.show', $order) }}" class="hover:text-gray-700">{{ $order->order_number }}</a><span>/</span><span class="text-gray-900">Edit</span>@else<span class="text-gray-900">New order</span>@endif
    </x-slot:breadcrumbs>

    <x-page-header :title="$editing ? 'Edit '.$order->order_number : 'New order'" description="Prices default to the product price. Totals are worked out on the server." />

    <form method="POST" action="{{ $editing ? route('orders.update', $order) : route('orders.store') }}" class="card max-w-4xl"
          x-data="{ items: @js(array_values($items)), prices: @js($prices), tax: @js((float) old('tax', $order->tax ?? 0)),
                    line(i) { const p = i.unit_price === '' || i.unit_price === null ? (this.prices[i.product] ?? 0) : parseFloat(i.unit_price); return (p || 0) * (parseInt(i.qty) || 0); },
                    get subtotal() { return this.items.reduce((s, i) => s + this.line(i), 0); } }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body space-y-6">
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="customer" class="form-label">Customer</label>
                    <select id="customer" name="customer" class="select-base"><option value="">No customer (stock sale)</option>@foreach ($customers as $c)<option value="{{ $c->ulid }}" @selected($selectedCustomer === $c->ulid)>{{ $c->full_name }}{{ $c->email ? ' · '.$c->email : '' }}</option>@endforeach</select>
                    @error('customer')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="select-base">@foreach (\App\Models\CardOrder::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $order->status) === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                </div>
            </div>

            <div>
                <p class="form-label">Items</p>
                @error('items')<p class="form-error">{{ $message }}</p>@enderror
                <div class="space-y-2">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid grid-cols-12 items-center gap-2">
                            <select class="select-base col-span-6" :name="`items[${index}][product]`" x-model="item.product" aria-label="Product">
                                @foreach ($products as $p)<option value="{{ $p->sku }}">{{ $p->sku }} · {{ $p->name }}</option>@endforeach
                            </select>
                            <input type="number" min="1" class="input-base col-span-2" :name="`items[${index}][qty]`" x-model="item.qty" aria-label="Quantity">
                            <input type="number" min="0" step="0.01" class="input-base col-span-2" :name="`items[${index}][unit_price]`" x-model="item.unit_price" :placeholder="prices[item.product] ?? ''" aria-label="Unit price">
                            <span class="col-span-1 text-right text-sm text-gray-700" x-text="line(item).toFixed(2)"></span>
                            <button type="button" class="btn-ghost btn-sm col-span-1" @click="items.splice(index, 1)" x-show="items.length > 1" aria-label="Remove item"><x-lucide-x class="h-4 w-4" /></button>
                        </div>
                    </template>
                </div>
                @if ($errors->has('items.*'))<p class="form-error">{{ $errors->first('items.*') }}</p>@endif
                <button type="button" class="btn-secondary btn-sm mt-3" @click="items.push({ product: @js($products->first()?->sku), qty: 1, unit_price: '' })"><x-lucide-plus class="h-4 w-4" /> Add item</button>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <div><label for="tax" class="form-label">Tax</label><input id="tax" name="tax" type="number" min="0" step="0.01" x-model="tax" class="input-base"></div>
                <div><label for="currency" class="form-label">Currency</label><input id="currency" name="currency" maxlength="3" value="{{ old('currency', $order->currency) }}" class="input-base uppercase">@error('currency')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div class="self-end rounded-lg bg-gray-50 px-4 py-2 text-sm">Total: <strong x-text="(subtotal + (parseFloat(tax) || 0)).toFixed(2)"></strong></div>
            </div>
            <div><label for="notes" class="form-label">Notes</label><textarea id="notes" name="notes" rows="3" class="input-base">{{ old('notes', $order->notes) }}</textarea></div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
            <a href="{{ $editing ? route('orders.show', $order) : route('orders.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">{{ $editing ? 'Save order' : 'Create order' }}</button>
        </div>
    </form>
</x-layouts.app>
