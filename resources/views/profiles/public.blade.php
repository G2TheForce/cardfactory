@php
    use App\Services\QRCodeService;
    use App\Support\Scene\IconLibrary;

    // Set when building a static site (ProfileSiteExporter): relative asset URLs, no tracking.
    $export ??= null;
    $t = $p['theme'];
    $radius = ['rounded' => '0.875rem', 'pill' => '9999px', 'square' => '0.375rem'][$p['button_style']] ?? '0.875rem';
    $serif = $t['font'] === 'serif';
    $hasBackdrop = $p['background_image'] || $t['effect'] !== 'none';
    $gradientTo = $t['gradient_to'] ?? 'color-mix(in srgb, var(--accent) 55%, #111827)';
    $subtitle = collect([$p['title'], $p['company']])->filter()->implode(' · ');
    $qrSvg = $p['show_qr'] ? app(QRCodeService::class)->svg($urls['qr'], 240, '#111827', '#ffffff', 2, 'M') : null;
    $days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
    $actions = collect([
        $p['phone_href'] ? ['label' => 'Call', 'href' => $p['phone_href'], 'icon' => 'phone', 'track' => 'phone'] : null,
        $p['whatsapp_href'] ? ['label' => 'WhatsApp', 'href' => $p['whatsapp_href'], 'icon' => 'message-circle', 'track' => 'whatsapp', 'external' => true] : null,
        $p['email'] ? ['label' => 'Email', 'href' => 'mailto:'.$p['email'], 'icon' => 'mail', 'track' => 'email'] : null,
        $p['website'] ? ['label' => 'Website', 'href' => $p['website'], 'icon' => 'globe', 'track' => 'website', 'external' => true] : null,
    ])->filter()->values();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $p['name'] }}{{ $subtitle ? ' · '.$subtitle : '' }}</title>
    <meta name="description" content="{{ Str::limit($p['headline'] ?: ($p['intro'] ?: 'Digital business card of '.$p['name'].($subtitle ? ', '.$subtitle : '').'.'), 160) }}">
    @if ($preview || ! $p['allow_indexing'])
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="{{ $t['header'] === 'centered' ? $t['colors']['bg'] : $p['accent'] }}">
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $p['name'] }}">
    <meta property="og:description" content="{{ $subtitle ?: 'Digital business card' }}">
    @if ($urls['public'])<meta property="og:url" content="{{ $urls['public'] }}">@endif
    @if ($p['photo'])<meta property="og:image" content="{{ $p['photo'] }}">@endif
    @if ($urls['public'])<link rel="canonical" href="{{ $urls['public'] }}">@endif
    @if ($export)
        <link rel="icon" href="favicon.svg" type="image/svg+xml">
        @if ($export['css'])<link rel="stylesheet" href="{{ $export['css'] }}">@endif
        @if ($export['js'])<script type="module" src="{{ $export['js'] }}"></script>@endif
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        :root {
            --bg: {{ $t['colors']['bg'] }}; --surface: {{ $t['colors']['surface'] }}; --text: {{ $t['colors']['text'] }};
            --muted: {{ $t['colors']['muted'] }}; --border: {{ $t['colors']['border'] }};
            --accent: {{ $p['accent'] }}; --accent-text: {{ $p['accent_text'] }}; --radius: {{ $radius }};
        }
        body { background: var(--bg); color: var(--text); }
        .pf-card { background: var(--surface); border: 1px solid var(--border); border-radius: 1.25rem; }
        .pf-muted { color: var(--muted); }
        .pf-btn { border-radius: var(--radius); background: var(--accent); color: var(--accent-text); }
        .pf-btn-outline { border-radius: var(--radius); border: 1.5px solid var(--accent); color: var(--accent); background: transparent; }
        .pf-chip { background: color-mix(in srgb, var(--accent) 10%, var(--surface)); color: var(--text); }
        .pf-icon { background: color-mix(in srgb, var(--accent) 12%, var(--surface)); color: var(--accent); }
        .pf-row + .pf-row { border-top: 1px solid var(--border); }
        .pf-hero { background: linear-gradient(140deg, var(--accent), {{ $gradientTo }}); }
        @if ($serif) .pf-heading { font-family: {!! $t['font_stack'] !!}; letter-spacing: -0.01em; } @elseif ($t['font'] !== 'sans') body { font-family: {!! $t['font_stack'] !!}; } @endif
        .pf-social { border-radius: var(--radius); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14); }
        .pf-social:hover { filter: brightness(1.08); }
        .pf-social-sub { opacity: 0.8; }

        /* Backdrop: page background image and effects, fixed behind the content. */
        .pf-backdrop { position: fixed; inset: 0; z-index: -1; overflow: hidden; background: var(--bg); }
        .pf-backdrop-img { position: absolute; inset: -40px; background-size: cover; background-position: center; }
        .pf-backdrop-overlay { position: absolute; inset: 0; }
        .pf-aurora span { position: absolute; width: 70vmax; height: 70vmax; border-radius: 9999px; filter: blur(90px); opacity: {{ $t['dark'] ? '0.45' : '0.35' }}; animation: pf-float 22s ease-in-out infinite alternate; }
        .pf-aurora span:nth-child(1) { background: var(--accent); top: -30vmax; left: -20vmax; }
        .pf-aurora span:nth-child(2) { background: {{ $gradientTo }}; bottom: -35vmax; right: -25vmax; animation-delay: -7s; }
        .pf-aurora span:nth-child(3) { background: color-mix(in srgb, var(--accent) 50%, {{ $gradientTo }}); top: 35%; left: 30%; width: 45vmax; height: 45vmax; animation-delay: -14s; }
        @keyframes pf-float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(6vmax, 4vmax) scale(1.12); } 100% { transform: translate(-4vmax, 8vmax) scale(0.95); } }
        @media (prefers-reduced-motion: reduce) { .pf-aurora span { animation: none; } }
        .pf-grid { position: absolute; inset: 0; background-image: linear-gradient(color-mix(in srgb, var(--accent) 16%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 16%, transparent) 1px, transparent 1px); background-size: 32px 32px; mask-image: radial-gradient(ellipse at top, #000 30%, transparent 75%); }
        .pf-dots { position: absolute; inset: 0; background-image: radial-gradient(color-mix(in srgb, var(--accent) 35%, transparent) 1px, transparent 1.5px); background-size: 18px 18px; }
        .pf-mesh { position: absolute; inset: 0; background: radial-gradient(at 15% 10%, color-mix(in srgb, var(--accent) 40%, transparent) 0, transparent 50%), radial-gradient(at 85% 20%, color-mix(in srgb, {{ $gradientTo }} 40%, transparent) 0, transparent 50%), radial-gradient(at 50% 95%, color-mix(in srgb, var(--accent) 25%, transparent) 0, transparent 55%); }
        @if ($hasBackdrop) .pf-shell { background: transparent !important; } @endif

        /* Card styles */
        @if ($t['card_style'] === 'glass')
            .pf-card { background: color-mix(in srgb, var(--surface) {{ $t['dark'] ? '55%' : '62%' }}, transparent); border-color: {{ $t['dark'] ? 'rgba(255,255,255,0.12)' : 'rgba(255,255,255,0.7)' }}; -webkit-backdrop-filter: blur(18px) saturate(150%); backdrop-filter: blur(18px) saturate(150%); box-shadow: 0 8px 32px rgba(0, 0, 0, {{ $t['dark'] ? '0.35' : '0.08' }}); }
        @elseif ($t['card_style'] === 'outline')
            .pf-card { background: color-mix(in srgb, var(--surface) 35%, transparent); border-color: color-mix(in srgb, var(--accent) 40%, var(--border)); }
        @endif
        @if ($t['glow'])
            .pf-btn, .pf-social { box-shadow: 0 0 0 1px color-mix(in srgb, var(--accent) 60%, transparent), 0 8px 28px -6px color-mix(in srgb, var(--accent) 70%, transparent); }
            .pf-avatar { box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 35%, transparent), 0 0 48px color-mix(in srgb, var(--accent) 55%, transparent) !important; }
            .pf-glow-text { text-shadow: 0 0 24px color-mix(in srgb, var(--accent) 60%, transparent); }
        @endif
    </style>
</head>
<body class="min-h-screen antialiased" x-data="{ qr: false, photo: null, shared: false }">
@if ($hasBackdrop)
    <div class="pf-backdrop" aria-hidden="true">
        @if ($t['effect'] === 'aurora')<div class="pf-aurora"><span></span><span></span><span></span></div>@endif
        @if ($t['effect'] === 'mesh')<div class="pf-mesh"></div>@endif
        @if ($p['background_image'])
            <div class="pf-backdrop-img" style="background-image: url('{{ $p['background_image'] }}'); filter: blur({{ $p['background_blur'] }}px)"></div>
            <div class="pf-backdrop-overlay" style="background: color-mix(in srgb, var(--bg) {{ $p['background_overlay'] }}%, transparent)"></div>
        @endif
        @if ($t['effect'] === 'grid')<div class="pf-grid"></div>@endif
        @if ($t['effect'] === 'dots')<div class="pf-dots"></div>@endif
    </div>
@endif
@if ($preview)
    <div class="sticky top-0 z-30 bg-gray-900 px-4 py-2 text-center text-xs font-medium text-white">
        Preview{{ $available ? '' : ' · this profile is not live, visitors see a "not available" page' }} · visits here aren't counted
    </div>
@endif

<main class="mx-auto w-full max-w-md pb-10 sm:py-8">
    <div class="pf-shell overflow-hidden sm:rounded-[2rem] {{ $hasBackdrop ? '' : 'sm:shadow-2xl sm:ring-1 sm:ring-black/5' }}" style="background: var(--bg)">

        {{-- Header --}}
        @if ($t['header'] === 'cover')
            <div class="relative h-40 pf-hero">
                @if ($p['cover'])<img src="{{ $p['cover'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">@endif
                @if ($p['company_logo'])
                    <img src="{{ $p['company_logo'] }}" alt="{{ $p['company'] }}" class="absolute right-4 top-4 h-10 max-w-28 rounded-lg bg-white/95 object-contain p-1.5 shadow">
                @endif
            </div>
            <div class="relative -mt-16 px-6 text-center">
                @include('profiles.partials.public-photo', ['size' => 'h-32 w-32', 'ring' => 'ring-4'])
            </div>
        @elseif ($t['header'] === 'hero')
            <div class="pf-hero relative px-6 pb-20 pt-12 text-center text-white">
                @if ($p['cover'])<img src="{{ $p['cover'] }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30">@endif
                <div class="relative">
                    @include('profiles.partials.public-photo', ['size' => 'h-28 w-28', 'ring' => 'ring-4 ring-white/40'])
                    <h1 class="pf-heading pf-glow-text mt-4 text-3xl font-extrabold tracking-tight">{{ $p['name'] }}</h1>
                    @if ($p['title'])<p class="mt-1 text-base font-medium text-white/90">{{ $p['title'] }}</p>@endif
                    @if ($p['company'])<p class="mt-0.5 text-sm text-white/75">{{ $p['company'] }}{{ $p['department'] ? ' · '.$p['department'] : '' }}</p>@endif
                </div>
            </div>
        @else
            <div class="px-6 pt-12 text-center">
                @if ($p['cover'])<img src="{{ $p['cover'] }}" alt="" class="mb-6 h-32 w-full rounded-2xl object-cover">@endif
                @include('profiles.partials.public-photo', ['size' => 'h-28 w-28', 'ring' => 'ring-1'])
            </div>
        @endif

        @if ($t['header'] !== 'hero')
            <div class="px-6 pt-4 text-center">
                <h1 class="pf-heading pf-glow-text text-[1.75rem] font-bold leading-tight tracking-tight">{{ $p['name'] }}</h1>
                @if ($p['title'])<p class="mt-1 text-base font-medium" style="color: {{ $t['dark'] ? 'color-mix(in srgb, var(--accent) 55%, var(--text))' : 'var(--accent)' }}">{{ $p['title'] }}</p>@endif
                @if ($p['company'] || $p['department'])
                    <p class="pf-muted mt-1 flex items-center justify-center gap-1.5 text-sm">
                        @if ($p['company_logo'] && $t['header'] !== 'cover')<img src="{{ $p['company_logo'] }}" alt="" class="h-5 w-5 rounded object-contain">@endif
                        {{ collect([$p['company'], $p['department']])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>
        @endif

        <div class="space-y-4 px-4 {{ $t['header'] === 'hero' ? '-mt-12 relative' : 'pt-5' }}">
            @if ($p['headline'])
                <p class="pf-card px-5 py-4 text-center text-[15px] font-medium leading-relaxed">{{ $p['headline'] }}</p>
            @endif

            @foreach ($p['sections'] as $section)
                @switch($section)
                    @case('actions')
                        @if ($actions->isNotEmpty() || $p['show_save_contact'])
                            <section class="pf-card p-4" aria-label="Contact {{ $p['name'] }}">
                                @if ($actions->isNotEmpty())
                                    <div class="grid gap-2" style="grid-template-columns: repeat({{ $actions->count() }}, minmax(0, 1fr))">
                                        @foreach ($actions as $a)
                                            <a href="{{ $a['href'] }}" data-track="{{ $a['track'] }}" @if (! empty($a['external'])) target="_blank" rel="noopener" @endif
                                               class="flex flex-col items-center gap-1.5 rounded-xl px-1 py-2.5 text-xs font-semibold transition hover:opacity-80">
                                                <span class="pf-icon grid h-12 w-12 place-items-center rounded-full"><x-dynamic-component :component="'lucide-'.$a['icon']" class="h-5 w-5" /></span>
                                                {{ $a['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($p['show_save_contact'])
                                    <a href="{{ $urls['vcard'] }}" class="pf-btn {{ $actions->isNotEmpty() ? 'mt-4' : '' }} flex w-full items-center justify-center gap-2 px-4 py-3.5 text-[15px] font-semibold shadow-sm transition hover:opacity-90">
                                        <x-lucide-user-plus class="h-5 w-5" /> Save contact
                                    </a>
                                @endif
                            </section>
                        @endif
                        @break

                    @case('ctas')
                        @if ($p['ctas'])
                            <section class="space-y-2.5" aria-label="Links">
                                @foreach ($p['ctas'] as $i => $cta)
                                    <a href="{{ $cta['url'] }}" target="_blank" rel="noopener" data-track="custom" data-target="cta-{{ $i + 1 }}"
                                       class="{{ $cta['style'] === 'outline' ? 'pf-btn-outline' : 'pf-btn' }} flex w-full items-center justify-center gap-2 px-4 py-3.5 text-[15px] font-semibold transition hover:opacity-90">
                                        {{ $cta['label'] }} <x-lucide-arrow-up-right class="h-4 w-4 opacity-70" />
                                    </a>
                                @endforeach
                            </section>
                        @endif
                        @break

                    @case('about')
                        @if ($p['intro'] || $p['description'])
                            <section class="pf-card p-5">
                                <h2 class="pf-heading text-base font-semibold">About</h2>
                                @if ($p['intro'])<p class="pf-muted mt-2 whitespace-pre-line text-[15px] leading-relaxed">{{ $p['intro'] }}</p>@endif
                                @if ($p['description'] && $p['description'] !== $p['intro'])<p class="pf-muted mt-2 whitespace-pre-line text-[15px] leading-relaxed">{{ $p['description'] }}</p>@endif
                            </section>
                        @endif
                        @break

                    @case('contact')
                        @php
                            $rows = collect([
                                $p['mobile'] ? ['icon' => 'smartphone', 'label' => 'Mobile', 'value' => $p['mobile'], 'href' => $p['phone_href'], 'track' => 'phone'] : null,
                                $p['office_phone'] ? ['icon' => 'phone', 'label' => 'Office', 'value' => $p['office_phone'], 'href' => $p['office_href'], 'track' => 'phone'] : null,
                                $p['email'] ? ['icon' => 'mail', 'label' => 'Email', 'value' => $p['email'], 'href' => 'mailto:'.$p['email'], 'track' => 'email'] : null,
                                $p['website'] ? ['icon' => 'globe', 'label' => 'Website', 'value' => $p['website_label'], 'href' => $p['website'], 'track' => 'website', 'external' => true] : null,
                                $p['address'] ? ['icon' => 'map-pin', 'label' => 'Address', 'value' => $p['address'], 'href' => $p['map_url'], 'external' => true] : null,
                            ])->filter()->merge(collect($p['custom_fields'])->map(fn ($f) => ['icon' => 'info', 'label' => $f['label'], 'value' => $f['value'], 'href' => null]));
                        @endphp
                        @if ($rows->isNotEmpty())
                            <section class="pf-card px-5 py-2" aria-label="Contact details">
                                @foreach ($rows as $r)
                                    <div class="pf-row flex items-center gap-3.5 py-3">
                                        <span class="pf-icon grid h-10 w-10 shrink-0 place-items-center rounded-xl"><x-dynamic-component :component="'lucide-'.$r['icon']" class="h-[18px] w-[18px]" /></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="pf-muted text-xs">{{ $r['label'] }}</p>
                                            @if ($r['href'])
                                                <a href="{{ $r['href'] }}" @if (! empty($r['track'])) data-track="{{ $r['track'] }}" @endif @if (! empty($r['external'])) target="_blank" rel="noopener" @endif class="block break-words text-[15px] font-medium hover:underline">{{ $r['value'] }}</a>
                                            @else
                                                <p class="break-words text-[15px] font-medium">{{ $r['value'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </section>
                        @endif
                        @break

                    @case('social')
                        @if ($p['social'])
                            @if ($p['social_style'] === 'buttons')
                                <section class="space-y-2.5" aria-label="Social media">
                                    @foreach ($p['social'] as $s)
                                        <a href="{{ $s['url'] }}" target="_blank" rel="noopener me" data-track="social" data-target="{{ $s['key'] }}"
                                           class="pf-social flex items-center gap-3 px-4 py-3 shadow-sm transition" style="background: {{ $s['background'] }}; color: {{ $s['text'] }}">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full" style="background: color-mix(in srgb, currentColor 15%, transparent)">
                                                <x-dynamic-component :component="'lucide-'.(IconLibrary::SOCIAL[$s['key']] ?? 'link')" class="h-5 w-5" />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-[15px] font-semibold leading-tight">{{ $s['verb'] }} on {{ $s['label'] }}</span>
                                                @if ($s['count_label'])<span class="pf-social-sub block text-xs">{{ $s['count_label'] }}</span>@endif
                                            </span>
                                            <x-lucide-chevron-right class="pf-social-sub h-4 w-4 shrink-0" />
                                        </a>
                                    @endforeach
                                </section>
                            @else
                                <section class="pf-card p-5">
                                    <h2 class="pf-heading text-base font-semibold">Follow</h2>
                                    <div class="mt-3 flex flex-wrap gap-2.5">
                                        @foreach ($p['social'] as $s)
                                            <a href="{{ $s['url'] }}" target="_blank" rel="noopener me" data-track="social" data-target="{{ $s['key'] }}" aria-label="{{ $s['label'] }}" title="{{ $s['label'] }}{{ $s['count_label'] ? ' · '.$s['count_label'] : '' }}"
                                               class="pf-icon grid h-12 w-12 place-items-center rounded-2xl transition hover:scale-105">
                                                <x-dynamic-component :component="'lucide-'.(IconLibrary::SOCIAL[$s['key']] ?? 'link')" class="h-5 w-5" />
                                            </a>
                                        @endforeach
                                    </div>
                                </section>
                            @endif
                        @endif
                        @break

                    @case('links')
                        @if ($p['links'])
                            <section class="pf-card px-5 py-2" aria-label="Links">
                                @foreach ($p['links'] as $i => $l)
                                    <a href="{{ $l['url'] }}" target="_blank" rel="noopener" data-track="custom" data-target="link-{{ $i + 1 }}" class="pf-row flex items-center gap-3.5 py-3 hover:opacity-80">
                                        <span class="pf-icon grid h-10 w-10 shrink-0 place-items-center rounded-xl"><x-dynamic-component :component="'lucide-'.$l['icon']" class="h-[18px] w-[18px]" /></span>
                                        <span class="min-w-0 flex-1 truncate text-[15px] font-medium">{{ $l['label'] }}</span>
                                        <x-lucide-arrow-up-right class="pf-muted h-4 w-4" />
                                    </a>
                                @endforeach
                            </section>
                        @endif
                        @break

                    @case('business')
                        @if ($p['services'] || $p['hours'])
                            <section class="pf-card p-5">
                                @if ($p['services'])
                                    <h2 class="pf-heading text-base font-semibold">Services</h2>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($p['services'] as $service)<span class="pf-chip rounded-full px-3 py-1.5 text-sm font-medium">{{ $service }}</span>@endforeach
                                    </div>
                                @endif
                                @if ($p['hours'])
                                    <h2 class="pf-heading {{ $p['services'] ? 'mt-5' : '' }} flex items-center gap-2 text-base font-semibold"><x-lucide-clock class="h-4 w-4" style="color: var(--accent)" /> Opening hours</h2>
                                    <dl class="mt-2 text-sm">
                                        @foreach ($days as $key => $label)
                                            @if (! empty($p['hours'][$key]))
                                                <div class="flex justify-between gap-4 py-1.5 {{ strtolower(now()->englishDayOfWeek) === $key ? 'font-semibold' : '' }}">
                                                    <dt class="pf-muted">{{ $label }}</dt><dd class="text-right">{{ $p['hours'][$key] }}</dd>
                                                </div>
                                            @endif
                                        @endforeach
                                    </dl>
                                @endif
                            </section>
                        @endif
                        @break

                    @case('portfolio')
                        @if ($p['portfolio'])
                            <section class="pf-card p-5">
                                <h2 class="pf-heading text-base font-semibold">Portfolio</h2>
                                <div class="mt-3 grid grid-cols-2 gap-2.5">
                                    @foreach ($p['portfolio'] as $item)
                                        <button type="button" class="group overflow-hidden rounded-xl text-left" @click="photo = @js(['url' => $item['url'], 'caption' => $item['caption']])">
                                            <img src="{{ $item['url'] }}" alt="{{ $item['caption'] ?? 'Portfolio image' }}" loading="lazy" class="aspect-square w-full object-cover transition group-hover:scale-105">
                                            @if ($item['caption'])<span class="pf-muted mt-1 block truncate text-xs">{{ $item['caption'] }}</span>@endif
                                        </button>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('map')
                        @if ($p['map_url'])
                            <section class="pf-card overflow-hidden" x-data="{ map: false }">
                                @if ($p['map_embed'])
                                    {{-- Click-to-load keeps Google from seeing visitors who don't use the map. --}}
                                    <template x-if="map"><iframe src="{{ $p['map_embed'] }}" class="h-56 w-full border-0" loading="lazy" referrerpolicy="no-referrer" title="Map"></iframe></template>
                                    <button type="button" x-show="! map" @click="map = true" class="pf-chip flex h-32 w-full flex-col items-center justify-center gap-1.5 text-sm font-medium">
                                        <x-lucide-map class="h-6 w-6" style="color: var(--accent)" /> Show map
                                    </button>
                                @endif
                                <div class="flex items-center gap-3 p-4">
                                    <span class="pf-icon grid h-10 w-10 shrink-0 place-items-center rounded-xl"><x-lucide-map-pin class="h-[18px] w-[18px]" /></span>
                                    <p class="min-w-0 flex-1 text-sm">{{ $p['address'] ?: 'Location' }}</p>
                                    <a href="{{ $p['map_url'] }}" target="_blank" rel="noopener" data-track="custom" data-target="map" class="pf-btn-outline shrink-0 px-3 py-1.5 text-xs font-semibold">Directions</a>
                                </div>
                            </section>
                        @endif
                        @break
                @endswitch
            @endforeach

            {{-- Share --}}
            <section class="flex gap-2.5 pt-1">
                <button type="button" class="pf-card flex flex-1 items-center justify-center gap-2 px-4 py-3 text-sm font-semibold"
                        @click="navigator.share ? navigator.share({ title: @js($p['name']), url: (@js($urls['public']) || location.href) }).catch(() => {}) : navigator.clipboard.writeText((@js($urls['public']) || location.href)).then(() => { shared = true; setTimeout(() => shared = false, 2000) })">
                    <x-lucide-share-2 class="h-4 w-4" /> <span x-text="shared ? 'Link copied' : 'Share'">Share</span>
                </button>
                @if ($qrSvg)
                    <button type="button" class="pf-card flex flex-1 items-center justify-center gap-2 px-4 py-3 text-sm font-semibold" @click="qr = true">
                        <x-lucide-qr-code class="h-4 w-4" /> QR code
                    </button>
                @endif
            </section>
        </div>

        <footer class="pf-muted px-6 pb-8 pt-8 text-center text-xs">
            @if ($export)
                Made with {{ config('app.name') }}
            @else
                <a href="{{ route('home') }}" class="hover:underline">Made with {{ config('app.name') }}</a>
            @endif
        </footer>
    </div>
</main>

@if ($qrSvg)
    <div x-cloak x-show="qr" x-transition.opacity class="fixed inset-0 z-40 grid place-items-center bg-black/60 p-6" @click.self="qr = false" @keydown.escape.window="qr = false" role="dialog" aria-modal="true" aria-label="QR code">
        <div class="w-full max-w-xs rounded-3xl bg-white p-6 text-center text-gray-900 shadow-2xl">
            <div class="mx-auto w-56 [&>svg]:h-auto [&>svg]:w-full">{!! $qrSvg !!}</div>
            <p class="mt-3 text-sm font-semibold">{{ $p['name'] }}</p>
            <p class="mt-0.5 text-xs text-gray-500">Scan with a phone camera to open this card</p>
            <button type="button" class="mt-5 w-full rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white" @click="qr = false">Close</button>
        </div>
    </div>
@endif

<div x-cloak x-show="photo" x-transition.opacity class="fixed inset-0 z-40 flex flex-col items-center justify-center gap-3 bg-black/85 p-4" @click="photo = null" @keydown.escape.window="photo = null" role="dialog" aria-modal="true">
    <img :src="photo?.url" alt="" class="max-h-[80vh] max-w-full rounded-xl object-contain">
    <p class="text-sm text-white/90" x-text="photo?.caption"></p>
</div>

@unless ($preview || $export)
    <script>
        document.addEventListener('click', function (e) {
            var a = e.target.closest('[data-track]');
            if (!a || !navigator.sendBeacon) return;
            var body = new FormData();
            body.append('type', a.dataset.track);
            if (a.dataset.target) body.append('target', a.dataset.target);
            navigator.sendBeacon(@js(route('profiles.track', $profile->slug)), body);
        });
    </script>
@endunless
</body>
</html>
