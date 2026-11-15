@php
    use App\Support\LandingPages\Styles;

    $panels = ['sections' => 'Sections', 'style' => 'Style', 'settings' => 'Page settings'];
    $panel = in_array(request('panel'), array_keys($panels), true) ? request('panel') : 'sections';
    foreach ($errors->keys() as $key) {
        $panel = match (strtok($key, '.')) { 'style' => 'style', 'settings', 'name', 'slug', 'is_published' => 'settings', default => $panel };
        break;
    }
    $oldSections = old('sections_json') ? json_decode(old('sections_json'), true) : null;
    $config = [
        'sections' => is_array($oldSections) ? $oldSections : $sections,
        'types' => $types,
        'icons' => $icons,
        'images' => $images,
        'style' => old('style', $style),
        'palettes' => $palettes,
        'logoPath' => $settings['logo_path'],
        'panel' => $panel,
        'uploadUrl' => route('landing-pages.images.store', $page),
        'previewUrl' => route('landing-pages.draft-preview', $page),
        'csrf' => csrf_token(),
    ];
    $bool = fn ($key, $default) => (bool) old('settings.'.$key, $settings[$key] ?? $default);
@endphp
<x-layouts.app :title="'Designer · '.$page->name">
    <x-slot:breadcrumbs>
        <a href="{{ route('landing-pages.index') }}" class="hover:text-gray-700">Landing pages</a><span>/</span><a href="{{ route('landing-pages.show', $page) }}" class="hover:text-gray-700">{{ $page->name }}</a><span>/</span><span class="text-gray-900">Designer</span>
    </x-slot:breadcrumbs>

    {{-- Brand kit form lives outside the main form (forms can't nest); its controls use form="brand-kit-form". --}}
    <form id="brand-kit-form" method="POST" action="{{ route('landing-pages.brand-kit', $page) }}" class="hidden">@csrf</form>

    <form x-ref="form" method="POST" action="{{ route('landing-pages.update', $page) }}" x-data="landingEditor(@js($config))" @submit="saving = true"
          @keydown.ctrl.s.prevent.window="save()" @keydown.meta.s.prevent.window="save()"
          @input.debounce.50ms="if ($event.target.type !== 'file' && $event.target.form === $refs.form) changed()" @change="if ($event.target.type !== 'file' && $event.target.form === $refs.form) changed()">
        @csrf @method('PUT')
        <input type="hidden" name="sections_json" :value="sectionsJson">
        <input type="hidden" name="_panel" :value="panel">

        <x-page-header :title="$page->name" :description="'Landing page designer · /p/'.$page->slug">
            <x-slot:actions>
                <span class="text-xs font-medium" :class="dirty ? 'text-amber-600' : 'text-gray-400'" x-text="dirty ? 'Unsaved changes' : 'All changes saved'">All changes saved</span>
                <a href="{{ route('landing-pages.show', $page) }}" class="btn-secondary"><x-lucide-arrow-left class="h-4 w-4" /> Back</a>
                <a href="{{ route('landing-pages.preview', $page) }}" target="_blank" rel="noopener" class="btn-secondary"><x-lucide-external-link class="h-4 w-4" /> Preview</a>
                <button type="submit" class="btn-primary" :disabled="saving"><x-lucide-save class="h-4 w-4" /> <span x-text="saving ? 'Saving…' : 'Save'">Save</span></button>
            </x-slot:actions>
        </x-page-header>

        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                <p class="font-semibold">Some changes couldn't be saved:</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[440px_1fr]">
            {{-- Controls --}}
            <div class="card self-start">
                <nav class="flex gap-1 border-b border-gray-100 px-3" role="tablist" aria-label="Designer panels">
                    @foreach ($panels as $key => $label)
                        <button type="button" role="tab" :aria-selected="panel === '{{ $key }}'" @click="panel = '{{ $key }}'"
                                class="-mb-px whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition"
                                :class="panel === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800'">{{ $label }}</button>
                    @endforeach
                </nav>

                <p x-show="uploadError" x-cloak class="mx-4 mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" x-text="uploadError"></p>

                {{-- Sections --}}
                <div x-show="panel === 'sections'" class="space-y-2 p-4">
                    <template x-for="(section, i) in sections" :key="section.id">
                        <div class="rounded-xl border transition" :class="open === section.id ? 'border-brand-300 shadow-soft' : 'border-gray-200'">
                            <div class="flex items-center gap-2 px-3 py-2.5">
                                <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="toggle(section.id)" :aria-expanded="open === section.id">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gray-100 text-gray-500" :class="section.hidden && 'opacity-40'">
                                        @foreach ($types as $type => $schema)
                                            <x-dynamic-component :component="'lucide-'.$schema['icon']" class="h-4 w-4" x-show="section.type === '{{ $type }}'" />
                                        @endforeach
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-gray-900" :class="section.hidden && 'text-gray-400 line-through'" x-text="label(section)"></span>
                                        <span class="block truncate text-xs text-gray-500" x-text="summary(section)"></span>
                                    </span>
                                </button>
                                <div class="flex shrink-0 items-center">
                                    <button type="button" class="btn-ghost btn-sm px-1.5" title="Move up" @click="move(i, -1)" :disabled="i === 0"><x-lucide-chevron-up class="h-4 w-4" /><span class="sr-only">Move up</span></button>
                                    <button type="button" class="btn-ghost btn-sm px-1.5" title="Move down" @click="move(i, 1)" :disabled="i === sections.length - 1"><x-lucide-chevron-down class="h-4 w-4" /><span class="sr-only">Move down</span></button>
                                    <button type="button" class="btn-ghost btn-sm px-1.5" :title="section.hidden ? 'Show section' : 'Hide section'" @click="section.hidden = ! section.hidden">
                                        <x-lucide-eye class="h-4 w-4" x-show="! section.hidden" /><x-lucide-eye-off class="h-4 w-4" x-show="section.hidden" x-cloak /><span class="sr-only">Show or hide</span>
                                    </button>
                                    <button type="button" class="btn-ghost btn-sm px-1.5" title="Duplicate" @click="duplicate(i)"><x-lucide-copy class="h-4 w-4" /><span class="sr-only">Duplicate</span></button>
                                    <button type="button" class="btn-ghost btn-sm px-1.5 text-gray-400 hover:text-red-600" title="Delete" @click="remove(i)"><x-lucide-trash-2 class="h-4 w-4" /><span class="sr-only">Delete</span></button>
                                </div>
                            </div>

                            <div x-show="open === section.id" x-collapse class="space-y-4 border-t border-gray-100 px-3 py-4">
                                <template x-for="field in fields(section.type)" :key="field.name">
                                    <div>@include('landing-pages.partials.field', ['target' => 'section.data', 'keyExpr' => "section.id + '-' + field.name"])</div>
                                </template>

                                <template x-if="types[section.type].items">
                                    <div class="space-y-2">
                                        <p class="form-label mb-0" x-text="types[section.type].items.label + 's'"></p>
                                        <template x-for="(item, j) in section.data.items" :key="section.id + '-item-' + j">
                                            <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-3" x-data="{ expanded: false }">
                                                <div class="flex items-center gap-2">
                                                    <button type="button" class="min-w-0 flex-1 truncate text-left text-sm font-medium text-gray-800" @click="expanded = ! expanded"
                                                            x-text="(item.title || item.name || item.question || item.quote || item.caption || item.label || item.value || (types[section.type].items.label + ' ' + (j + 1)))"></button>
                                                    <button type="button" class="btn-ghost btn-sm px-1" title="Move up" @click="moveItem(section, j, -1)" :disabled="j === 0"><x-lucide-chevron-up class="h-3.5 w-3.5" /><span class="sr-only">Move up</span></button>
                                                    <button type="button" class="btn-ghost btn-sm px-1" title="Move down" @click="moveItem(section, j, 1)" :disabled="j === section.data.items.length - 1"><x-lucide-chevron-down class="h-3.5 w-3.5" /><span class="sr-only">Move down</span></button>
                                                    <button type="button" class="btn-ghost btn-sm px-1 text-gray-400 hover:text-red-600" title="Remove" @click="removeItem(section, j)"><x-lucide-x class="h-3.5 w-3.5" /><span class="sr-only">Remove</span></button>
                                                    <button type="button" class="btn-ghost btn-sm px-1" @click="expanded = ! expanded" :title="expanded ? 'Collapse' : 'Edit'"><x-lucide-pencil class="h-3.5 w-3.5" /><span class="sr-only">Edit</span></button>
                                                </div>
                                                <div x-show="expanded" x-collapse class="mt-3 space-y-3">
                                                    <template x-for="field in itemFields(section.type)" :key="field.name">
                                                        <div>@include('landing-pages.partials.field', ['target' => 'item', 'keyExpr' => "section.id + '-' + j + '-' + field.name"])</div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <button type="button" class="btn-secondary btn-sm w-full" @click="addItem(section)" x-show="canAddItem(section)">
                                            <x-lucide-plus class="h-4 w-4" /> <span x-text="'Add ' + types[section.type].items.label.toLowerCase()"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="pt-2">
                        <button type="button" class="btn-secondary w-full" @click="adding = ! adding" :aria-expanded="adding"><x-lucide-plus class="h-4 w-4" /> Add section</button>
                        <div x-show="adding" x-collapse class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ($types as $type => $schema)
                                <button type="button" class="flex items-start gap-2.5 rounded-xl border border-gray-200 p-3 text-left transition hover:border-brand-300 hover:bg-brand-50/40" @click="add('{{ $type }}')">
                                    <x-dynamic-component :component="'lucide-'.$schema['icon']" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" />
                                    <span><span class="block text-sm font-semibold text-gray-900">{{ $schema['label'] }}</span><span class="block text-[11px] leading-snug text-gray-500">{{ $schema['description'] }}</span></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Style --}}
                <div x-show="panel === 'style'" x-cloak class="space-y-6 p-4">
                    <input type="hidden" name="style[palette]" :value="style.palette">
                    <fieldset>
                        <legend class="form-label">Colour palette</legend>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach ($palettes as $key => $palette)
                                <button type="button" class="rounded-xl border p-1.5 text-center transition" :class="style.palette === '{{ $key }}' ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-gray-200 hover:border-gray-300'" @click="usePalette('{{ $key }}')">
                                    <span class="flex h-9 overflow-hidden rounded-lg ring-1 ring-black/5">
                                        <span class="flex-1" style="background: {{ $palette['primary'] }}"></span>
                                        <span class="flex-1" style="background: {{ $palette['accent'] }}"></span>
                                        <span class="w-3" style="background: {{ $palette['mode'] === 'dark' ? '#0b0f19' : '#ffffff' }}"></span>
                                    </span>
                                    <span class="mt-1 block truncate text-[11px] font-semibold text-gray-700">{{ $palette['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-4">
                        @foreach (['primary' => 'Main colour', 'accent' => 'Accent colour'] as $key => $label)
                            <div>
                                <label class="form-label" for="style-{{ $key }}">{{ $label }}</label>
                                <div class="flex gap-2">
                                    <input type="color" x-model="style.{{ $key }}" class="h-10 w-11 shrink-0 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick {{ strtolower($label) }}">
                                    <input id="style-{{ $key }}" name="style[{{ $key }}]" x-model="style.{{ $key }}" class="input-base font-mono" maxlength="7">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="-mt-4 form-hint">Main: bands, stats and the call to action. Accent: buttons, icons and highlights.</p>

                    <div>
                        <span class="form-label">Background</span>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (Styles::MODES as $key => $label)
                                <label class="cursor-pointer rounded-xl border border-gray-200 px-3 py-2 text-center text-sm font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                    <input type="radio" name="style[mode]" value="{{ $key }}" class="sr-only" x-model="style.mode">{{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <span class="form-label">Fonts</span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach (Styles::FONTS as $key => $font)
                                <label class="cursor-pointer rounded-xl border border-gray-200 px-3 py-2.5 text-center has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="style[font]" value="{{ $key }}" class="sr-only" x-model="style.font">
                                    <span class="block text-lg leading-none text-gray-900" style="font-family: {{ $font['heading'] }}">Aa</span>
                                    <span class="mt-1 block text-[11px] font-semibold text-gray-600">{{ $font['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <span class="form-label">Buttons</span>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (Styles::BUTTONS as $key => $label)
                                <label class="cursor-pointer rounded-xl border border-gray-200 px-3 py-2.5 text-center has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="style[buttons]" value="{{ $key }}" class="sr-only" x-model="style.buttons">
                                    <span class="mx-auto block h-5 w-16 bg-gray-800" style="border-radius: {{ Styles::RADII[$key] }}"></span>
                                    <span class="mt-1 block text-[11px] font-semibold text-gray-600">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="style[animations]" value="0">
                        <input type="checkbox" name="style[animations]" value="1" class="checkbox-base" x-model="style.animations"> Animate sections as visitors scroll
                    </label>

                    <section class="rounded-xl border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Brand kit</h3>
                        <p class="mt-0.5 text-xs text-gray-500">Applies the kit's colours, logo and company contact details. Save your edits first: this reloads the designer.</p>
                        @if ($brandKits->isEmpty())
                            <p class="mt-3 text-sm text-gray-500">No brand kits yet. <a href="{{ route('brand-kits.create') }}" class="font-semibold text-brand-600 hover:text-brand-700">Create one</a></p>
                        @else
                            <div class="mt-3 flex gap-2">
                                <select name="brand_kit" form="brand-kit-form" class="select-base" aria-label="Brand kit">
                                    @foreach ($brandKits as $kit)
                                        <option value="{{ $kit->ulid }}" @selected($page->brandKit?->ulid === $kit->ulid)>{{ $kit->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" form="brand-kit-form" class="btn-secondary shrink-0" @click="if (dirty && ! confirm('Apply the brand kit? Unsaved changes will be lost.')) $event.preventDefault(); else saving = true">Apply</button>
                            </div>
                        @endif
                    </section>
                </div>

                {{-- Page settings --}}
                <div x-show="panel === 'settings'" x-cloak class="space-y-5 p-4">
                    <div>
                        <label for="name" class="form-label">Page name</label>
                        <input id="name" name="name" value="{{ old('name', $page->name) }}" class="input-base" maxlength="120" required>
                        @error('name')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="slug" class="form-label">Web address</label>
                        <div class="flex rounded-lg shadow-soft">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-xs text-gray-500">/p/</span>
                            <input id="slug" name="slug" value="{{ old('slug', $page->slug) }}" class="input-base rounded-l-none shadow-none" maxlength="60" required pattern="[a-z0-9][a-z0-9\-]{1,58}[a-z0-9]">
                        </div>
                        @error('slug')<p class="form-error">{{ $message }}</p>@enderror
                        <p class="form-hint">Changing it breaks links you've already shared.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                        <input type="hidden" name="is_published" value="0">
                        <input type="checkbox" name="is_published" value="1" class="checkbox-base" @checked(old('is_published', $page->is_published))> Published (visible at the web address)
                    </label>

                    <div class="border-t border-gray-100 pt-5">
                        <span class="form-label">Logo</span>
                        <input type="hidden" name="settings[logo_path]" :value="logoPath">
                        <div class="flex items-center gap-3">
                            <span class="grid h-14 w-28 place-items-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-50">
                                <img x-show="imageUrl(logoPath)" :src="imageUrl(logoPath)" alt="Logo" class="max-h-12 max-w-24 object-contain">
                                <span x-show="! imageUrl(logoPath)" class="text-[11px] text-gray-400">No logo</span>
                            </span>
                            <label class="btn-secondary btn-sm cursor-pointer">
                                <x-lucide-upload class="h-3.5 w-3.5" /> <span x-text="uploading === 'logo' ? 'Uploading…' : 'Upload'">Upload</span>
                                <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" @change="upload($event, 'logo', 'logo')">
                            </label>
                            <button type="button" class="btn-ghost btn-sm" x-show="logoPath" @click="logoPath = ''; changed()">Remove</button>
                        </div>
                        <input type="hidden" name="settings[remove_logo]" :value="logoPath ? 0 : 1">
                        <p class="form-hint">Shown in the top bar instead of the company name.</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="settings[show_nav]" value="0">
                        <input type="checkbox" name="settings[show_nav]" value="1" class="checkbox-base" @checked($bool('show_nav', true))> Show the top navigation bar
                    </label>
                    <div>
                        <label for="nav_cta" class="form-label">Top bar button</label>
                        <input id="nav_cta" name="settings[nav_cta]" value="{{ old('settings.nav_cta', $settings['nav_cta']) }}" class="input-base" maxlength="30" placeholder="Get in touch">
                        <p class="form-hint">Links to the contact section.</p>
                    </div>

                    <div class="space-y-4 border-t border-gray-100 pt-5">
                        <h3 class="text-sm font-semibold text-gray-900">Search & sharing</h3>
                        <div>
                            <label for="seo_title" class="form-label">Page title</label>
                            <input id="seo_title" name="settings[seo_title]" value="{{ old('settings.seo_title', $settings['seo_title']) }}" class="input-base" maxlength="70" placeholder="Company · headline">
                            @error('settings.seo_title')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="seo_description" class="form-label">Description</label>
                            <textarea id="seo_description" name="settings[seo_description]" rows="2" class="input-base" maxlength="170" placeholder="Uses the hero intro when empty">{{ old('settings.seo_description', $settings['seo_description']) }}</textarea>
                            @error('settings.seo_description')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="settings[allow_indexing]" value="0">
                            <input type="checkbox" name="settings[allow_indexing]" value="1" class="checkbox-base" @checked($bool('allow_indexing', true))> Let search engines list this page
                        </label>
                        <div>
                            <label for="footer_text" class="form-label">Footer text</label>
                            <input id="footer_text" name="settings[footer_text]" value="{{ old('settings.footer_text', $settings['footer_text']) }}" class="input-base" maxlength="200" placeholder="© {{ now()->year }} Company. All rights reserved.">
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-gray-100 pt-5">
                        <h3 class="text-sm font-semibold text-gray-900">Enquiries</h3>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="settings[notify_leads]" value="0">
                            <input type="checkbox" name="settings[notify_leads]" value="1" class="checkbox-base" @checked($bool('notify_leads', true))> Email me when someone sends the contact form
                        </label>
                        <div>
                            <label for="notify_email" class="form-label">Send to</label>
                            <input id="notify_email" type="email" name="settings[notify_email]" value="{{ old('settings.notify_email', $settings['notify_email']) }}" class="input-base" maxlength="190" placeholder="Contact section email, else the customer's">
                            @error('settings.notify_email')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Live preview --}}
            <div class="card overflow-hidden xl:sticky xl:top-6 xl:self-start">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        Live preview
                        <x-lucide-loader-circle class="h-4 w-4 animate-spin text-gray-400" x-show="previewing" x-cloak />
                    </p>
                    <div class="flex rounded-lg bg-gray-100 p-0.5" role="group" aria-label="Preview size">
                        <button type="button" class="rounded-md px-2.5 py-1 text-xs font-semibold" :class="device === 'desktop' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'" @click="device = 'desktop'"><x-lucide-monitor class="inline h-3.5 w-3.5" /> Desktop</button>
                        <button type="button" class="rounded-md px-2.5 py-1 text-xs font-semibold" :class="device === 'mobile' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'" @click="device = 'mobile'"><x-lucide-smartphone class="inline h-3.5 w-3.5" /> Phone</button>
                    </div>
                </div>
                <div class="bg-gray-100 p-3">
                    <iframe name="lp-preview" x-ref="preview" title="Live preview" @load="previewLoaded()"
                            src="{{ route('landing-pages.preview', $page) }}"
                            class="mx-auto block h-[calc(100vh-11rem)] min-h-[560px] rounded-lg bg-white shadow-soft ring-1 ring-black/5 transition-all"
                            :class="device === 'mobile' ? 'w-[390px] max-w-full' : 'w-full'"></iframe>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
