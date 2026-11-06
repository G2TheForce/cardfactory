@php
    $bg = $kit->background ?? [];
    $company = $kit->company_details ?? [];
    $bgLabel = ['solid' => 'Solid colour', 'gradient' => 'Gradient', 'image' => 'Image'][$bg['type'] ?? 'solid'];
@endphp
<x-layouts.app :title="$kit->name">
    @push('head')
        @if ($fontsUrl)<link rel="stylesheet" href="{{ $fontsUrl }}">@endif
        @if ($fontFaceCss)<style>{!! $fontFaceCss !!}</style>@endif
    @endpush
    <x-slot:breadcrumbs>
        <a href="{{ route('brand-kits.index') }}" class="hover:text-gray-700">Brand kits</a><span>/</span><span class="text-gray-900">{{ $kit->name }}</span>
    </x-slot:breadcrumbs>

    <x-page-header :title="$kit->name" description="Card templates for this brand, and the kit they use.">
        <x-slot:actions>
            <a href="{{ route('brand-kits.edit', $kit) }}" class="btn-secondary"><x-lucide-pencil class="h-4 w-4" /> Edit kit</a>
            <form method="POST" action="{{ route('brand-kits.destroy', $kit) }}" onsubmit="return confirm('Delete this brand kit? Its designs keep their current colours and fonts.')">
                @csrf @method('DELETE')
                <button class="btn-ghost text-red-600 hover:bg-red-50"><x-lucide-trash-2 class="h-4 w-4" /> Delete</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-6">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Designs for this brand ({{ $designs->count() }})</h2>
                </div>
                <div class="card-body">
                    @if ($designs->isEmpty())
                        <x-empty-state icon="layout-template" title="No designs use this kit yet" description="Start a new design from a theme below, or apply the kit to a design you already have." />
                    @else
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 2xl:grid-cols-3">
                            @foreach ($designs as $design)
                                @php $previewWidth = min(100, round(63.1 * (float) $design->width_mm / max(1, (float) $design->height_mm), 1)); @endphp
                                <article x-data="{ side: 'front' }">
                                    <a href="{{ route('designs.edit', $design) }}" class="relative mx-auto block" style="width: {{ $previewWidth }}%" @mouseenter="side = 'back'" @mouseleave="side = 'front'" aria-label="Edit {{ $design->name }}">
                                        <div x-show="side === 'front'"><x-card-preview :svg="$previews[$design->id]['front']" :blank="empty($design->front['elements'] ?? [])" /></div>
                                        <div x-show="side === 'back'" x-cloak><x-card-preview :svg="$previews[$design->id]['back']" :blank="empty($design->back['elements'] ?? [])" /></div>
                                    </a>
                                    <h3 class="mt-3 truncate text-sm font-semibold text-gray-900"><a href="{{ route('designs.edit', $design) }}" class="hover:text-brand-700">{{ $design->name }}</a></h3>
                                    <p class="truncate text-xs text-gray-500">{{ $design->customer?->full_name ?? 'Template (no customer)' }} · v{{ $design->version }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="card">
                    <div class="card-header"><h2 class="card-title">New design for this brand</h2></div>
                    <form method="POST" action="{{ route('brand-kits.designs.store', $kit) }}" class="card-body space-y-4">
                        @csrf
                        <div>
                            <label for="theme" class="form-label">Start from theme <span class="text-red-500">*</span></label>
                            <select id="theme" name="theme" class="select-base" required>
                                @foreach ($themes as $theme)<option value="{{ $theme->ulid }}" @selected(old('theme') === $theme->ulid)>{{ $theme->name }}</option>@endforeach
                            </select>
                            @error('theme')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <x-field name="name" label="Design name" maxlength="120" :placeholder="$kit->name.' · theme name'" />
                        <p class="text-xs text-gray-500">The kit's colours and fonts are applied, and its background goes on the back of the card.</p>
                        <button class="btn-primary" @disabled($themes->isEmpty())><x-lucide-plus class="h-4 w-4" /> Create and open editor</button>
                    </form>
                </section>

                <section class="card">
                    <div class="card-header"><h2 class="card-title">Apply to an existing design</h2></div>
                    <form method="POST" action="{{ route('brand-kits.apply', $kit) }}" class="card-body space-y-4">
                        @csrf
                        @if ($otherDesigns->isEmpty())
                            <p class="text-sm text-gray-500">There are no other designs you can edit.</p>
                        @else
                            <div>
                                <label for="design" class="form-label">Design <span class="text-red-500">*</span></label>
                                <select id="design" name="design" class="select-base" required>
                                    @foreach ($otherDesigns as $d)<option value="{{ $d->ulid }}">{{ $d->name }}</option>@endforeach
                                </select>
                                @error('design')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <fieldset>
                                <legend class="form-label">Also use the brand background on</legend>
                                <div class="flex gap-4 text-sm">
                                    <label class="flex items-center gap-2"><input type="checkbox" name="background[]" value="front" class="checkbox-base"> Front</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="background[]" value="back" class="checkbox-base"> Back</label>
                                </div>
                            </fieldset>
                            <p class="text-xs text-gray-500">Replaces the design's palette and fonts. The layout stays as it is.</p>
                            <button class="btn-secondary"><x-lucide-paintbrush class="h-4 w-4" /> Apply kit</button>
                        @endif
                    </form>
                </section>
            </div>
        </div>

        <aside class="space-y-6">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Kit</h2></div>
                <div class="card-body space-y-5 text-sm">
                    @if ($kit->logo_path)
                        <div class="grid h-20 place-items-center rounded-lg border border-gray-200 bg-[conic-gradient(#f3f4f6_25%,#fff_0_50%,#f3f4f6_0_75%,#fff_0)] [background-size:12px_12px]"><img src="{{ Storage::disk('public')->url($kit->logo_path) }}" alt="{{ $kit->name }} logo" class="max-h-16 max-w-[80%] object-contain"></div>
                    @endif
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        @foreach (['Primary' => $kit->primary_color, 'Secondary' => $kit->secondary_color, 'Accent' => $kit->accent_color] as $label => $c)
                            <div><span class="block h-10 rounded-lg ring-1 ring-black/10" style="background: {{ $c }}"></span><span class="mt-1 block text-gray-500">{{ $label }}</span><span class="font-mono">{{ $c }}</span></div>
                        @endforeach
                    </div>
                    <dl class="space-y-2">
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Headings</dt><dd style="font-family: '{{ $kit->typography['heading'] ?? 'Inter' }}'">{{ $kit->typography['heading'] ?? 'Inter' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Body</dt><dd style="font-family: '{{ $kit->typography['body'] ?? 'Inter' }}'">{{ $kit->typography['body'] ?? 'Inter' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Background</dt><dd>{{ $bgLabel }}</dd></div>
                        @foreach (['name' => 'Company', 'tagline' => 'Tagline', 'website' => 'Website', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address'] as $key => $label)
                            @if (! empty($company[$key]))<div class="flex justify-between gap-3"><dt class="text-gray-500">{{ $label }}</dt><dd class="truncate text-right">{{ $company[$key] }}</dd></div>@endif
                        @endforeach
                    </dl>
                    @if (! empty($kit->social_links))
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($kit->social_links as $key => $url)<li><a href="{{ $url }}" target="_blank" rel="noopener nofollow" class="badge-gray">{{ \App\Support\CustomerFields::SOCIAL[$key] ?? $key }}</a></li>@endforeach
                        </ul>
                    @endif
                    <p class="text-xs text-gray-500">Company details and the logo fill a design's merge fields when its customer has none.</p>
                </div>
            </section>
        </aside>
    </div>
</x-layouts.app>
