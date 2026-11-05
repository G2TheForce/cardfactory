@php
    $editing = $kit->exists;
    $bg = $kit->background ?? [];
    $company = $kit->company_details ?? [];
    $links = $kit->social_links ?? [];
    $title = $editing ? 'Edit '.$kit->name : 'New brand kit';
    $state = [
        'primary' => old('primary_color', $kit->primary_color),
        'secondary' => old('secondary_color', $kit->secondary_color),
        'accent' => old('accent_color', $kit->accent_color),
        'heading' => old('heading_font', $kit->typography['heading'] ?? 'Inter'),
        'body' => old('body_font', $kit->typography['body'] ?? 'Inter'),
        'bgType' => old('background_type', $bg['type'] ?? 'solid'),
        'bg' => old('background_color', $bg['color'] ?? '#FFFFFF'),
        'bg2' => old('background_color2', $bg['color2'] ?? $kit->primary_color),
        'angle' => (int) old('background_angle', $bg['angle'] ?? 135),
        'company' => old('company.name', $company['name'] ?? ''),
        'tagline' => old('company.tagline', $company['tagline'] ?? ''),
    ];
@endphp
<x-layouts.app :title="$title">
    @push('head')
        @if ($fontsUrl)<link rel="stylesheet" href="{{ $fontsUrl }}">@endif
    @endpush
    <x-slot:breadcrumbs>
        <a href="{{ route('brand-kits.index') }}" class="hover:text-gray-700">Brand kits</a><span>/</span>
        @if ($editing)<a href="{{ route('brand-kits.show', $kit) }}" class="hover:text-gray-700">{{ $kit->name }}</a><span>/</span><span class="text-gray-900">Edit</span>@else<span class="text-gray-900">New</span>@endif
    </x-slot:breadcrumbs>

    <x-page-header :title="$title" description="Colours and fonts replace a design's palette when you apply the kit. Company details and the logo fill the card where the customer has none." />

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">Please fix {{ $errors->count() === 1 ? 'the highlighted field' : 'the '.$errors->count().' highlighted fields' }} below.</div>
    @endif

    <form method="POST" action="{{ $editing ? route('brand-kits.update', $kit) : route('brand-kits.store') }}" enctype="multipart/form-data"
          class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_340px]" x-data='@json($state)'>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Brand</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field name="name" label="Kit name" :value="$kit->name" required maxlength="120" class="sm:col-span-2" placeholder="e.g. Acme Corporation" />
                    <div class="sm:col-span-2" x-data="{ preview: null, remove: false }">
                        <span class="form-label">Logo</span>
                        <div class="flex items-center gap-4">
                            <div class="grid h-16 w-28 place-items-center overflow-hidden rounded-lg border border-gray-200 bg-[conic-gradient(#f3f4f6_25%,#fff_0_50%,#f3f4f6_0_75%,#fff_0)] [background-size:12px_12px]" :class="remove && 'opacity-30'">
                                <template x-if="preview"><img :src="preview" alt="" class="max-h-full max-w-full object-contain"></template>
                                @if ($kit->logo_path)
                                    <img x-show="!preview" src="{{ Storage::disk('public')->url($kit->logo_path) }}" alt="{{ $kit->name }} logo" class="max-h-full max-w-full object-contain">
                                @else
                                    <x-lucide-image x-show="!preview" class="h-6 w-6 text-gray-300" />
                                @endif
                            </div>
                            <div class="space-y-1.5">
                                <label class="btn-secondary btn-sm cursor-pointer"><x-lucide-upload class="h-3.5 w-3.5" /> {{ $kit->logo_path ? 'Change logo' : 'Upload logo' }}
                                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; remove = false">
                                </label>
                                @if ($kit->logo_path)<label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_logo" value="1" class="checkbox-base" x-model="remove"> Remove logo</label>@endif
                                <p class="form-hint mt-0">JPG, PNG or WebP up to 5 MB. A transparent PNG looks best.</p>
                                @error('logo')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Colours and typography</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-3">
                    @foreach (['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent'] as $key => $label)
                        <div>
                            <label for="{{ $key }}_color" class="form-label">{{ $label }} colour <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <input type="color" x-model="{{ $key }}" class="h-9 w-11 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick {{ strtolower($label) }} colour">
                                <input id="{{ $key }}_color" name="{{ $key }}_color" x-model="{{ $key }}" class="input-base font-mono uppercase" maxlength="7" required pattern="#[0-9A-Fa-f]{6}">
                            </div>
                            @error($key.'_color')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    @foreach (['heading' => 'Heading font', 'body' => 'Body font'] as $key => $label)
                        <div class="sm:col-span-{{ $key === 'heading' ? '2' : '1' }}">
                            <label for="{{ $key }}_font" class="form-label">{{ $label }} <span class="text-red-500">*</span></label>
                            <select id="{{ $key }}_font" name="{{ $key }}_font" x-model="{{ $key }}" class="select-base" :style="`font-family: '${ {{ $key }} }'`">
                                @foreach ($fonts as $font)<option value="{{ $font['name'] }}">{{ $font['name'] }} ({{ $font['category'] }})</option>@endforeach
                            </select>
                            @error($key.'_font')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Background</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <fieldset class="sm:col-span-3">
                        <legend class="form-label">Type</legend>
                        <div class="flex flex-wrap gap-4 text-sm">
                            @foreach (['solid' => 'Solid colour', 'gradient' => 'Gradient', 'image' => 'Image'] as $value => $label)
                                <label class="flex items-center gap-2"><input type="radio" name="background_type" value="{{ $value }}" x-model="bgType" class="h-4 w-4 border-gray-300 text-brand-600"> {{ $label }}</label>
                            @endforeach
                        </div>
                        @error('background_type')<p class="form-error">{{ $message }}</p>@enderror
                    </fieldset>
                    <div>
                        <label for="background_color" class="form-label" x-text="bgType === 'gradient' ? 'Start colour' : (bgType === 'image' ? 'Colour behind the image' : 'Colour')">Colour</label>
                        <div class="flex gap-2">
                            <input type="color" x-model="bg" class="h-9 w-11 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick background colour">
                            <input id="background_color" name="background_color" x-model="bg" class="input-base font-mono uppercase" maxlength="7" required pattern="#[0-9A-Fa-f]{6}">
                        </div>
                        @error('background_color')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="bgType === 'gradient'">
                        <label for="background_color2" class="form-label">End colour</label>
                        <div class="flex gap-2">
                            <input type="color" x-model="bg2" class="h-9 w-11 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick gradient end colour">
                            <input id="background_color2" name="background_color2" x-model="bg2" class="input-base font-mono uppercase" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
                        </div>
                        @error('background_color2')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="bgType === 'gradient'">
                        <label for="background_angle" class="form-label">Angle (<span x-text="angle"></span>°)</label>
                        <input id="background_angle" type="range" name="background_angle" min="0" max="360" step="5" x-model.number="angle" class="mt-2 w-full accent-brand-600">
                        @error('background_angle')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="bgType === 'image'" class="sm:col-span-2">
                        <span class="form-label">Background image</span>
                        <div class="flex flex-wrap items-center gap-3">
                            @if (! empty($bg['image_path']))<img src="{{ Storage::disk('public')->url($bg['image_path']) }}" alt="Current background" class="h-12 w-20 rounded object-cover ring-1 ring-gray-200">@endif
                            <input type="file" name="background_image" accept="image/jpeg,image/png,image/webp" class="text-sm">
                            @if (! empty($bg['image_path']))<label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_background_image" value="1" class="checkbox-base"> Remove image</label>@endif
                        </div>
                        <p class="form-hint">At least 300 × 200 px, up to 10 MB. Without an image the background falls back to the colour.</p>
                        @error('background_image')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Company details</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field name="company[name]" label="Company name" :value="$company['name'] ?? null" maxlength="120" x-model="company" />
                    <x-field name="company[tagline]" label="Tagline" :value="$company['tagline'] ?? null" maxlength="160" x-model="tagline" />
                    <x-field name="company[website]" type="url" label="Website" :value="$company['website'] ?? null" placeholder="https://" />
                    <x-field name="company[phone]" type="tel" label="Phone" :value="$company['phone'] ?? null" />
                    <x-field name="company[email]" type="email" label="Email" :value="$company['email'] ?? null" />
                    <x-field name="company[address]" label="Address" :value="$company['address'] ?? null" maxlength="255" />
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Social links</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach ($social as $key => $label)
                        <x-field :name="'social_links['.$key.']'" type="url" :label="$label" :value="$links[$key] ?? null" placeholder="https://" />
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Preview</h2></div>
                <div class="p-5">
                    <div class="aspect-[85.6/54] overflow-hidden rounded-md p-4 shadow-lift ring-1 ring-black/5"
                         :style="bgType === 'gradient' ? `background: linear-gradient(${angle}deg, ${bg}, ${bg2})` : `background: ${bg}`">
                        <div class="h-1.5 w-10 rounded-full" :style="`background: ${accent}`"></div>
                        <p class="mt-3 truncate text-lg font-bold leading-tight" :style="`color: ${primary}; font-family: '${heading}'`" x-text="company || 'Company name'"></p>
                        <p class="truncate text-xs" :style="`color: ${secondary}; font-family: '${body}'`" x-text="tagline || 'Your tagline here'"></p>
                        <p class="mt-4 text-sm font-semibold" :style="`color: ${secondary}; font-family: '${heading}'`">Alex Morgan</p>
                        <p class="text-[11px]" :style="`color: ${secondary}; font-family: '${body}'; opacity: .75`">Head of Sales · +1 555 010 2000</p>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">A rough guide. Real designs keep their own layout and take the kit's colours and fonts.</p>
                </div>
            </div>
            <div class="card flex flex-col gap-2 p-4">
                <button class="btn-primary">{{ $editing ? 'Save brand kit' : 'Create brand kit' }}</button>
                <a href="{{ $editing ? route('brand-kits.show', $kit) : route('brand-kits.index') }}" class="btn-secondary">Cancel</a>
            </div>
        </aside>
    </form>
</x-layouts.app>
