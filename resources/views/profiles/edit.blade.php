@php
    use App\Support\ProfileThemes;
    use App\Support\CustomerFields;
    $tabs = ['appearance' => 'Appearance', 'social' => 'Social', 'layout' => 'Layout', 'buttons' => 'Buttons', 'portfolio' => 'Portfolio', 'settings' => 'Settings'];
    $tab = in_array(request('tab'), array_keys($tabs), true) ? request('tab') : 'appearance';
    // Send people back to the tab with the first error.
    $errorTabs = ['social_manual' => 'social', 'social_links' => 'social', 'slug' => 'settings', 'is_active' => 'settings', 'card_design' => 'settings', 'layout' => 'layout', 'ctas' => 'buttons', 'portfolio' => 'portfolio'];
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

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <form method="POST" action="{{ route('profiles.update', $profile) }}" enctype="multipart/form-data" class="card"
              x-data="{
                  tab: @js($tab),
                  theme: @js(old('profile_theme', $profile->profile_theme)),
                  accent: @js(old('accent', $settings['accent'])),
                  background: @js(old('background', $settings['background'] ?? '')),
                  themeBackgrounds: @js(collect(ProfileThemes::THEMES)->map(fn ($t) => $t['colors']['bg'])),
                  themeAccents: @js(collect(ProfileThemes::THEMES)->map(fn ($t) => $t['accent'])),
                  studioThemes: @js(collect(ProfileThemes::THEMES)->filter(fn ($t, $k) => ProfileThemes::isStudio($k))->keys()),
                  get isStudio() { return this.studioThemes.includes(this.theme); },
                  cardStyle: @js(old('card_style', $settings['card_style'] ?? '')),
                  effect: @js(old('effect', $settings['effect'] ?? '')),
                  glow: @js((string) old('glow', $settings['glow'] === null ? '' : (int) $settings['glow'])),
                  previewTimer: null,
                  previewing: false,
                  refreshPreview() {
                      clearTimeout(this.previewTimer);
                      this.previewTimer = setTimeout(() => {
                          const f = this.$root, files = f.querySelectorAll('input[type=file]');
                          const saved = [f.action, f.target, f.enctype];
                          files.forEach((i) => (i.disabled = true));
                          f.action = @js(route('profiles.draft-preview', $profile)); f.target = 'live-preview'; f.enctype = 'application/x-www-form-urlencoded';
                          this.previewing = true;
                          f.submit();
                          [f.action, f.target, f.enctype] = saved;
                          files.forEach((i) => (i.disabled = false));
                      }, 450);
                  },
                  layout: @js(collect(old('layout', $layout))->map(fn ($r) => ['key' => $r['key'], 'enabled' => filter_var($r['enabled'], FILTER_VALIDATE_BOOL)])->values()),
                  labels: @js($sectionLabels),
                  ctas: @js(array_values($ctas)),
                  move(i, d) { const j = i + d; if (j < 0 || j >= this.layout.length) return; const r = this.layout.splice(i, 1)[0]; this.layout.splice(j, 0, r); this.$nextTick(() => this.refreshPreview()); },
              }"
              @input.debounce.50ms="if ($event.target.type !== 'file') refreshPreview()" @change="if ($event.target.type !== 'file') refreshPreview()">
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
                <div x-show="tab === 'appearance'" class="space-y-8">
                    {{-- Themes: jump links so every category is visible without scrolling --}}
                    <nav class="-mb-4 flex flex-wrap gap-2" aria-label="Theme categories">
                        @foreach (ProfileThemes::byCategory() as $key => $category)
                            <a href="#theme-group-{{ $key }}" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:border-brand-300 hover:text-brand-700">
                                {{ $category['label'] }} <span class="text-gray-400">{{ count($category['themes']) }}</span>
                                @if ($key === 'studio')<span class="rounded-full bg-brand-100 px-1.5 text-[10px] font-bold uppercase text-brand-700">Website</span>@endif
                            </a>
                        @endforeach
                    </nav>
                    @foreach (ProfileThemes::byCategory() as $key => $category)
                        <fieldset id="theme-group-{{ $key }}" class="scroll-mt-24">
                            <legend class="text-sm font-semibold text-gray-900">{{ $category['label'] }} themes</legend>
                            <p class="mb-3 text-xs text-gray-500">{{ $category['description'] }}</p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($category['themes'] as $key => $theme)
                                    <label class="group cursor-pointer rounded-xl border border-gray-200 p-2 transition hover:border-gray-300 has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                                        <input type="radio" name="profile_theme" value="{{ $key }}" class="sr-only" x-model="theme" @change="accent = themeAccents[theme]; background = ''; cardStyle = ''; effect = ''; glow = ''">
                                        @include('profiles.partials.theme-swatch', ['theme' => $theme])
                                        <span class="mt-2 flex items-center justify-center gap-1 text-xs font-semibold text-gray-800">
                                            {{ $theme['name'] }}
                                            @if (($theme['effect'] ?? 'none') !== 'none' || ($theme['card_style'] ?? 'solid') === 'glass')<x-lucide-sparkles class="h-3 w-3 text-brand-500" />@endif
                                        </span>
                                        <span class="block text-center text-[11px] leading-tight text-gray-500">{{ $theme['description'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    {{-- Style --}}
                    <section class="space-y-5 rounded-2xl border border-gray-200 p-5">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Style</h3>
                            <p class="text-xs text-gray-500">Fine-tune the theme. "Theme default" keeps what the theme does.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="accent">Accent colour</label>
                                <div class="flex gap-2">
                                    <input type="color" x-model="accent" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick accent colour">
                                    <input id="accent" name="accent" x-model="accent" class="input-base font-mono" maxlength="7">
                                </div>
                                @error('accent')<p class="form-error">{{ $message }}</p>@enderror
                                <p class="form-hint">Buttons, icons, glows and the header gradient.</p>
                            </div>
                            <div>
                                <label class="form-label" for="background">Page colour</label>
                                <div class="flex gap-2">
                                    <input type="color" :value="background || themeBackgrounds[theme]" @input="background = $event.target.value" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-300 bg-white p-1" aria-label="Pick page colour">
                                    <input id="background" name="background" x-model="background" class="input-base font-mono" maxlength="7" :placeholder="themeBackgrounds[theme]">
                                </div>
                                @error('background')<p class="form-error">{{ $message }}</p>@enderror
                                <p class="form-hint">Leave empty for the theme's own. Text adapts to light or dark.</p>
                            </div>
                        </div>

                        <div>
                            <span class="form-label">Panels</span>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <label class="cursor-pointer rounded-xl border border-gray-200 px-3 py-2 text-center text-sm font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                    <input type="radio" name="card_style" value="" class="sr-only" x-model="cardStyle">Theme default
                                </label>
                                @foreach (ProfileThemes::CARD_STYLES as $key => $label)
                                    <label class="cursor-pointer rounded-xl border border-gray-200 px-3 py-2 text-center text-sm font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                        <input type="radio" name="card_style" value="{{ $key }}" class="sr-only" x-model="cardStyle">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <span class="form-label">Background effect</span>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                <label class="cursor-pointer rounded-xl border border-gray-200 px-2 py-2 text-center text-xs font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                    <input type="radio" name="effect" value="" class="sr-only" x-model="effect">Theme default
                                </label>
                                @foreach (ProfileThemes::EFFECTS as $key => $label)
                                    <label class="cursor-pointer rounded-xl border border-gray-200 px-2 py-2 text-center text-xs font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                        <input type="radio" name="effect" value="{{ $key }}" class="sr-only" x-model="effect">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-4">
                            <div>
                                <label class="form-label" for="glow">Neon glow</label>
                                <select id="glow" name="glow" class="select-base" x-model="glow">
                                    <option value="">Theme default</option>
                                    <option value="1">On</option>
                                    <option value="0">Off</option>
                                </select>
                            </div>
                            <div x-show="! isStudio">
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
                            <div>
                                <label class="form-label" for="button_style">Buttons</label>
                                <select id="button_style" name="button_style" class="select-base">
                                    @foreach (ProfileThemes::BUTTON_STYLES as $key => $label)
                                        <option value="{{ $key }}" @selected(old('button_style', $settings['button_style']) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    {{-- Images --}}
                    <section class="space-y-5 rounded-2xl border border-gray-200 p-5" x-data="{ bgPreview: @js($settings['background_image_path'] ? Storage::disk('public')->url($settings['background_image_path']) : null), coverPreview: @js($settings['cover_path'] ? Storage::disk('public')->url($settings['cover_path']) : null), overlay: @js((int) old('background_overlay', $settings['background_overlay'])), blur: @js((int) old('background_blur', $settings['background_blur'])) }">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Images</h3>
                            <p class="text-xs text-gray-500">Upload your own photos. They are resized and kept with the profile. JPG, PNG or WebP.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <span class="form-label">Page background image</span>
                                <label for="background_image" class="relative flex h-36 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 text-center transition hover:border-brand-400">
                                    <template x-if="bgPreview"><img :src="bgPreview" alt="" class="absolute inset-0 h-full w-full object-cover" :style="`filter: blur(${blur / 3}px)`"></template>
                                    <template x-if="bgPreview"><span class="absolute inset-0" :style="`background: rgba(0,0,0,${overlay / 100})`"></span></template>
                                    <span class="relative flex flex-col items-center gap-1 text-xs font-medium" :class="bgPreview ? 'text-white' : 'text-gray-500'">
                                        <x-lucide-image-plus class="h-6 w-6" />
                                        <span x-text="bgPreview ? 'Change image' : 'Upload background (up to 8 MB)'"></span>
                                    </span>
                                    <input id="background_image" type="file" name="background_image" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="if ($event.target.files[0]) bgPreview = URL.createObjectURL($event.target.files[0])">
                                </label>
                                @error('background_image')<p class="form-error">{{ $message }}</p>@enderror
                                @if ($settings['background_image_path'])
                                    <label class="mt-2 flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_background_image" value="1" class="checkbox-base"> Remove background image</label>
                                @endif
                                <div class="mt-3 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="flex justify-between text-xs font-medium text-gray-600" for="background_overlay">Darken / fade <span x-text="overlay + '%'"></span></label>
                                        <input id="background_overlay" type="range" name="background_overlay" min="0" max="90" step="5" x-model.number="overlay" class="w-full accent-brand-600">
                                    </div>
                                    <div>
                                        <label class="flex justify-between text-xs font-medium text-gray-600" for="background_blur">Blur <span x-text="blur + 'px'"></span></label>
                                        <input id="background_blur" type="range" name="background_blur" min="0" max="20" step="1" x-model.number="blur" class="w-full accent-brand-600">
                                    </div>
                                </div>
                                <p class="form-hint">Fills the whole page behind your content. The fade uses the page colour so text stays readable.</p>
                            </div>
                            <div>
                                <span class="form-label">Header cover image</span>
                                <label for="cover" class="relative flex h-36 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 text-center transition hover:border-brand-400">
                                    <template x-if="coverPreview"><img :src="coverPreview" alt="" class="absolute inset-0 h-full w-full object-cover"></template>
                                    <span class="relative flex flex-col items-center gap-1 text-xs font-medium" :class="coverPreview ? 'rounded-lg bg-black/50 px-2 py-1 text-white' : 'text-gray-500'">
                                        <x-lucide-image-plus class="h-6 w-6" />
                                        <span x-text="coverPreview ? 'Change cover' : 'Upload cover (up to 5 MB)'"></span>
                                    </span>
                                    <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="if ($event.target.files[0]) coverPreview = URL.createObjectURL($event.target.files[0])">
                                </label>
                                @error('cover')<p class="form-error">{{ $message }}</p>@enderror
                                @if ($settings['cover_path'])
                                    <label class="mt-2 flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_cover" value="1" class="checkbox-base"> Remove cover</label>
                                @endif
                                <p class="form-hint">Shown in the banner of Banner and Full gradient headers, and behind the motto on Profile Studio. Wide images work best.</p>
                            </div>
                        </div>
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">New images appear in the preview once you save.</p>
                    </section>

                    <x-field name="headline" label="Headline" :value="$settings['headline']" maxlength="120" hint="A short line under the name, e.g. what you help people with. Profile Studio shows it as the status chip above your name." />

                    {{-- Profile Studio extras --}}
                    <section x-show="isStudio" x-cloak class="space-y-5 rounded-2xl border border-brand-200 bg-brand-50/40 p-5">
                        <div>
                            <h3 class="flex items-center gap-1.5 text-sm font-semibold text-gray-900"><x-lucide-sparkles class="h-4 w-4 text-brand-500" /> Profile Studio</h3>
                            <p class="text-xs text-gray-500">Your profile becomes an animated one-page website. Stats are counted up from custom fields with numbers (e.g. "Years experience: 15+") and follower counts. Services scroll in the ribbon; the cover image sits behind the motto.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-field name="greeting" label="Greeting" :value="$settings['greeting']" maxlength="60" placeholder="Hello, I’m" hint="Shown above your name. Try “Namaste, I’m”." />
                            <x-field name="motto" label="Motto" :value="$settings['motto']" maxlength="220" placeholder="Build slowly. Build to last." hint="A quote in the mountain band after About. Leave empty to hide the band." />
                        </div>
                    </section>
                </div>

                {{-- Social --}}
                <div x-show="tab === 'social'" x-cloak class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <fieldset>
                            <legend class="form-label">Show social links as</legend>
                            <div class="flex gap-2">
                                @foreach (ProfileThemes::SOCIAL_STYLES as $key => $label)
                                    <label class="flex-1 cursor-pointer rounded-xl border border-gray-200 px-3 py-2 text-center text-sm font-medium text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                        <input type="radio" name="social_style" value="{{ $key }}" class="sr-only" @checked(old('social_style', $settings['social_style']) === $key)>{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="flex items-start gap-3 sm:pt-7">
                            <input type="hidden" name="show_follower_counts" value="0">
                            <input type="checkbox" name="show_follower_counts" value="1" class="checkbox-base mt-0.5" @checked(old('show_follower_counts', $settings['show_follower_counts']))>
                            <span><span class="block text-sm font-medium text-gray-900">Show follower counts</span><span class="block text-xs text-gray-500">On Follow buttons, for networks with a known count.</span></span>
                        </label>
                    </div>

                    @php $socialLinks = array_filter($profile->customer->social_links ?? []); @endphp
                    <div class="overflow-hidden rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-2.5">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Accounts</span>
                            @if ($socialLinks)
                                <button type="submit" form="refresh-social" class="btn-secondary btn-sm"><x-lucide-refresh-cw class="h-3.5 w-3.5" /> Refresh counts</button>
                            @endif
                        </div>
                        <div class="hidden grid-cols-[1fr_140px] gap-3 border-b border-gray-100 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 sm:grid">
                            <span>Link or @handle</span><span>Followers</span>
                        </div>
                        @foreach (CustomerFields::SOCIAL as $network => $label)
                            @php
                                $row = $settings['social_counts'][$network] ?? [];
                                $reason = $social->unavailableReason($network);
                                $color = \App\Services\Social\SocialStatsService::NETWORKS[$network]['color'] ?? '#6b7280';
                            @endphp
                            <div class="border-b border-gray-100 px-4 py-3 last:border-0">
                                <div class="grid items-start gap-3 sm:grid-cols-[1fr_140px]">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-white" style="background: {{ $color }}; {{ $network === 'snapchat' ? 'color: #111' : '' }}">
                                            <x-dynamic-component :component="'lucide-'.(\App\Support\Scene\IconLibrary::SOCIAL[$network] ?? 'link')" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <label class="sr-only" for="social_links_{{ $network }}">{{ $label }} link</label>
                                            <input id="social_links_{{ $network }}" name="social_links[{{ $network }}]" value="{{ old('social_links.'.$network, $socialLinks[$network] ?? '') }}"
                                                   class="input-base" placeholder="{{ $label }}: @handle or link" maxlength="255">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="sr-only" for="social_manual_{{ $network }}">{{ $label }} followers</label>
                                        <input id="social_manual_{{ $network }}" name="social_manual[{{ $network }}]" value="{{ old('social_manual.'.$network, $row['manual'] ?? '') }}" inputmode="decimal" maxlength="14"
                                               class="input-base" placeholder="{{ isset($row['count']) ? \App\Services\Social\SocialStatsService::format($row['count']) : 'e.g. 12.4K' }}">
                                    </div>
                                </div>
                                @error('social_links.'.$network)<p class="form-error ml-12">{{ $message }}</p>@enderror
                                @error('social_manual.'.$network)<p class="form-error ml-12">{{ $message }}</p>@enderror
                                @if (isset($socialLinks[$network]))
                                    <p class="ml-12 mt-1 text-xs {{ ! empty($row['error']) ? 'text-amber-700' : 'text-gray-500' }}">
                                        @if (! empty($row['error'])) {{ $row['error'] }}
                                        @elseif (isset($row['count'])) Fetched {{ \App\Services\Social\SocialStatsService::format($row['count']) }} {{ ! empty($row['fetched_at']) ? \Illuminate\Support\Carbon::parse($row['fetched_at'])->diffForHumans() : '' }}. Type a number to override it.
                                        @elseif ($reason) {{ $reason }}
                                        @else Fetched automatically. Press Refresh counts to get it now.
                                        @endif
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500">Links are saved on the customer record too, so cards and other profiles use them. Type a handle like @maya and it becomes the full link.</p>
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

        <aside class="xl:sticky xl:top-20 xl:self-start">
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h2 class="card-title flex items-center gap-2">
                        <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span>
                        Live preview
                    </h2>
                    <a href="{{ route('profiles.preview', $profile) }}" target="_blank" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Saved version</a>
                </div>
                <div class="bg-gradient-to-b from-gray-50 to-gray-100 p-4">
                    <div class="mx-auto h-[640px] w-[320px] overflow-hidden rounded-[2.25rem] border-[7px] border-gray-900 bg-white shadow-lift ring-1 ring-black/10">
                        <iframe name="live-preview" src="{{ route('profiles.preview', $profile) }}" title="Live preview" class="h-full w-full"></iframe>
                    </div>
                    <p class="mt-3 text-center text-[11px] text-gray-500">Updates as you edit. Nothing is saved until you press Save profile.</p>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.app>
