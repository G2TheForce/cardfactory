@php $editing = $product->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$product->sku : 'New product'">
    <x-page-header :title="$editing ? 'Edit '.$product->sku : 'New product'" description="A card product you stock and sell. Individual cards are added on the NFC cards page." />

    <form method="POST" action="{{ $editing ? route('inventory.update', $product) : route('inventory.store') }}" class="card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <x-field name="sku" label="SKU" :value="$product->sku" required hint="Letters, digits, dots, dashes and underscores, e.g. NFC-PVC-215." />
            <x-field name="name" label="Name" :value="$product->name" required />
            <div>
                <label for="tag_type" class="form-label">Tag type</label>
                <select id="tag_type" name="tag_type" class="select-base">@foreach (\App\Services\NFCManagementService::TAG_TYPES as $t)<option @selected(old('tag_type', $product->tag_type) === $t)>{{ $t }}</option>@endforeach</select>
                <p class="form-hint">NTAG213 holds 144 bytes, NTAG215 504 and NTAG216 888. Any of them fits a profile URL.</p>
            </div>
            <x-field name="material" label="Material" :value="$product->material" hint="PVC, metal, wood, sticker…" />
            <x-field name="unit_price" label="Price ({{ config('smartcard.currency', 'USD') }})" type="number" step="0.01" min="0" :value="$product->unit_price ?? 0" required />
            <x-field name="cost_price" label="Cost ({{ config('smartcard.currency', 'USD') }})" type="number" step="0.01" min="0" :value="$product->cost_price ?? 0" />
            <x-field name="reorder_level" label="Reorder when stock reaches" type="number" :value="$product->reorder_level" required />
            <label class="flex items-center gap-2 self-end pb-2 text-sm">
                <input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="checkbox-base" @checked(old('is_active', $product->is_active))> Active (offered on new orders)
            </label>
            <div class="sm:col-span-2"><label for="description" class="form-label">Description</label><textarea id="description" name="description" rows="3" class="input-base">{{ old('description', $product->description) }}</textarea></div>
            @isset($stock)<p class="text-sm text-gray-600 sm:col-span-2">{{ $stock['total'] }} cards: {{ $stock['available'] }} in stock, {{ $stock['assigned'] }} assigned, {{ $stock['sold'] }} sold.</p>@endisset
            @error('product')<p class="form-error sm:col-span-2">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center justify-between gap-2 border-t border-gray-100 px-5 py-4">
            <div>
                @if ($editing && ($stock['total'] ?? 0) === 0)
                    <button form="delete-product" class="btn-ghost text-red-600">Delete product</button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('inventory.index') }}" class="btn-secondary">Cancel</a>
                <button class="btn-primary">{{ $editing ? 'Save product' : 'Add product' }}</button>
            </div>
        </div>
    </form>
    @if ($editing)
        <form id="delete-product" method="POST" action="{{ route('inventory.destroy', $product) }}" onsubmit="return confirm('Delete this product?')">@csrf @method('DELETE')</form>
    @endif
</x-layouts.app>
