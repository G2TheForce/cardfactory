@php
    use App\Support\ProfileThemes;
    $assignmentBadges = ['active' => 'badge-green', 'assigned' => 'badge-amber', 'inactive' => 'badge-gray', 'replaced' => 'badge-red', 'revoked' => 'badge-gray'];
    $links = [
        ['label' => 'Public page', 'url' => $urls['public'], 'hint' => 'Share anywhere: email signature, social bio, messages.', 'icon' => 'globe'],
        ['label' => 'QR link', 'url' => $urls['qr'], 'hint' => 'What the QR code opens. Scans are counted.', 'icon' => 'qr-code'],
        ['label' => 'NFC link', 'url' => $urls['nfc'], 'hint' => 'Short link for an NFC tag you write yourself. Taps are counted.', 'icon' => 'nfc'],
        ['label' => 'Save contact (vCard)', 'url' => $urls['vcard'], 'hint' => 'Downloads a .vcf contact file.', 'icon' => 'contact-round'],
    ];
@endphp
<x-layouts.app :title="$customer->full_name.' · Digital profile'">
    <x-page-header :title="$customer->full_name" :description="'Digital profile · '.ProfileThemes::get($profile->profile_theme)['name'].' theme · Card ID '.$profile->card_identifier">
        <x-slot:actions>
            <span class="{{ $profile->is_active ? 'badge-green' : 'badge-gray' }}">{{ $profile->is_active ? 'Live' : 'Switched off' }}</span>
            <a href="{{ $available ? $urls['public'] : route('profiles.preview', $profile) }}" target="_blank" rel="noopener" class="btn-secondary"><x-lucide-external-link class="h-4 w-4" /> {{ $available ? 'Open' : 'Preview' }}</a>
            @if ($canEdit)
                <a href="{{ route('profiles.edit', $profile) }}" class="btn-primary"><x-lucide-pencil class="h-4 w-4" /> Edit profile</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @unless ($available)
        <div class="mb-6 flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            <x-lucide-circle-alert class="mt-0.5 h-4 w-4 shrink-0" />
            <p>Visitors see a "card not available" page because {{ ! $profile->is_active ? 'this profile is switched off' : 'the customer is not active' }}. QR codes and NFC cards stop working until it is live again.</p>
        </div>
    @endunless

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ($stats as $label => $value)
            <div class="card p-4">
                <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ number_format($value) }}</p>
                <p class="text-[11px] text-gray-400">last 30 days</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Links --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Links</h2></div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($links as $link)
                        <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:gap-4" x-data="copyToClipboard(@js($link['url']))">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-gray-100 text-gray-500 max-sm:hidden"><x-dynamic-component :component="'lucide-'.$link['icon']" class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $link['label'] }}</p>
                                <p class="truncate font-mono text-xs text-gray-600">{{ $link['url'] }}</p>
                                <p class="text-xs text-gray-400">{{ $link['hint'] }}</p>
                            </div>
                            <button type="button" class="btn-secondary btn-sm shrink-0" @click="copy()">
                                <x-lucide-copy class="h-3.5 w-3.5" /> <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- QR code --}}
            <section id="qr" class="card scroll-mt-6">
                <div class="card-header">
                    <h2 class="card-title">QR code</h2>
                    <span class="text-xs text-gray-500">{{ number_format($qrCode->scans_count) }} scans in total</span>
                </div>
                <div class="card-body grid gap-6 md:grid-cols-[220px_1fr]">
                    <div>
                        <div class="rounded-xl p-3 ring-1 ring-gray-200 [&>svg]:h-auto [&>svg]:w-full" style="{{ $qrStyle['background'] === 'transparent' ? 'background-image: repeating-conic-gradient(#f3f4f6 0 25%, #fff 0 50%); background-size: 16px 16px' : '' }}">{!! $qrSvg !!}</div>
                        <p class="mt-2 text-center text-xs text-gray-500">Opens {{ preg_replace('#^https?://#', '', $urls['qr']) }}</p>
                    </div>
                    <div class="space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Download</h3>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <form method="GET" action="{{ route('profiles.qr.download', [$profile, 'png']) }}" class="flex gap-1.5">
                                    <select name="size" class="select-base w-28 py-1.5 text-xs" aria-label="PNG size">
                                        @foreach (\App\Http\Controllers\Profiles\QrController::SIZES['png'] as $s)<option value="{{ $s }}" @selected($s === 1024)>{{ $s }} px</option>@endforeach
                                    </select>
                                    <button class="btn-secondary btn-sm shrink-0"><x-lucide-download class="h-3.5 w-3.5" /> PNG</button>
                                </form>
                                <a href="{{ route('profiles.qr.download', [$profile, 'svg']) }}" class="btn-secondary btn-sm"><x-lucide-download class="h-3.5 w-3.5" /> SVG (vector)</a>
                                <form method="GET" action="{{ route('profiles.qr.download', [$profile, 'pdf']) }}" class="flex gap-1.5">
                                    <select name="size" class="select-base w-24 py-1.5 text-xs" aria-label="PDF size">
                                        @foreach (\App\Http\Controllers\Profiles\QrController::SIZES['pdf'] as $s)<option value="{{ $s }}" @selected($s === 50)>{{ $s }} mm</option>@endforeach
                                    </select>
                                    <button class="btn-secondary btn-sm shrink-0"><x-lucide-download class="h-3.5 w-3.5" /> PDF</button>
                                </form>
                            </div>
                            <p class="form-hint">SVG and PDF are vector files for print. Keep at least 2 cm wide on a printed card.</p>
                        </div>

                        @if ($canEdit)
                            <form method="POST" action="{{ route('profiles.qr.update', $profile) }}" class="border-t border-gray-100 pt-5" x-data="{ transparent: @js($qrStyle['background'] === 'transparent') }">
                                @csrf @method('PUT')
                                <h3 class="text-sm font-semibold text-gray-900">Style</h3>
                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="form-label" for="qr-color">Code colour</label>
                                        <input id="qr-color" type="color" name="color" value="{{ old('color', $qrStyle['color']) }}" class="h-10 w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-1">
                                        @error('color')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="form-label" for="qr-bg">Background</label>
                                        <input id="qr-bg" type="color" name="background" value="{{ old('background', $qrStyle['background'] === 'transparent' ? '#ffffff' : $qrStyle['background']) }}" :disabled="transparent" class="h-10 w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-1 disabled:opacity-40">
                                        <label class="mt-2 flex items-center gap-2 text-xs text-gray-600"><input type="hidden" name="transparent" value="0"><input type="checkbox" name="transparent" value="1" x-model="transparent" class="checkbox-base"> Transparent (PNG, SVG, PDF)</label>
                                    </div>
                                    <div>
                                        <label class="form-label" for="qr-margin">Quiet zone</label>
                                        <select id="qr-margin" name="margin" class="select-base">
                                            @foreach ([0 => 'None', 1 => '1 module', 2 => '2 modules (default)', 4 => '4 modules (standard)'] as $v => $l)<option value="{{ $v }}" @selected($qrStyle['margin'] === $v)>{{ $l }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" for="qr-level">Error correction</label>
                                        <select id="qr-level" name="level" class="select-base">
                                            @foreach (['L' => 'Low (7%)', 'M' => 'Medium (15%)', 'Q' => 'Quartile (25%)', 'H' => 'High (30%)'] as $v => $l)<option value="{{ $v }}" @selected($qrStyle['level'] === $v)>{{ $l }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-end"><button class="btn-secondary btn-sm">Save style</button></div>
                            </form>
                        @endif
                    </div>
                </div>
            </section>

            {{-- NFC --}}
            <section id="nfc" class="card scroll-mt-6" x-data="nfcTools()">
                <div class="card-header">
                    <h2 class="card-title">NFC cards</h2>
                    <span class="text-xs text-gray-500">{{ $cards->count() }} linked</span>
                </div>

                @if ($errors->nfc->any() || $errors->replace->any())
                    <div class="mx-5 mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                        @foreach (array_merge($errors->nfc->all(), $errors->replace->all()) as $message)<p>{{ $message }}</p>@endforeach
                    </div>
                @endif

                <ul class="divide-y divide-gray-100">
                    @forelse ($cards as $a)
                        @php $card = $a->nfcCard; @endphp
                        <li class="px-5 py-4" x-data="{ replace: false, uid: @js($card->uid ?? '') }">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-lucide-nfc class="h-5 w-5" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900">
                                        <span class="font-mono">{{ $card->card_identifier }}</span>
                                        <span class="{{ $assignmentBadges[$a->status] ?? 'badge-gray' }}">{{ $a->status === 'assigned' ? 'Waiting to be encoded' : ucfirst($a->status) }}</span>
                                        <span class="badge-gray">{{ $card->tag_type }}</span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        UID {{ $card->uid ?? 'not recorded' }} · assigned {{ $a->assigned_at->diffForHumans() }}
                                        @if ($a->activated_at) · active since {{ $a->activated_at->toFormattedDateString() }} @endif
                                        @if ($a->replaces_assignment_id) · replaces a lost/damaged card @endif
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-1.5" x-data="copyToClipboard(@js($a->tag_url))">
                                    <button type="button" class="btn-secondary btn-sm" @click="copy()"><x-lucide-copy class="h-3.5 w-3.5" /> <span x-text="copied ? 'Copied' : 'Copy tag URL'">Copy tag URL</span></button>
                                    @if ($canNfc)
                                        <button type="button" class="btn-secondary btn-sm" x-show="supported" x-cloak @click="write(@js($a->tag_url))"><x-lucide-smartphone-nfc class="h-3.5 w-3.5" /> Write with this phone</button>
                                    @endif
                                </div>
                            </div>
                            <p class="mt-2 rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700">{{ $a->tag_url }}</p>

                            @if ($canNfc)
                                <div class="mt-3 flex flex-wrap items-end gap-2">
                                    @if ($a->status === 'assigned' || ! $card->uid)
                                        <form method="POST" action="{{ route('profiles.nfc.encoded', [$profile, $card]) }}" class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            <div>
                                                <label class="text-xs font-medium text-gray-600" for="uid-{{ $card->ulid }}">Tag UID (optional)</label>
                                                <div class="flex gap-1.5">
                                                    <input id="uid-{{ $card->ulid }}" name="uid" x-model="uid" placeholder="04:A2:3B:1C:5D:80:00" class="input-base w-56 py-1.5 font-mono text-xs">
                                                    <button type="button" class="btn-ghost btn-sm" x-show="supported" x-cloak @click="readUid().then(v => { if (v) uid = v })" title="Read the UID by holding the card to this phone"><x-lucide-scan-line class="h-4 w-4" /><span class="sr-only">Read UID</span></button>
                                                </div>
                                            </div>
                                            <button class="btn-primary btn-sm">{{ $a->status === 'assigned' ? 'Mark as encoded' : 'Save UID' }}</button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn-secondary btn-sm" @click="replace = ! replace">Replace lost or damaged card</button>
                                    <form method="POST" action="{{ route('profiles.nfc.destroy', [$profile, $card]) }}" x-data @submit="if (! confirm('Unlink {{ $card->card_identifier }}? Tapping it will no longer open this profile.')) $event.preventDefault()">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50 hover:text-red-700">Unassign</button>
                                    </form>
                                </div>
                                <form x-show="replace" x-cloak x-collapse method="POST" action="{{ route('profiles.nfc.replace', [$profile, $card]) }}" class="mt-3 grid gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-4 sm:items-end">
                                    @csrf
                                    <div>
                                        <label class="text-xs font-medium text-gray-600">Reason</label>
                                        <select name="reason" class="select-base py-1.5 text-sm"><option value="lost">Lost</option><option value="damaged">Damaged</option></select>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-600">New card ID</label>
                                        <input name="identifier" required placeholder="SC-…" class="input-base py-1.5 font-mono text-sm uppercase">
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-600">New tag UID (optional)</label>
                                        <input name="uid" class="input-base py-1.5 font-mono text-sm">
                                    </div>
                                    <button class="btn-primary btn-sm">Replace card</button>
                                    <p class="text-xs text-gray-500 sm:col-span-4">{{ $card->card_identifier }} stops opening this profile straight away and is marked lost or defective.</p>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li><x-empty-state icon="nfc" title="No NFC card linked" description="{{ $canNfc ? 'Assign a card below. Its URL never changes, so it can be moved to another profile later without rewriting.' : 'Ask your account manager for an NFC card, or write the NFC link above to any NFC tag.' }}" /></li>
                    @endforelse
                </ul>

                <p x-show="message" x-cloak x-text="message" class="mx-5 mb-3 rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-800"></p>

                @if ($canNfc)
                    <form method="POST" action="{{ route('profiles.nfc.store', $profile) }}" class="border-t border-gray-100 px-5 py-4">
                        @csrf
                        <h3 class="text-sm font-semibold text-gray-900">Assign a card</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-4 sm:items-end">
                            <div class="sm:col-span-1">
                                <label for="nfc-identifier" class="text-xs font-medium text-gray-600">Card ID <span class="text-red-500">*</span></label>
                                <input id="nfc-identifier" name="identifier" value="{{ old('identifier') }}" required placeholder="SC-7K2M9Q" class="input-base font-mono uppercase">
                            </div>
                            <div>
                                <label for="nfc-uid" class="text-xs font-medium text-gray-600">Tag UID</label>
                                <input id="nfc-uid" name="uid" value="{{ old('uid') }}" placeholder="optional" class="input-base font-mono">
                            </div>
                            <div>
                                <label for="nfc-type" class="text-xs font-medium text-gray-600">Tag type (new cards)</label>
                                <select id="nfc-type" name="tag_type" class="select-base">@foreach ($tagTypes as $type)<option @selected(old('tag_type', 'NTAG215') === $type)>{{ $type }}</option>@endforeach</select>
                            </div>
                            <button class="btn-primary">Assign</button>
                        </div>
                        <label class="mt-3 flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="move" value="1" class="checkbox-base" @checked(old('move'))> Move it here if it's linked to another profile</label>
                        <p class="form-hint">Use the ID printed on the card. A card ID that isn't in stock yet is added automatically.</p>
                    </form>
                @endif

                <details class="border-t border-gray-100 px-5 py-4 text-sm text-gray-600">
                    <summary class="cursor-pointer font-semibold text-gray-900">How to write an NFC tag</summary>
                    <ol class="mt-3 list-decimal space-y-1.5 pl-5">
                        <li>Copy the tag URL (or the NFC link above for a tag that isn't in stock).</li>
                        <li>Open an NFC writing app such as NXP TagWriter or NFC Tools on a phone with NFC, or use a USB NFC writer.</li>
                        <li>Choose "Write" → "URL / URI", paste the URL and hold the tag to the back of the phone.</li>
                        <li>Tap the tag with another phone to test it. The first tap marks the card active here.</li>
                        <li>Optionally lock the tag in the app so nobody can overwrite it.</li>
                    </ol>
                    <p class="mt-3 text-xs text-gray-500">On Android with Chrome, the "Write with this phone" button writes the tag directly. Other browsers can't write NFC tags, so use an app.</p>
                </details>

                @if ($history->isNotEmpty())
                    <details class="border-t border-gray-100 px-5 py-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-gray-900">Card history</summary>
                        <ul class="mt-3 space-y-1.5 text-xs text-gray-600">
                            @foreach ($history as $h)
                                <li><span class="font-mono">{{ $h->nfcCard?->card_identifier }}</span> · {{ ucfirst($h->status) }} {{ $h->ended_at->toFormattedDateString() }}{{ $h->reason ? ' · '.$h->reason : '' }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        </div>

        {{-- Preview --}}
        <aside class="space-y-6">
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Preview</h2><a href="{{ route('profiles.preview', $profile) }}" target="_blank" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Full screen</a></div>
                <div class="bg-gray-50 p-4">
                    <div class="mx-auto h-[600px] w-[300px] overflow-hidden rounded-[2rem] border-[6px] border-gray-900 bg-white shadow-lift">
                        <iframe src="{{ route('profiles.preview', $profile) }}" title="Profile preview" class="h-full w-full" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
            <div class="card card-body space-y-2 text-sm">
                <p class="flex justify-between"><span class="text-gray-500">Customer</span>@can('view', $customer)<a href="{{ route('customers.show', $customer) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $customer->full_name }}</a>@else<span>{{ $customer->full_name }}</span>@endcan</p>
                <p class="flex justify-between"><span class="text-gray-500">Card design</span><span class="text-gray-900">{{ $profile->cardDesign?->name ?? 'None linked' }}</span></p>
                <p class="flex justify-between"><span class="text-gray-500">Search engines</span><span class="text-gray-900">{{ ($profile->settings['allow_indexing'] ?? false) ? 'Allowed' : 'Hidden' }}</span></p>
                <p class="flex justify-between"><span class="text-gray-500">Total views</span><span class="tabular-nums text-gray-900">{{ number_format($profile->views_count) }}</span></p>
                @if ($canEdit)
                    <form method="POST" action="{{ route('profiles.destroy', $profile) }}" class="border-t border-gray-100 pt-3" x-data @submit="if (! confirm('Delete this profile? Its QR code and NFC cards will stop opening it.')) $event.preventDefault()">
                        @csrf @method('DELETE')
                        <button class="btn-ghost btn-sm -ml-2 text-red-600 hover:bg-red-50 hover:text-red-700"><x-lucide-trash-2 class="h-4 w-4" /> Delete profile</button>
                    </form>
                @endif
            </div>
        </aside>
    </div>

    <script>
        // Web NFC (Android Chrome only). Everywhere else the buttons stay hidden.
        function nfcTools() {
            return {
                supported: 'NDEFReader' in window,
                message: '',
                async write(url) {
                    try {
                        this.message = 'Hold the card to the back of this phone…';
                        await new NDEFReader().write({ records: [{ recordType: 'url', data: url }] });
                        this.message = 'Written. Tap the card with a phone to test it, then mark it as encoded.';
                    } catch (e) {
                        this.message = 'Could not write the tag: ' + e.message;
                    }
                },
                readUid() {
                    return new Promise((resolve) => {
                        const reader = new NDEFReader();
                        this.message = 'Hold the card to the back of this phone…';
                        reader.scan().then(() => {
                            reader.onreading = (e) => { this.message = 'UID read.'; resolve((e.serialNumber || '').toUpperCase()); };
                        }).catch((e) => { this.message = 'Could not read the tag: ' + e.message; resolve(null); });
                    });
                },
            };
        }
    </script>
</x-layouts.app>
