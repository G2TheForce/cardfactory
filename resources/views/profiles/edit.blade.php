@php
    use App\Support\ProfileThemes;
    use App\Support\CustomerFields;
    $tabs = ['appearance' => 'Appearance', 'social' => 'Social', 'layout' => 'Layout', 'buttons' => 'Buttons', 'portfolio' => 'Portfolio', 'settings' => 'Settings'];
    $tab = in_array(request('tab'), array_keys($tabs), true) ? request('tab') : 'appearance';
    // Send people back to the tab with the first error.
    $errorTabs = ['social_manual' => 'social', 'slug' => 'settings', 'is_active' => 'settings', 'card_design' => 'settings', 'layout' => 'layout', 'ctas' => 'buttons', 'portfolio' => 'portfolio'];
    foreach ($errors->keys() as $key) {
        if (isset($errorTabs[strtok($key, '.')])) { $tab = $errorTabs[strtok($key, '.')]; break; }
    }
    $ctas = old('ctas', $settings['ctas']);
    $sectionLabels = ProfileThemes::SECTIONS;
@endphp
<x-layouts.app :title="'Edit profile · '.$profile->customer->full_name">
    <x-slot:breadcrumbs>
        <a href="{{ route('profiles.index') }}" class="hover:text-gray-700">Digital profiles</a><span>/</span><a href="{{ route('profiles.show', $profile) }}" class="hover:text-gray-700">{{ $profile->customer->full_name }}</a><span>/</span><span class="text-gray-900">Edit</span>
    </x-slot:breadcrumbs>

    <x-page-header :title="'Edit profile'" :description="$profile->customer->full_name.' · /card/'.$profile->slug">
        <x-slot:actions>
            <a href="{{ route('profiles.show', $profile) }}" class="btn-secondary"><x-lucide-arrow-left class="h-4 w-4" /> Back</a>
        </x-slot:actions>
    </x-page-header>

    <p class="mb-6 -mt-4 text-sm text-gray-500">
        Contact details come from the customer record.
        @can('update', $profile->customer)<a href="{{ route('customers.edit', $profile->customer) }}" class="font-semibold text-brand-600 hover:text-brand-700">Edit details and what's shown</a>@endcan
    </p>

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form method="POST" action="{{ route('profiles.update', $profile) }}" enctype="multipart/form-data" class="card"
              x-data="{
                  tab: @js($tab),
                  theme: @js(old('profile_theme', $profile->profile_theme)),
                  accent: @js(old('accent', $settings['accent'])),
                  background: @js(old('background', $settings['background'] ?? '')),
                  themeBackgrounds: @js(collect(ProfileThemes::THEMES)->map(fn ($t) => $t['colors']['bg'])),
                  themeAccents: @js(collect(ProfileThemes::THEMES)->map(fn ($t) => $t['accent'])),
                  layout: @js(collect(old('layout', $layout))->map(fn ($r) => ['key' => $r['key'], 'enabled' => filter_var($r['enabled'], FILTER_VALIDATE_BOOL)])->values()),
                  labels: @js($sectionLabels),
                  ctas: @js(array_values($ctas)),
                  move(i, d) { const j = i + d; if (j < 0 || j >= this.layout.length) return; const r = this.layout.splice(i, 1)[0]; this.layout.splice(j, 0, r); },
              }">
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="1">
            <input type="hidden" name="_tab" :value="tab">

            <nav class="flex gap-1 overflow-x-auto border-b border-gray-100 px-3" role="tablist" aria-label="Profile settings">
                @foreach ($tabs as $key => $label)
                    <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'"
                            class="-mb-px whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition"
                            :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800'">{{ $label }}</button>
                @endforeach
            </nav>

            <div class="card-body">
                {{-- Appearance --}}
                <div x-show="tab === 'appearance'" class="space-y-6">
                    @foreach (ProfileThemes::byCategory() as $category)
                        <fieldset>
                            <legend class="form-label">{{ $category['label'] }} themes</legend>
                            <p class="-mt-1 mb-2 text-xs text-gray-500">{{ $category['description'] }}</p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                @foreach ($category['themes'] as $key => $theme)
                                    <label class="cursor-pointer rounded-xl border border-gray-200 p-2 transition has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                                        <input type="radio" name="profile_theme" value="{{ $key }}" class="sr-only" x-model="theme" @change="accent = themeAccents[theme]; background = ''">
                                        @include('profiles.partials.theme-swatch', ['theme' => $theme])
                                        <span class="mt-2 block text-center text-xs font-semibold text-gray-800">{{ $theme['name'] }}</span>
                                        <span class="block text-center text-[11px] leading-tight text-gray-500">{{ $theme['description'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="form-label" for="accent">Accent colour</label>
                            <div class="flex gap-2">
                                <input type="color" x-model="accent" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick accent colour">
                                <input id="accent" name="accent" x-model="accent" class="input-base font-mono" maxlength="7">
                            </div>
                            @error('accent')<p class="form-error">{{ $message }}</p>@enderror
                            <p class="form-hint">Buttons, icons and the header. Text on buttons switches to dark automatically on light colours.</p>
                        </div>
                        <fieldset>
                            <legend class="form-label">Button shape</legend>
                            <div class="flex gap-2">
                                @foreach (ProfileThemes::BUTTON_STYLES as $key => $label)
                                    <label class="flex-1 cursor-pointer border border-gray-200 px-3 py-2 text-center text-sm font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700 {{ ['rounded' => 'rounded-xl', 'pill' => 'rounded-full', 'square' => 'rounded-md'][$key] }}">
                                        <input type="radio" name="button_style" value="{{ $key }}" class="sr-only" @checked(old('button_style', $settings['button_style']) === $key)>{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <div>
                            <label class="form-label" for="background">Page background</label>
                            <div class="flex gap-2">
                                <input type="color" :value="background || themeBackgrounds[theme]" @input="background = $event.target.value" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick background colour">
                                <input id="background" name="background" x-model="background" class="input-base font-mono" maxlength="7" :placeholder="themeBackgrounds[theme]">
                            </div>
                            @error('background')<p class="form-error">{{ $message }}</p>@enderror
                            <p class="form-hint">Leave empty for the theme's own. Text colours adapt to light or dark.</p>
                        </div>
                        <div>
                            <label class="form-label" for="header_style">Header</label>
                            <select id="header_style" name="header_style" class="select-base">
                                <option value="">Theme default</option>
                                @foreach (ProfileThemes::HEADER_STYLES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('header_style', $settings['header_style']) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="font">Font</label>
                            <select id="font" name="font" class="select-base">
                                <option value="">Theme default</option>
                                @foreach (ProfileThemes::FONTS as $key => $font)
                                    <option value="{{ $key }}" @selected(old('font', $settings['font']) === $key) style="font-family: {{ $font['stack'] }}">{{ $font['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <x-field name="headline" label="Headline" :value="$settings['headline']" maxlength="120" hint="A short line under the name, e.g. what you help people with." />

                    <div>
                        <label class="form-label" for="cover">Cover image</label>
                        @if ($settings['cover_path'])
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ Storage::disk('public')->url($settings['cover_path']) }}" alt="" class="h-16 w-32 rounded-lg object-cover ring-1 ring-gray-200">
                                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="remove_cover" value="1" class="checkbox-base"> Remove</label>
                            </div>
                        @endif
                        <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
                        @error('cover')<p class="form-error">{{ $message }}</p>@enderror
                        <p class="form-hint">Shown in the header (JPG, PNG or WebP, up to 5 MB). Wide images work best.</p>
                    </div>
                </div>

                {{-- Social --}}
                <div x-show="tab === 'social'" x-cloak class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <fieldset>
                            <legend class="form-label">Social links look like</legend>
                            <div class="flex gap-2">
                                @foreach (ProfileThemes::SOCIAL_STYLES as $key => $label)
                                    <label class="flex-1 cursor-pointer rounded-xl border border-gray-200 px-3 py-2 text-center text-sm font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                        <input type="radio" name="social_style" value="{{ $key }}" class="sr-only" @checked(old('social_style', $settings['social_style']) === $key)>{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="form-hint">Follow buttons are full-width, in each network's colours, with "Subscribe" or "Follow" and the follower count.</p>
                        </fieldset>
                        <label class="flex items-start gap-3 sm:pt-7">
                            <input type="hidden" name="show_follower_counts" value="0">
                            <input type="checkbox" name="show_follower_counts" value="1" class="checkbox-base mt-0.5" @checked(old('show_follower_counts', $settings['show_follower_counts']))>
                            <span><span class="block text-sm font-medium text-gray-900">Show follower counts</span><span class="block text-xs text-gray-500">Only on Follow buttons, and only for networks with a known count.</span></span>
                        </label>
                    </div>

                    @php $socialLinks = array_filter($profile->customer->social_links ?? []); @endphp
                    @if ($socialLinks)
                        <div class="overflow-hidden rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-2.5">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Follower counts</span>
                                <button type="submit" form="refresh-social" class="btn-secondary btn-sm"><x-lucide-refresh-cw class="h-3.5 w-3.5" /> Refresh now</button>
                            </div>
                            @foreach (CustomerFields::SOCIAL as $network => $label)
                                @continue(! isset($socialLinks[$network]))
                                @php
                                    $row = $settings['social_counts'][$network] ?? [];
                                    $reason = $social->unavailableReason($network);
                                @endphp
                                <div class="grid items-center gap-3 border-b border-gray-100 px-4 py-3 last:border-0 sm:grid-cols-[1fr_160px]">
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                            <x-dynamic-component :component="'lucide-'.(\App\Support\Scene\IconLibrary::SOCIAL[$network] ?? 'link')" class="h-4 w-4 text-gray-400" />
                                            {{ $label }}
                                            @if (isset($row['count']))
                                                <span class="badge-green">{{ \App\Services\Social\SocialStatsService::format($row['count']) }} fetched</span>
                                            @endif
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-gray-500">{{ $socialLinks[$network] }}</p>
                                        @if (! empty($row['error']))
                                            <p class="mt-0.5 text-xs text-amber-700">{{ $row['error'] }}</p>
                                        @elseif ($reason)
                                            <p class="mt-0.5 text-xs text-gray-500">{{ $reason }}</p>
                                        @elseif (! empty($row['fetched_at']))
                                            <p class="mt-0.5 text-xs text-gray-500">Updated {{ \Illuminate\Support\Carbon::parse($row['fetched_at'])->diffForHumans() }}. Refreshes daily.</p>
                                        @else
                                            <p class="mt-0.5 text-xs text-gray-500">Fetched automatically. Press Refresh now to get it straight away.</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="sr-only" for="social_manual_{{ $network }}">{{ $label }} count</label>
                                        <input id="social_manual_{{ $network }}" name="social_manual[{{ $network }}]" value="{{ old('social_manual.'.$network, $row['manual'] ?? '') }}" inputmode="decimal" maxlength="14"
                                               class="input-base" placeholder="{{ $reason ? 'e.g. 12.4K' : 'Override, e.g. 12.4K' }}">
                                        @error('social_manual.'.$network)<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500">A number you type in is shown instead of the fetched one. Leave it empty to use the fetched count.</p>
                    @else
                        <div class="rounded-xl border border-dashed border-gray-200 px-6 py-8 text-center text-sm text-gray-500">
                            No social links yet.
                            @can('update', $profile->customer)<a href="{{ route('customers.edit', $profile->customer) }}" class="font-semibold text-brand-600 hover:text-brand-700">Add them on the customer record</a>@endcan
                        </div>
                    @endif
                </div>

                {{-- Layout --}}
                <div x-show="tab === 'layout'" x-cloak>
                    <p class="mb-4 text-sm text-gray-500">Choose which sections appear and in what order. Empty sections are skipped automatically.</p>
                    <ol class="space-y-2">
                        <template x-for="(row, i) in layout" :key="row.key">
                            <li class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2.5" :class="row.enabled ? '' : 'opacity-60'">
                                <input type="hidden" :name="`layout[${i}][key]`" :value="row.key">
                                <input type="hidden" :name="`layout[${i}][enabled]`" :value="row.enabled ? 1 : 0">
                                <span class="w-5 text-center text-xs font-semibold tabular-nums text-gray-400" x-text="i + 1"></span>
                                <span class="flex-1 text-sm font-medium text-gray-800" x-text="labels[row.key]"></span>
                                <button type="button" class="btn-ghost btn-sm" @click="move(i, -1)" :disabled="i === 0" :aria-label="`Move ${labels[row.key]} up`"><x-lucide-arrow-up class="h-4 w-4" /></button>
                                <button type="button" class="btn-ghost btn-sm" @click="move(i, 1)" :disabled="i === layout.length - 1" :aria-label="`Move ${labels[row.key]} down`"><x-lucide-arrow-down class="h-4 w-4" /></button>
                                <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" class="checkbox-base" x-model="row.enabled"> Show</label>
                            </li>
                        </template>
                    </ol>
                    @error('layout')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                {{-- Buttons --}}
                <div x-show="tab === 'buttons'" x-cloak class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm text-gray-500">Big buttons for the things you want visitors to do: book a call, see a menu, leave a review.</p>
                        <button type="button" class="btn-secondary btn-sm shrink-0" @click="ctas.push({ label: '', url: '', style: 'solid' })" x-show="ctas.length < {{ ProfileThemes::MAX_CTAS }}"><x-lucide-plus class="h-4 w-4" /> Add button</button>
                    </div>
                    <template x-for="(cta, i) in ctas" :key="i">
                        <div class="grid gap-3 rounded-xl border border-gray-200 p-3 sm:grid-cols-[1fr_1.5fr_auto_auto] sm:items-end">
                            <div><label class="text-xs font-medium text-gray-600">Label</label><input class="input-base" :name="`ctas[${i}][label]`" x-model="cta.label" maxlength="40" placeholder="Book a meeting"></div>
                            <div><label class="text-xs font-medium text-gray-600">Link</label><input class="input-base" type="url" :name="`ctas[${i}][url]`" x-model="cta.url" placeholder="https://"></div>
                            <div><label class="text-xs font-medium text-gray-600">Style</label>
                                <select class="select-base" :name="`ctas[${i}][style]`" x-model="cta.style"><option value="solid">Filled</option><option value="outline">Outline</option></select>
                            </div>
                            <button type="button" class="btn-ghost btn-sm text-red-600" @click="ctas.splice(i, 1)" aria-label="Remove button"><x-lucide-trash-2 class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <p x-show="ctas.length === 0" class="rounded-xl border border-dashed border-gray-200 py-8 text-center text-sm text-gray-400">No custom buttons.</p>
                    @foreach ($errors->get('ctas*') as $messages)
                        @foreach ((array) $messages as $message)<p class="form-error">{{ $message }}</p>@endforeach
                    @endforeach
                </div>

                {{-- Portfolio --}}
                <div x-show="tab === 'portfolio'" x-cloak class="space-y-4">
                    <p class="text-sm text-gray-500">Up to {{ ProfileThemes::MAX_PORTFOLIO }} images of your work, products or space.</p>
                    @if ($settings['portfolio'])
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($settings['portfolio'] as $item)
                                <div class="rounded-xl border border-gray-200 p-2">
                                    <img src="{{ Storage::disk('public')->url($item['path']) }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                                    <input name="portfolio_captions[{{ $item['id'] }}]" value="{{ $item['caption'] }}" placeholder="Caption" maxlength="120" class="input-base mt-2 py-1.5 text-xs">
                                    <label class="mt-1.5 flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="portfolio_remove[]" value="{{ $item['id'] }}" class="checkbox-base"> Remove</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div>
                        <label class="form-label" for="portfolio">Add images</label>
                        <input id="portfolio" type="file" name="portfolio[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
                        @foreach ($errors->get('portfolio*') as $messages)
                            @foreach ((array) $messages as $message)<p class="form-error">{{ $message }}</p>@endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Settings --}}
                <div x-show="tab === 'settings'" x-cloak class="space-y-5">
                    <div>
                        <label for="slug" class="form-label">Web address</label>
                        <div class="flex rounded-lg shadow-soft">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">{{ preg_replace('#^https?://#', '', url('/card')) }}/</span>
                            <input id="slug" name="slug" value="{{ old('slug', $profile->slug) }}" required maxlength="60" class="input-base rounded-l-none shadow-none">
                        </div>
                        @error('slug')<p class="form-error">{{ $message }}</p>@enderror
                        <p class="form-hint">QR codes and NFC cards keep working if you change this; old shared links to /card/… will not.</p>
                    </div>

                    @foreach ([
                        'is_active' => ['Profile is live', 'When off, visitors, QR codes and NFC cards get a "not available" page.', $profile->is_active],
                        'show_save_contact' => ['Show "Save contact" button', 'Downloads a vCard with the details shown on the profile.', $settings['show_save_contact']],
                        'show_qr' => ['Show QR code button', 'Lets visitors show the QR to someone else in person.', $settings['show_qr']],
                        'map_embed' => ['Embed a map', 'Adds a "Show map" button that loads Google Maps only when tapped.', $settings['map_embed']],
                        'allow_indexing' => ['Let search engines list this profile', 'Off by default to protect privacy.', $settings['allow_indexing']],
                    ] as $name => [$label, $hint, $value])
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" name="{{ $name }}" value="1" class="checkbox-base mt-0.5" @checked(old($name, $value))>
                            <span><span class="block text-sm font-medium text-gray-900">{{ $label }}</span><span class="block text-xs text-gray-500">{{ $hint }}</span></span>
                        </label>
                    @endforeach

                    <div>
                        <label for="card_design" class="form-label">Linked card design</label>
                        <select id="card_design" name="card_design" class="select-base">
                            <option value="">None</option>
                            @foreach ($designs as $design)
                                <option value="{{ $design->ulid }}" @selected(old('card_design', $profile->cardDesign?->ulid) === $design->ulid)>{{ $design->name }}</option>
                            @endforeach
                        </select>
                        @error('card_design')<p class="form-error">{{ $message }}</p>@enderror
                        <p class="form-hint">The printed card that goes with this profile. Its QR code opens this profile.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                <a href="{{ route('profiles.show', $profile) }}" class="btn-secondary">Cancel</a>
                <button class="btn-primary"><x-lucide-save class="h-4 w-4" /> Save profile</button>
            </div>
        </form>
        <form id="refresh-social" method="POST" action="{{ route('profiles.social.refresh', $profile) }}" class="hidden">@csrf</form>

        <aside class="xl:sticky xl:top-6 xl:self-start">
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Saved version</h2><a href="{{ route('profiles.preview', $profile) }}" target="_blank" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Full screen</a></div>
                <div class="bg-gray-50 p-4">
                    <div class="mx-auto h-[600px] w-[300px] overflow-hidden rounded-[2rem] border-[6px] border-gray-900 bg-white shadow-lift">
                        <iframe src="{{ route('profiles.preview', $profile) }}" title="Profile preview" class="h-full w-full"></iframe>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.app>
