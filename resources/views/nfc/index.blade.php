@php
    $groups = ['available' => ['Available', 'package-check', 'emerald'], 'assigned' => ['Assigned & active', 'nfc', 'brand'], 'sold' => ['Sold', 'shopping-bag', 'sky'], 'defective' => ['Defective', 'circle-x', 'rose'], 'lost' => ['Lost / replaced', 'search-x', 'amber']];
@endphp
<x-layouts.app title="NFC cards">
    <x-page-header title="NFC cards" description="Every physical card with its printed ID, tag UID, type and who it's linked to. Cards are encoded once with their own URL, so reassigning never needs a rewrite.">
        <x-slot:actions>
            <a href="{{ route('nfc.export', request()->query()) }}" class="btn-secondary"><x-lucide-download class="h-4 w-4" /> CSV for encoding</a>
            @can('inventory.manage')<a href="{{ route('inventory.index') }}" class="btn-secondary"><x-lucide-package class="h-4 w-4" /> Products</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($groups as $key => [$label, $icon, $tone])
            <a href="{{ route('nfc.index', ['status' => $key]) }}" class="block"><x-stat-card :label="$label" :value="number_format($summary[$key])" :icon="$icon" :tone="$tone" class="{{ ($filters['status'] ?? null) === $key ? 'ring-2 ring-brand-500' : '' }}" /></a>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-4">
                <div class="min-w-48 flex-1"><label for="search" class="form-label">Search</label><input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="input-base" placeholder="Card ID, UID or customer"></div>
                <div><label for="f-status" class="form-label">Status</label>
                    <select id="f-status" name="status" class="select-base"><option value="">Any</option>
                        @foreach (\App\Models\NfcCard::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ str_replace('_', ' ', ucfirst($s)) }}</option>@endforeach
                    </select></div>
                <div><label for="f-type" class="form-label">Tag</label>
                    <select id="f-type" name="tag_type" class="select-base"><option value="">Any</option>
                        @foreach (\App\Services\NFCManagementService::TAG_TYPES as $t)<option @selected(($filters['tag_type'] ?? '') === $t)>{{ $t }}</option>@endforeach
                    </select></div>
                <div><label for="f-product" class="form-label">Product</label>
                    <select id="f-product" name="product" class="select-base"><option value="">Any</option>
                        @foreach ($products as $p)<option value="{{ $p->sku }}" @selected(($filters['product'] ?? '') === $p->sku)>{{ $p->sku }}</option>@endforeach
                    </select></div>
                @if ($batches->isNotEmpty())
                    <div><label for="f-batch" class="form-label">Batch</label>
                        <select id="f-batch" name="batch" class="select-base"><option value="">Any</option>
                            @foreach ($batches as $b)<option @selected(($filters['batch'] ?? '') === $b)>{{ $b }}</option>@endforeach
                        </select></div>
                @endif
                <button class="btn-secondary">Filter</button>
                @if (array_filter($filters))<a href="{{ route('nfc.index') }}" class="btn-ghost">Clear</a>@endif
            </form>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Card</th><th>UID</th><th>Product</th><th>Status</th><th>Linked to</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cards as $card)
                            @php $a = $card->currentAssignment; @endphp
                            <tr>
                                <td><a href="{{ route('nfc.show', $card) }}" class="font-mono text-sm font-semibold text-gray-900 hover:text-brand-700">{{ $card->card_identifier }}</a><span class="block text-xs text-gray-500">{{ $card->tag_type }}{{ $card->batch_number ? ' · '.$card->batch_number : '' }}</span></td>
                                <td class="font-mono text-xs text-gray-600">{{ $card->uid ?? '—' }}</td>
                                <td class="text-sm text-gray-600">{{ $card->product?->sku ?? '—' }}</td>
                                <td>@include('nfc._status', ['status' => $card->status])</td>
                                <td class="text-sm">
                                    @if ($a?->digitalProfile)<a href="{{ route('profiles.show', $a->digitalProfile) }}" class="text-brand-700 hover:underline">{{ $a->customer?->full_name }}</a><span class="block text-xs text-gray-500">/card/{{ $a->digitalProfile->slug }}</span>
                                    @else<span class="text-gray-400">—</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="nfc" title="No cards found" description="Add cards to stock with the form, or change the filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($cards->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $cards->links() }}</div>@endif
        </div>

        <form method="POST" action="{{ route('nfc.store') }}" class="card self-start" x-data="{ mode: @js(old('mode', 'generate')) }">
            @csrf
            <div class="card-header"><h2 class="font-semibold text-gray-900">Add cards to stock</h2></div>
            <div class="card-body space-y-4">
                <div class="flex rounded-lg bg-gray-100 p-1 text-sm">
                    <label class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-center has-[:checked]:bg-white has-[:checked]:font-semibold has-[:checked]:shadow-sm"><input type="radio" name="mode" value="generate" x-model="mode" class="sr-only"> New IDs</label>
                    <label class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-center has-[:checked]:bg-white has-[:checked]:font-semibold has-[:checked]:shadow-sm"><input type="radio" name="mode" value="uids" x-model="mode" class="sr-only"> From UIDs</label>
                </div>
                <div x-show="mode === 'generate'">
                    <label for="count" class="form-label">How many</label>
                    <input id="count" name="count" type="number" min="1" max="{{ \App\Services\InventoryService::MAX_BATCH }}" value="{{ old('count', 50) }}" class="input-base">
                    @error('count')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">Each card gets a unique printed ID such as SC-7K2M9QXA. Record UIDs later, when encoding.</p>
                </div>
                <div x-show="mode === 'uids'" x-cloak>
                    <label for="uids" class="form-label">Tag UIDs, one per line</label>
                    <textarea id="uids" name="uids" rows="5" class="input-base font-mono text-xs" placeholder="04:A2:3B:1C:5D:80:00">{{ old('uids') }}</textarea>
                    @error('uids')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">From your reader or the supplier's list. One card is created per UID.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="tag_type" class="form-label">Tag type</label>
                        <select id="tag_type" name="tag_type" class="select-base">@foreach (\App\Services\NFCManagementService::TAG_TYPES as $t)<option @selected(old('tag_type', 'NTAG215') === $t)>{{ $t }}</option>@endforeach</select></div>
                    <div><label for="product" class="form-label">Product</label>
                        <select id="product" name="product" class="select-base"><option value="">None</option>@foreach ($products as $p)<option value="{{ $p->sku }}" @selected(old('product', request('product')) === $p->sku)>{{ $p->sku }}</option>@endforeach</select></div>
                    <div><label for="batch_number" class="form-label">Batch</label><input id="batch_number" name="batch_number" value="{{ old('batch_number') }}" class="input-base" placeholder="2026-11-A"></div>
                    <div><label for="prefix" class="form-label">ID prefix</label><input id="prefix" name="prefix" value="{{ old('prefix', 'SC') }}" maxlength="6" class="input-base uppercase">@error('prefix')<p class="form-error">{{ $message }}</p>@enderror</div>
                </div>
                @error('tag_type')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end border-t border-gray-100 px-5 py-4"><button class="btn-primary"><x-lucide-plus class="h-4 w-4" /> Add to stock</button></div>
        </form>
    </div>
</x-layouts.app>
