@php
    $badges = ['uploaded' => 'badge-gray', 'validated' => 'badge-brand', 'processing' => 'badge-amber', 'completed' => 'badge-green', 'failed' => 'badge-red'];
    $labels = ['uploaded' => 'Needs matching', 'validated' => 'Checked', 'processing' => 'Generating', 'completed' => 'Completed', 'failed' => 'Failed'];
    $headers = $import->headers ?? [];
    $mapping = old('mapping', $import->column_mapping ?? []);
    $options = $import->options ?? [];
    $errors_ = collect($import->errors ?? []);
    $editable = in_array($import->status, ['uploaded', 'validated'], true);
@endphp
<x-layouts.app :title="'Bulk run · '.$import->original_filename">
    <x-page-header :title="$import->original_filename" :description="$import->total_rows.' rows · uploaded '.$import->created_at->diffForHumans().' by '.($import->user?->name ?? 'unknown')">
        <x-slot:actions>
            <span class="{{ $badges[$import->status] ?? 'badge-gray' }}">{{ $labels[$import->status] ?? ucfirst($import->status) }}</span>
            @if ($import->status !== 'processing')
                <form method="POST" action="{{ route('imports.destroy', $import) }}" onsubmit="return confirm('Delete this bulk run? Customers, designs and profiles it created are kept.')">
                    @csrf @method('DELETE')
                    <button class="btn-secondary"><x-lucide-trash-2 class="h-4 w-4" /> Delete</button>
                </form>
            @endif
            <a href="{{ route('imports.index') }}" class="btn-secondary">All bulk runs</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Step 1: match columns and choose the card source --}}
    @if ($editable)
        <form method="POST" action="{{ route('imports.check', $import) }}" class="space-y-6"
              x-data="{ samples: @js($samples), map: @js(collect($fields)->keys()->mapWithKeys(fn ($f) => [$f => isset($mapping[$f]) ? (string) $mapping[$f] : ''])), source: @js(old('source', $options['source'] ?? 'theme')), profiles: @js((bool) old('create_profiles', $options['create_profiles'] ?? true)) }">
            @csrf
            <div class="card">
                <div class="card-header"><h2 class="font-semibold text-gray-900">1. Match your columns</h2><p class="text-sm text-gray-500">We matched what we could. Leave a field empty if your file doesn't have it.</p></div>
                @error('file')<p class="form-error px-5 pt-3">{{ $message }}</p>@enderror
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th class="w-56">Field</th><th class="w-72">Column in your file</th><th>Example values</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($fields as $key => $field)
                                <tr>
                                    <td class="font-medium text-gray-800">{{ $field['label'] }}@if ($key === 'full_name') <span class="text-red-500">*</span>@endif</td>
                                    <td>
                                        <select name="mapping[{{ $key }}]" class="select-base py-1.5 text-sm" x-model="map['{{ $key }}']" aria-label="Column for {{ $field['label'] }}">
                                            <option value="">— not in file —</option>
                                            @foreach ($headers as $i => $h)<option value="{{ $i }}">{{ $h !== '' ? $h : 'Column '.($i + 1) }}</option>@endforeach
                                        </select>
                                        @error('mapping.'.$key)<p class="form-error">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="max-w-md truncate text-xs text-gray-500">
                                        <span x-text="map['{{ $key }}'] === '' ? '' : samples.map(r => r[map['{{ $key }}']] ?? '').filter(v => v).join(' · ')"></span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="font-semibold text-gray-900">2. Choose the card and profile</h2></div>
                <div class="card-body grid gap-6 md:grid-cols-2">
                    <fieldset class="space-y-3">
                        <legend class="form-label">Card design</legend>
                        <label class="flex items-start gap-3"><input type="radio" name="source" value="theme" x-model="source" class="mt-1 h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500"> <span><span class="font-medium text-gray-800">From a theme</span><span class="block text-xs text-gray-500">Each customer gets a new design from this theme, filled with their details.</span></span></label>
                        <div x-show="source === 'theme'">
                            <select name="card_theme" class="select-base" aria-label="Card theme">
                                <option value="">Choose a theme…</option>
                                @foreach ($themes as $theme)<option value="{{ $theme->ulid }}" @selected(old('card_theme', $import->theme?->ulid) === $theme->ulid)>{{ $theme->name }}{{ $theme->category ? ' · '.ucfirst($theme->category) : '' }}</option>@endforeach
                            </select>
                            @error('card_theme')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-start gap-3"><input type="radio" name="source" value="design" x-model="source" class="mt-1 h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500"> <span><span class="font-medium text-gray-800">Copy one of your designs</span><span class="block text-xs text-gray-500">Use a design you've already laid out as the template. Merge fields fill in per customer.</span></span></label>
                        <div x-show="source === 'design'" x-cloak>
                            <select name="card_design" class="select-base" aria-label="Template design">
                                <option value="">Choose a design…</option>
                                @foreach ($templates as $design)<option value="{{ $design->ulid }}" @selected(old('card_design', $import->templateDesign?->ulid) === $design->ulid)>{{ $design->name }} ({{ (float) $design->width_mm }} × {{ (float) $design->height_mm }} mm)</option>@endforeach
                            </select>
                            @error('card_design')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </fieldset>
                    <div class="space-y-4">
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="create_profiles" value="0">
                            <input type="checkbox" name="create_profiles" value="1" x-model="profiles" class="checkbox-base mt-1">
                            <span><span class="font-medium text-gray-800">Create digital profiles</span><span class="block text-xs text-gray-500">Each customer gets a public profile page, its own QR code and an NFC link. Use the "Profile address" column to pick addresses; otherwise they come from the name.</span></span>
                        </label>
                        <div x-show="profiles">
                            <label for="profile_theme" class="form-label">Profile theme</label>
                            <select id="profile_theme" name="profile_theme" class="select-base">
                                @foreach ($profileThemes as $key => $t)<option value="{{ $key }}" @selected(old('profile_theme', $options['profile_theme'] ?? 'classic') === $key)>{{ $t['name'] }}</option>@endforeach
                            </select>
                        </div>
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="skip_existing" value="0">
                            <input type="checkbox" name="skip_existing" value="1" class="checkbox-base mt-1" @checked(old('skip_existing', $options['skip_existing'] ?? true))>
                            <span><span class="font-medium text-gray-800">Skip people who are already customers</span><span class="block text-xs text-gray-500">Rows whose email matches an existing customer are skipped instead of duplicated.</span></span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end border-t border-gray-100 px-5 py-4">
                    <button class="btn-primary"><x-lucide-list-checks class="h-4 w-4" /> {{ $import->status === 'validated' ? 'Check rows again' : 'Check rows' }}</button>
                </div>
            </div>
        </form>
    @endif

    {{-- Step 3: review --}}
    @if ($import->status === 'validated')
        <div id="review" class="mt-6 space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card label="Rows in file" :value="$import->total_rows" icon="rows-3" />
                <x-stat-card label="Ready to generate" :value="$import->valid_rows" icon="circle-check" tone="emerald" />
                <x-stat-card label="With problems" :value="$import->failed_rows" icon="triangle-alert" tone="amber" />
            </div>

            @include('imports.partials.errors')

            <div class="card">
                <div class="card-header flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">3. Preview and generate</h2>
                        <p class="text-sm text-gray-500">First {{ min($preview->count(), \App\Services\BatchGenerationService::PREVIEW_ROWS) }} of {{ $import->valid_rows }} rows that will be created, using {{ $import->templateDesign?->name ?? $import->theme?->name }}.</p>
                    </div>
                    @if ($import->valid_rows > 0)
                        <form method="POST" action="{{ route('imports.generate', $import) }}">
                            @csrf
                            <button class="btn-primary"><x-lucide-sparkles class="h-4 w-4" /> Generate {{ $import->valid_rows }} {{ Str::plural('card', $import->valid_rows) }}</button>
                        </form>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Row</th><th>Name</th><th>Title</th><th>Company</th><th>Email</th><th>Mobile</th>@if (! empty($options['create_profiles']))<th>Profile address</th>@endif</tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($preview as $line => $row)
                                <tr>
                                    <td class="text-xs text-gray-400">{{ $line }}</td>
                                    <td class="font-medium text-gray-900">{{ $row['full_name'] }}</td>
                                    <td class="text-sm text-gray-600">{{ $row['professional_title'] }}</td>
                                    <td class="text-sm text-gray-600">{{ $row['company_name'] }}</td>
                                    <td class="text-sm text-gray-600">{{ $row['email'] }}</td>
                                    <td class="text-sm text-gray-600">{{ $row['mobile'] }}</td>
                                    @if (! empty($options['create_profiles']))<td class="text-xs text-gray-500">/card/{{ $row['profile_slug'] ?? 'from name' }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-empty-state icon="triangle-alert" title="No rows can be generated" description="Fix the problems listed above in your file and upload it again, or change the column matches." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Generating --}}
    @if ($import->status === 'processing')
        <div class="card" x-data="{ processed: {{ $import->processed_rows }}, total: {{ max(1, $import->valid_rows) }}, generated: {{ $import->generated_rows }},
                async poll() {
                    try {
                        const r = await fetch(@js(route('imports.status', $import)), { headers: { Accept: 'application/json' } });
                        const d = await r.json();
                        this.processed = d.processed; this.generated = d.generated;
                        if (d.status !== 'processing') return window.location.reload();
                    } catch (e) {}
                    setTimeout(() => this.poll(), 2000);
                } }" x-init="setTimeout(() => poll(), 1500)">
            <div class="card-body space-y-3">
                <div class="flex items-center justify-between text-sm"><span class="font-medium text-gray-900">Generating cards…</span><span class="text-gray-500"><span x-text="processed"></span> of {{ $import->valid_rows }}</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="processed" aria-valuemin="0" aria-valuemax="{{ $import->valid_rows }}"><div class="h-full rounded-full bg-brand-600 transition-all" :style="'width:' + Math.round(processed / total * 100) + '%'"></div></div>
                <p class="text-xs text-gray-500">This runs in the background, so you can leave the page.</p>
            </div>
        </div>
    @endif

    {{-- Results --}}
    @if (in_array($import->status, ['completed', 'failed'], true))
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card label="Rows in file" :value="$import->total_rows" icon="rows-3" />
                <x-stat-card label="Cards generated" :value="$import->generated_rows" icon="circle-check" tone="emerald" />
                <x-stat-card label="Not generated" :value="max(0, $import->total_rows - $import->generated_rows)" icon="triangle-alert" tone="amber" />
            </div>

            @include('imports.partials.errors')

            @if ($import->status === 'completed' && $import->generated_rows > 0)
                @include('imports.partials.export')

                <div class="card overflow-hidden">
                    <div class="card-header flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-gray-900">Generated cards</h2>
                        <a href="{{ route('imports.info', $import) }}" class="btn-secondary btn-sm"><x-lucide-sheet class="h-4 w-4" /> Customer and card list (CSV)</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Customer</th><th>Card design</th><th>Digital profile</th><th>QR link</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($results as $customer)
                                    @php
                                        $design = $customer->cardDesigns->first();
                                        $profile = $customer->digitalProfiles->first();
                                        $qr = $profile?->qrCodes->first();
                                    @endphp
                                    <tr>
                                        <td><a href="{{ route('customers.show', $customer) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $customer->full_name }}</a><span class="block text-xs text-gray-500">{{ $customer->professional_title }}{{ $customer->company ? ' · '.$customer->company->name : '' }}</span></td>
                                        <td class="text-sm">@if ($design)<a href="{{ route('designs.edit', $design) }}" class="text-brand-700 hover:underline">Edit</a> · <a href="{{ route('designs.export', $design) }}" class="text-brand-700 hover:underline">Export</a>@else<span class="text-gray-400">—</span>@endif</td>
                                        <td class="text-sm">@if ($profile)<a href="{{ route('profiles.public', $profile->slug) }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">/card/{{ $profile->slug }}</a>@else<span class="text-gray-400">—</span>@endif</td>
                                        <td class="text-xs text-gray-500">@if ($qr){{ route('qr.scan', $qr->code) }}@else—@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($results->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $results->links() }}</div>@endif
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
