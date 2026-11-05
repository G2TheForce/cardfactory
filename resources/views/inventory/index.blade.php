@php $currency = config('smartcard.currency', 'USD'); @endphp
<x-layouts.app title="Inventory">
    <x-page-header title="Inventory" description="NFC card products and how many of each are in stock, assigned, sold or out of use.">
        <x-slot:actions>
            @can('nfc.manage')<a href="{{ route('nfc.index') }}" class="btn-secondary"><x-lucide-nfc class="h-4 w-4" /> NFC cards</a>@endcan
            <a href="{{ route('inventory.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New product</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Cards in stock" :value="number_format($summary['available'])" icon="package-check" tone="emerald" />
        <x-stat-card label="Assigned & active" :value="number_format($summary['assigned'])" icon="nfc" />
        <x-stat-card label="Sold" :value="number_format($summary['sold'])" icon="shopping-bag" tone="sky" />
        <x-stat-card label="Defective, lost or replaced" :value="number_format($summary['defective'] + $summary['lost'])" icon="circle-x" tone="rose" />
    </div>

    @php $low = $products->where('low_stock', true); @endphp
    @if ($low->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-lucide-triangle-alert class="mr-1 inline h-4 w-4" /> Low stock: {{ $low->map(fn ($p) => $p->sku.' ('.$p->stock['available'].' left)')->implode(', ') }}.
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Product</th><th>Tag</th><th class="text-right">Price</th><th class="text-right">In stock</th><th class="text-right">Assigned</th><th class="text-right">Sold</th><th class="text-right">Out of use</th><th class="text-right">Reorder at</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $product)
                        <tr class="{{ $product->is_active ? '' : 'opacity-60' }}">
                            <td><span class="font-mono text-xs text-gray-500">{{ $product->sku }}</span><span class="block font-medium text-gray-900">{{ $product->name }}</span>@unless ($product->is_active)<span class="badge-gray mt-1">Inactive</span>@endunless</td>
                            <td class="text-sm text-gray-600">{{ $product->tag_type }}{{ $product->material ? ' · '.$product->material : '' }}</td>
                            <td class="text-right text-sm">{{ Illuminate\Support\Number::currency((float) $product->unit_price, $currency) }}</td>
                            <td class="text-right text-sm font-semibold {{ $product->low_stock ? 'text-amber-700' : 'text-gray-900' }}">@can('nfc.manage')<a href="{{ route('nfc.index', ['product' => $product->sku, 'status' => 'available']) }}" class="hover:underline">{{ $product->stock['available'] }}</a>@else{{ $product->stock['available'] }}@endcan</td>
                            <td class="text-right text-sm">{{ $product->stock['assigned'] }}</td>
                            <td class="text-right text-sm">{{ $product->stock['sold'] }}</td>
                            <td class="text-right text-sm">{{ $product->stock['defective'] + $product->stock['lost'] + $product->stock['retired'] }}</td>
                            <td class="text-right text-sm text-gray-500">{{ $product->reorder_level }}</td>
                            <td class="text-right whitespace-nowrap">
                                @can('nfc.manage')<a href="{{ route('nfc.index', ['product' => $product->sku]) }}#product" class="btn-ghost btn-sm" title="Add stock">Add stock</a>@endcan
                                <a href="{{ route('inventory.edit', $product) }}" class="btn-ghost btn-sm" aria-label="Edit {{ $product->sku }}"><x-lucide-pencil class="h-4 w-4" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state icon="package" title="No products yet" description="Add the card products you sell, such as a PVC NTAG215 card or a metal NTAG216 card." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($unassignedProduct > 0)<p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">{{ $unassignedProduct }} {{ Str::plural('card', $unassignedProduct) }} have no product.</p>@endif
    </div>
</x-layouts.app>
