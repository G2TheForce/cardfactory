@php $current = $card->currentAssignment; @endphp
<x-layouts.app :title="'NFC card '.$card->card_identifier">
    <x-slot:breadcrumbs>
        <a href="{{ route('nfc.index') }}" class="hover:text-gray-700">NFC cards</a><span>/</span><span class="text-gray-900">{{ $card->card_identifier }}</span>
    </x-slot:breadcrumbs>

    <x-page-header :title="$card->card_identifier" :description="$card->tag_type.($card->product ? ' · '.$card->product->name : '').($card->batch_number ? ' · batch '.$card->batch_number : '')">
        <x-slot:actions>
            @include('nfc._status', ['status' => $card->status])
            <a href="{{ route('nfc.index') }}" class="btn-secondary">All cards</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card" x-data="{ copied: false }">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Tag URL</h2></div>
                <div class="card-body space-y-3">
                    <div class="flex gap-2">
                        <input readonly value="{{ $tagUrl }}" class="input-base font-mono text-sm" aria-label="Tag URL" @focus="$event.target.select()">
                        <button type="button" class="btn-secondary" @click="navigator.clipboard.writeText(@js($tagUrl)); copied = true; setTimeout(() => copied = false, 2000)"><x-lucide-copy class="h-4 w-4" /> <span x-text="copied ? 'Copied' : 'Copy'"></span></button>
                    </div>
                    <p class="text-sm text-gray-600">Write this URL to the tag as an NDEF URI record with an NFC writer app or a USB encoder. It never changes: linking the card to another profile takes effect on the next tap.</p>
                    @if ($card->encoded_at)<p class="text-xs text-gray-500">First tapped / encoded {{ $card->encoded_at->diffForHumans() }}.</p>@endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Linked profile</h2></div>
                <div class="card-body">
                    @if ($current?->digitalProfile)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <a href="{{ route('profiles.show', $current->digitalProfile) }}" class="font-semibold text-gray-900 hover:text-brand-700">{{ $current->customer?->full_name }}</a>
                                <p class="text-sm text-gray-500">/card/{{ $current->digitalProfile->slug }} · {{ $current->status === 'active' ? 'active since '.$current->activated_at?->toFormattedDateString() : 'assigned, waiting for the first tap' }}</p>
                            </div>
                            <form method="POST" action="{{ route('nfc.unassign', $card) }}" onsubmit="return confirm('Unlink this card? Tapping it will no longer open the profile.')">
                                @csrf @method('DELETE')
                                <button class="btn-secondary btn-sm"><x-lucide-unlink class="h-4 w-4" /> Unlink</button>
                            </form>
                        </div>
                    @endif
                    @if (! in_array($card->status, ['defective', 'lost', 'replaced', 'retired'], true))
                        <form method="POST" action="{{ route('nfc.assign', $card) }}" class="{{ $current ? 'mt-4 border-t border-gray-100 pt-4' : '' }} flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="min-w-64 flex-1">
                                <label for="profile" class="form-label">{{ $current ? 'Move to another profile' : 'Link to a profile' }}</label>
                                <select id="profile" name="profile" class="select-base" required>
                                    <option value="">Choose a profile…</option>
                                    @foreach ($profiles as $p)<option value="{{ $p->ulid }}">{{ $p->customer?->full_name }} · /card/{{ $p->slug }}</option>@endforeach
                                </select>
                                @error('profile')<p class="form-error">{{ $message }}</p>@enderror
                                @error('identifier')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            @if ($current)<input type="hidden" name="move" value="1">@endif
                            <button class="btn-primary"><x-lucide-link class="h-4 w-4" /> {{ $current ? 'Move card' : 'Link card' }}</button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500">This card is {{ $card->status }} and can't be linked. Set its status back to "In stock" if that was a mistake.</p>
                    @endif
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Assignment history</h2></div>
                <table class="table">
                    <thead><tr><th>Customer</th><th>Status</th><th>Assigned</th><th>Ended</th><th>By</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($history as $h)
                            <tr>
                                <td class="text-sm">{{ $h->customer?->full_name ?? '—' }}@if ($h->digitalProfile)<span class="block text-xs text-gray-500">/card/{{ $h->digitalProfile->slug }}</span>@endif</td>
                                <td class="text-sm text-gray-600">{{ ucfirst($h->status) }}@if ($h->reason)<span class="block text-xs text-gray-500">{{ $h->reason }}</span>@endif</td>
                                <td class="text-xs text-gray-500">{{ $h->assigned_at?->toFormattedDateString() }}</td>
                                <td class="text-xs text-gray-500">{{ $h->ended_at?->toFormattedDateString() ?? '—' }}</td>
                                <td class="text-xs text-gray-500">{{ $h->assignedBy?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-sm text-gray-500">Never assigned.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="card-header"><h2 class="font-semibold text-gray-900">Orders</h2></div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($orders as $o)
                            <li class="flex items-center justify-between px-5 py-3 text-sm">
                                @can('orders.manage')<a href="{{ route('orders.show', $o) }}" class="font-medium text-brand-700 hover:underline">{{ $o->order_number }}</a>@else<span>{{ $o->order_number }}</span>@endcan
                                <span class="text-gray-500">{{ $o->customer?->full_name }} · {{ ucfirst($o->status) }} · {{ $o->created_at->toFormattedDateString() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('nfc.update', $card) }}" class="card self-start">
            @csrf @method('PUT')
            <div class="card-header"><h2 class="font-semibold text-gray-900">Card details</h2></div>
            <div class="card-body space-y-4">
                <div>
                    <label for="uid" class="form-label">Tag UID</label>
                    <input id="uid" name="uid" value="{{ old('uid', $card->uid) }}" class="input-base font-mono" placeholder="04:A2:3B:1C:5D:80:00">
                    @error('uid')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">Read it with an NFC app. Leave empty if unknown.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="tag_type" class="form-label">Tag type</label>
                        <select id="tag_type" name="tag_type" class="select-base">@foreach (\App\Services\NFCManagementService::TAG_TYPES as $t)<option @selected(old('tag_type', $card->tag_type) === $t)>{{ $t }}</option>@endforeach</select></div>
                    <div><label for="product" class="form-label">Product</label>
                        <select id="product" name="product" class="select-base"><option value="">None</option>@foreach ($products as $p)<option value="{{ $p->sku }}" @selected(old('product', $card->product?->sku) === $p->sku)>{{ $p->sku }}</option>@endforeach</select></div>
                </div>
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="select-base">
                        @foreach (\App\Models\NfcCard::STATUSES as $s)
                            @if (in_array($s, \App\Services\InventoryService::MANUAL_STATUSES, true) || $s === $card->status)
                                <option value="{{ $s }}" @selected(old('status', $card->status) === $s)>{{ str_replace('_', ' ', ucfirst($s)) }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('status')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">Marking a card defective, lost or retired unlinks it at once. To swap a lost card for a new one, use "Replace" on the profile page.</p>
                </div>
                <div><label for="batch_number" class="form-label">Batch</label><input id="batch_number" name="batch_number" value="{{ old('batch_number', $card->batch_number) }}" class="input-base"></div>
                <div><label for="notes" class="form-label">Notes</label><textarea id="notes" name="notes" rows="3" class="input-base">{{ old('notes', $card->notes) }}</textarea></div>
            </div>
            <div class="flex justify-end border-t border-gray-100 px-5 py-4"><button class="btn-primary">Save card</button></div>
        </form>
    </div>
</x-layouts.app>
