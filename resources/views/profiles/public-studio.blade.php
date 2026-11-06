@php
    use App\Services\QRCodeService;
    use App\Support\ProfileThemes;
    use App\Support\Scene\IconLibrary;

    /*
     * Profile Studio: the public profile as an animated one-page website.
     * Same data, sections, tracking, vCard, QR and export support as the phone
     * card layout (profiles/public), laid out as a personal site. Classes are
     * prefixed "sx-" so they never collide with Tailwind utilities.
     */
    $export ??= null;
    $t = $p['theme'];
    $c = $t['colors'];
    $dark = $t['dark'];
    $accent = $p['accent'];
    $deep = $t['deep'] ?? ProfileThemes::mix($accent, '#000000', 0.7);
    $mix = fn ($a, $b, $x) => ProfileThemes::mix($a, $b, $x);
    $rgba = function (string $hex, float $a): string {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return "rgba($r,$g,$b,$a)";
    };
    $radius = ['rounded' => '14px', 'pill' => '999px', 'square' => '6px'][$p['button_style']] ?? '999px';
    // Fraunces + Manrope unless the owner picked another font.
    $serif = "'Fraunces Variable', 'Fraunces', Georgia, serif";
    $sans = "'Manrope Variable', 'Manrope', ui-sans-serif, system-ui, sans-serif";
    if ($t['font_override']) {
        $serif = $t['font'] === 'serif' ? ProfileThemes::FONTS['serif']['stack'] : $t['font_stack'];
        $sans = $t['font'] === 'serif' ? $sans : $t['font_stack'];
    }
    $hasBackdrop = $p['background_image'] || $t['effect'] !== 'none';
    $gradientTo = $t['gradient_to'] ?? $mix($accent, $deep, 0.5);
    $subtitle = collect([$p['title'], $p['company']])->filter()->implode(' · ');
    $qrSvg = $p['show_qr'] ? app(QRCodeService::class)->svg($urls['qr'], 240, '#111827', '#ffffff', 2, 'M') : null;
    $days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
    $on = fn (string $section) => in_array($section, $p['sections'], true);

    // Name over two lines, the second in italic accent.
    $words = preg_split('/\s+/', trim($p['name'])) ?: [$p['name']];
    $half = count($words) > 1 ? (int) ceil(count($words) / 2) : 1;
    $line1 = implode(' ', array_slice($words, 0, $half));
    $line2 = implode(' ', array_slice($words, $half));

    // "12.4K" → count-up from 0 to 12.4 with suffix "K".
    $stat = function (string $value): ?array {
        if (! preg_match('/^([^\d]{0,3})([\d][\d,]*(?:\.\d+)?)(\S{0,4})$/u', trim($value), $m)) {
            return null;
        }

        return ['pre' => $m[1], 'n' => (float) str_replace(',', '', $m[2]), 'suf' => $m[3], 'dec' => strlen(explode('.', $m[2])[1] ?? '')];
    };
    // Number-like custom fields ("Years experience: 15+") become hero stats; the rest become "at a glance" cards.
    $fields = collect($p['custom_fields'])->filter(fn ($f) => filled($f['label'] ?? null) && filled($f['value'] ?? null));
    $stats = $fields->filter(fn ($f) => $stat((string) $f['value']))->map(fn ($f) => ['value' => (string) $f['value'], 'label' => $f['label']]);
    $facts = $fields->reject(fn ($f) => $stat((string) $f['value']))->values();
    $stats = $stats->merge(collect($p['social'])->filter(fn ($s) => $s['count'] !== null)->sortByDesc('count')
        ->map(fn ($s) => ['value' => \App\Services\Social\SocialStatsService::format($s['count']), 'label' => $s['label'].' '.\Illuminate\Support\Str::after($s['count_label'], ' ')]))
        ->take(4)->values();

    $actions = collect([
        $p['phone_href'] ? ['label' => 'Call', 'href' => $p['phone_href'], 'icon' => 'phone', 'track' => 'phone'] : null,
        $p['whatsapp_href'] ? ['label' => 'WhatsApp', 'href' => $p['whatsapp_href'], 'icon' => 'message-circle', 'track' => 'whatsapp', 'external' => true] : null,
        $p['email'] ? ['label' => 'Email', 'href' => 'mailto:'.$p['email'], 'icon' => 'mail', 'track' => 'email'] : null,
        $p['website'] ? ['label' => 'Website', 'href' => $p['website'], 'icon' => 'globe', 'track' => 'website', 'external' => true] : null,
    ])->filter()->values();
    // "Street, City, Province, Country" → "City, Country".
    $parts = collect(explode(',', (string) $p['address']))->map(fn ($x) => trim($x))->filter()->values();
    $city = $parts->count() >= 3 ? $parts[$parts->count() - 3].', '.$parts->last() : $parts->implode(', ');
    $info = collect([
        $p['company'] ? ['icon' => 'briefcase', 'title' => $p['company'], 'sub' => $p['department'] ?: ($p['designation'] ?: 'Company')] : null,
        $city ? ['icon' => 'map-pin', 'title' => $city, 'sub' => 'Based in'] : null,
    ])->filter()->values();
    $socialCards = $on('social') && $p['social'] && $p['social_style'] === 'buttons';
    $socialIcons = $on('social') && $p['social'] && $p['social_style'] !== 'buttons';
    $contactRows = collect([
        $p['email'] ? ['icon' => 'mail', 'value' => $p['email'], 'href' => 'mailto:'.$p['email'], 'track' => 'email', 'copy' => $p['email']] : null,
        $p['mobile'] ? ['icon' => 'smartphone', 'value' => $p['mobile'], 'href' => $p['phone_href'], 'track' => 'phone', 'note' => 'Mobile'] : null,
        $p['office_phone'] ? ['icon' => 'phone', 'value' => $p['office_phone'], 'href' => $p['office_href'], 'track' => 'phone', 'note' => 'Office'] : null,
        $p['whatsapp_href'] ? ['icon' => 'message-circle', 'value' => 'WhatsApp', 'href' => $p['whatsapp_href'], 'track' => 'whatsapp', 'external' => true] : null,
        $p['website'] ? ['icon' => 'globe', 'value' => $p['website_label'], 'href' => $p['website'], 'track' => 'website', 'external' => true] : null,
        $p['address'] ? ['icon' => 'map-pin', 'value' => $p['address'], 'href' => $p['map_url'], 'external' => true] : null,
    ])->filter()->values();
    $showContact = $on('contact') && ($contactRows->isNotEmpty() || $socialIcons);
    $showAbout = $on('about') && ($p['intro'] || $p['description'] || $facts->isNotEmpty());
    $nav = collect([
        $showAbout ? ['about', 'About'] : null,
        $socialCards ? ['social', 'Follow'] : null,
        $on('business') && ($p['services'] || $p['hours']) ? ['services', $p['services'] ? 'Services' : 'Hours'] : null,
        $on('links') && $p['links'] ? ['work', 'Work'] : null,
        $on('portfolio') && $p['portfolio'] ? ['portfolio', 'Portfolio'] : null,
        $showContact ? ['contact', 'Contact'] : null,
    ])->filter()->values();
    $greeting = $p['greeting'] ?? 'Hello, I’m';
    $motto = $p['motto'] ?? null;
    $lead = $p['description'] ?: \Illuminate\Support\Str::limit((string) preg_split('/\n\s*\n/', (string) $p['intro'])[0], 220);
    $paras = fn (?string $s) => array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', (string) $s) ?: [])));
    $tile = [$deep, $accent, '#2f6b5a', '#9a4a07', '#3b3b8f', '#5a5a63'];
    $num = fn (int $i) => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
    $initials = fn (string $s) => mb_strtoupper(collect(preg_split('/\s+/', trim($s)) ?: [])->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '•';
    $ink2 = $mix($c['text'], $c['bg'], 0.22);
    $navyText = $dark ? $mix($c['text'], $c['bg'], 0.12) : $mix($deep, '#ffffff', 0.1);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $p['name'] }}{{ $subtitle ? ' · '.$subtitle : '' }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($p['headline'] ?: ($p['intro'] ?: 'Personal website of '.$p['name'].($subtitle ? ', '.$subtitle : '').'.'), 160) }}">
    @if ($preview || ! $p['allow_indexing'])
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="{{ $c['bg'] }}">
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $p['name'] }}">
    <meta property="og:description" content="{{ $subtitle ?: 'Personal website' }}">
    @if ($urls['public'])<meta property="og:url" content="{{ $urls['public'] }}">@endif
    @if ($p['photo'])<meta property="og:image" content="{{ $p['photo'] }}">@endif
    @if ($urls['public'])<link rel="canonical" href="{{ $urls['public'] }}">@endif
    <script>document.documentElement.classList.add('sx-js')</script>
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
            --ink: {{ $c['text'] }}; --ink-2: {{ $ink2 }}; --muted: {{ $c['muted'] }}; --line: {{ $c['border'] }};
            --ground: {{ $c['bg'] }}; --paper: {{ $c['surface'] }}; --navy: {{ $deep }}; --navy-2: {{ $navyText }};
            --accent: {{ $accent }}; --accent-d: {{ $mix($accent, '#000000', 0.28) }}; --accent-text: {{ $p['accent_text'] }};
            --blush: {{ $mix($accent, $c['bg'], 0.88) }}; --blush-2: {{ $mix($accent, $c['bg'], 0.78) }}; --gold: #c8963e;
            --serif: {!! $serif !!}; --sans: {!! $sans !!}; --radius: {{ $radius }};
            --ease: cubic-bezier(.22, 1, .36, 1); --r: 22px; --wrap: 1180px;
        }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        html, body { overflow-x: clip; }
        body { margin: 0; background: var(--ground); color: var(--ink); font-family: var(--sans); font-size: 17px; line-height: 1.65; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        ::selection { background: var(--accent); color: var(--accent-text); }
        :focus-visible { outline: 3px solid var(--gold); outline-offset: 3px; border-radius: 6px; }
        .sx-i { width: 20px; height: 20px; flex: none; }
        .sx-wrap { max-width: var(--wrap); margin: 0 auto; padding: 0 24px; }
        .sx-skip { position: absolute; left: -999px; top: 12px; background: var(--navy); color: #fff; padding: 10px 16px; border-radius: 8px; z-index: 200; }
        .sx-skip:focus { left: 12px; }
        .sx-preview { position: sticky; top: 0; z-index: 140; background: #111827; color: #fff; text-align: center; font: 600 12px/1.4 var(--sans); padding: 8px 16px; }
        .sx-progress { position: fixed; top: 0; left: 0; height: 3px; width: 100%; transform-origin: 0 50%; transform: scaleX(0); background: linear-gradient(90deg, var(--accent), var(--gold)); z-index: 120; }

        /* Backdrop (page background image and effects) */
        .sx-backdrop { position: fixed; inset: 0; z-index: -1; overflow: hidden; background: var(--ground); }
        .sx-backdrop-img { position: absolute; inset: -40px; background-size: cover; background-position: center; }
        .sx-backdrop-overlay { position: absolute; inset: 0; }
        .sx-aurora span { position: absolute; width: 70vmax; height: 70vmax; border-radius: 9999px; filter: blur(90px); opacity: {{ $dark ? '.4' : '.25' }}; animation: sx-float 22s ease-in-out infinite alternate; }
        .sx-aurora span:nth-child(1) { background: var(--accent); top: -30vmax; left: -20vmax; }
        .sx-aurora span:nth-child(2) { background: {{ $gradientTo }}; bottom: -35vmax; right: -25vmax; animation-delay: -7s; }
        @keyframes sx-float { 50% { transform: translate(6vmax, 4vmax) scale(1.12); } 100% { transform: translate(-4vmax, 8vmax) scale(.95); } }
        .sx-grid { position: absolute; inset: 0; background-image: linear-gradient({{ $rgba($accent, .12) }} 1px, transparent 1px), linear-gradient(90deg, {{ $rgba($accent, .12) }} 1px, transparent 1px); background-size: 40px 40px; mask-image: radial-gradient(ellipse at top, #000 30%, transparent 75%); }
        .sx-dots { position: absolute; inset: 0; background-image: radial-gradient({{ $rgba($accent, .3) }} 1px, transparent 1.5px); background-size: 20px 20px; }
        .sx-mesh { position: absolute; inset: 0; background: radial-gradient(at 15% 10%, {{ $rgba($accent, .3) }} 0, transparent 50%), radial-gradient(at 85% 20%, {{ $rgba($deep, .25) }} 0, transparent 50%); }
        @if ($hasBackdrop) body { background: transparent; } @endif

        /* Navigation */
        .sx-nav { position: fixed; inset: 0 0 auto 0; z-index: 110; transition: background .4s var(--ease), box-shadow .4s var(--ease), padding .4s var(--ease); padding: 18px 0; }
        .sx-has-preview .sx-nav { top: 33px; }
        .sx-nav.sx-scrolled { background: {{ $rgba($c['bg'], .86) }}; -webkit-backdrop-filter: saturate(1.4) blur(14px); backdrop-filter: saturate(1.4) blur(14px); box-shadow: 0 1px 0 var(--line); padding: 10px 0; }
        .sx-nav .sx-wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .sx-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; font-weight: 800; letter-spacing: -.01em; min-width: 0; }
        .sx-brand > span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sx-mono { width: 44px; height: 44px; border-radius: 50%; background: var(--accent); color: var(--accent-text); display: grid; place-items: center; font-family: var(--serif); font-weight: 600; font-size: 17px; flex: none; box-shadow: 0 6px 18px {{ $rgba($accent, .3) }}; }
        .sx-links { display: flex; align-items: center; gap: 4px; list-style: none; margin: 0; padding: 0; }
        .sx-links a { display: block; padding: 10px 14px; border-radius: 999px; text-decoration: none; font-weight: 600; font-size: 15px; color: var(--ink-2); position: relative; transition: color .3s; }
        .sx-links a::after { content: ""; position: absolute; left: 14px; right: 14px; bottom: 6px; height: 2px; background: var(--accent); transform: scaleX(0); transform-origin: left; transition: transform .4s var(--ease); }
        .sx-links a:hover, .sx-links a.sx-active { color: var(--ink); }
        .sx-links a:hover::after, .sx-links a.sx-active::after { transform: scaleX(1); }
        .sx-menu { display: none; width: 48px; height: 48px; border-radius: 50%; border: 1.5px solid var(--line); background: var(--paper); cursor: pointer; align-items: center; justify-content: center; }
        .sx-menu span, .sx-menu span::before, .sx-menu span::after { display: block; width: 18px; height: 2px; background: var(--ink); transition: transform .35s var(--ease), background .3s; }
        .sx-menu span { position: relative; }
        .sx-menu span::before, .sx-menu span::after { content: ""; position: absolute; left: 0; }
        .sx-menu span::before { top: -6px; } .sx-menu span::after { top: 6px; }
        .sx-nav.sx-open .sx-menu span { background: transparent; }
        .sx-nav.sx-open .sx-menu span::before { transform: translateY(6px) rotate(45deg); }
        .sx-nav.sx-open .sx-menu span::after { transform: translateY(-6px) rotate(-45deg); }

        /* Buttons */
        .sx-btn { --bg: var(--accent); display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 48px; padding: 0 26px; border-radius: var(--radius); border: 0; cursor: pointer; font: 700 15px/1 var(--sans); text-decoration: none; color: var(--accent-text); background: var(--bg); position: relative; overflow: hidden; isolation: isolate; transition: transform .35s var(--ease), box-shadow .35s var(--ease), color .35s; }
        .sx-btn::before { content: ""; position: absolute; inset: 0; background: var(--navy); transform: translateY(101%); transition: transform .45s var(--ease); z-index: -1; }
        .sx-btn:hover { color: #fff; box-shadow: 0 14px 30px {{ $rgba($deep, .25) }}; }
        .sx-btn:hover::before { transform: translateY(0); }
        .sx-btn .sx-i { width: 18px; height: 18px; transition: transform .35s var(--ease); }
        .sx-btn:hover .sx-arrow { transform: translateX(4px); }
        .sx-btn.sx-ghost { background: transparent; color: var(--navy-2); box-shadow: inset 0 0 0 1.5px var(--navy-2); }
        .sx-btn.sx-ghost:hover { color: #fff; }
        .sx-btn.sx-navy { --bg: var(--navy); color: #fff; }
        .sx-btn.sx-navy::before { background: var(--accent); }
        .sx-round { width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; background: var(--paper); border: 1px solid var(--line); color: var(--accent); text-decoration: none; transition: transform .35s var(--ease), background .3s, color .3s; }
        .sx-round:hover { transform: translateY(-4px); background: var(--accent); color: var(--accent-text); }
        @if ($t['glow'])
            .sx-btn:not(.sx-ghost), .sx-mono { box-shadow: 0 0 0 1px {{ $rgba($accent, .6) }}, 0 10px 34px -6px {{ $rgba($accent, .7) }}; }
            .sx-frame { box-shadow: 0 0 0 1px {{ $rgba($accent, .5) }}, 0 0 80px -10px {{ $rgba($accent, .55) }} !important; }
            .sx-h1 .sx-line:nth-child(2) span { text-shadow: 0 0 28px {{ $rgba($accent, .55) }}; }
        @endif

        /* Hero */
        .sx-hero { position: relative; padding: 150px 0 90px; overflow: hidden; isolation: isolate; }
        .sx-topo { position: absolute; inset: 0; z-index: -1; opacity: .55; pointer-events: none; width: 100%; height: 100%; }
        .sx-hero-grid { display: grid; grid-template-columns: 1.08fr .92fr; gap: 64px; align-items: center; }
        .sx-chip { display: inline-flex; align-items: center; gap: 10px; padding: 8px 16px 8px 12px; border-radius: 999px; background: var(--paper); border: 1px solid var(--line); font-size: 14px; font-weight: 700; color: var(--navy-2); box-shadow: 0 4px 14px {{ $rgba($deep, .06) }}; }
        .sx-pulse { width: 9px; height: 9px; border-radius: 50%; background: #1f8a4c; position: relative; flex: none; }
        .sx-pulse::after { content: ""; position: absolute; inset: -5px; border-radius: 50%; border: 2px solid #1f8a4c; animation: sx-ping 2s var(--ease) infinite; }
        @keyframes sx-ping { 0% { transform: scale(.5); opacity: 1; } 100% { transform: scale(1.6); opacity: 0; } }
        .sx-greet { font-weight: 600; color: var(--accent); font-size: 21px; margin: 26px 0 6px; }
        .sx-h1 { font-family: var(--serif); font-weight: 600; font-size: clamp(48px, 8vw, 104px); line-height: .98; letter-spacing: -.035em; margin: 0; overflow-wrap: anywhere; }
        .sx-h1 .sx-line { display: block; overflow: hidden; padding-bottom: .08em; }
        .sx-h1 .sx-line span { display: inline-block; transition: transform 1.1s var(--ease); }
        .sx-js .sx-h1 .sx-line span { transform: translateY(110%); }
        .sx-h1 .sx-line:nth-child(2) span { transition-delay: .12s; font-style: italic; font-weight: 400; color: var(--accent); }
        .sx-loaded .sx-h1 .sx-line span { transform: none; }
        .sx-role { font-weight: 700; color: var(--navy-2); font-size: 19px; margin: 22px 0 0; display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center; }
        .sx-role i { width: 5px; height: 5px; border-radius: 50%; background: var(--gold); display: inline-block; }
        .sx-lead { font-size: 19px; color: var(--ink-2); max-width: 540px; margin: 18px 0 32px; }
        .sx-ctas { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; }
        .sx-quick { display: flex; gap: 10px; }
        .sx-stats { display: grid; grid-template-columns: repeat(var(--n, 3), minmax(0, 1fr)); gap: 20px; margin-top: 48px; padding-top: 28px; border-top: 1px solid var(--line); }
        .sx-stat b { display: block; font-family: var(--serif); font-weight: 600; font-size: clamp(30px, 4vw, 46px); line-height: 1; letter-spacing: -.02em; }
        .sx-stat span { font-size: 14px; color: var(--muted); font-weight: 600; }
        .sx-portrait { position: relative; justify-self: end; width: 100%; max-width: 460px; }
        .sx-pwrap { position: relative; isolation: isolate; }
        .sx-frame { position: relative; border-radius: 200px 200px 28px 28px; overflow: hidden; aspect-ratio: 4 / 5; box-shadow: 0 40px 80px -20px {{ $rgba($deep, .35) }}; transition: clip-path 1.4s var(--ease) .2s; background: var(--blush); }
        .sx-js .sx-frame { clip-path: inset(100% 0 0 0 round 200px 200px 28px 28px); }
        .sx-loaded .sx-frame { clip-path: inset(0 0 0 0 round 200px 200px 28px 28px); }
        .sx-frame img { width: 100%; height: 100%; object-fit: cover; transition: transform 1.8s var(--ease) .2s; transform: scale(1.04); }
        .sx-js .sx-frame img { transform: scale(1.18); }
        .sx-loaded .sx-frame img { transform: scale(1.04); }
        .sx-ph { position: absolute; inset: 0; display: grid; place-items: center; color: var(--accent); font-family: var(--serif); font-size: clamp(80px, 12vw, 150px); font-weight: 600; letter-spacing: -.04em; background: radial-gradient(circle at 50% 30%, var(--paper), var(--blush) 70%); }
        .sx-outline { position: absolute; inset: -18px -18px 18px 18px; border: 1.5px solid var(--accent); border-radius: 200px 200px 28px 28px; z-index: -1; opacity: .5; }
        .sx-logo-badge { position: absolute; right: 18px; top: 42%; background: #fff; border-radius: 16px; padding: 10px; box-shadow: 0 18px 40px -16px {{ $rgba($deep, .4) }}; }
        .sx-logo-badge img { height: 40px; max-width: 110px; object-fit: contain; }
        .sx-float { background: {{ $dark ? $rgba($c['surface'], .92) : 'rgba(255,255,255,.92)' }}; border-radius: 18px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 700; color: var(--navy-2); line-height: 1.35; min-width: 0; }
        .sx-float small { display: block; font-weight: 600; color: var(--muted); font-size: 12px; }
        .sx-float > span:last-child { min-width: 0; overflow-wrap: anywhere; }
        .sx-ic { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; background: var(--blush); color: var(--accent); flex: none; }
        .sx-info { display: grid; grid-template-columns: repeat(var(--n, 2), minmax(0, 1fr)); gap: 14px; margin-top: 22px; }
        .sx-info .sx-float { box-shadow: 0 12px 30px -12px {{ $rgba($deep, .22) }}; border: 1px solid var(--line); transition: opacity .9s var(--ease) 1.2s, transform .9s var(--ease) 1.2s; }
        .sx-js .sx-info .sx-float { opacity: 0; transform: translateY(16px); }
        .sx-info .sx-float:nth-child(2) { transition-delay: 1.35s; }
        .sx-loaded .sx-info .sx-float { opacity: 1; transform: none; }
        .sx-cue { display: inline-flex; align-items: center; gap: 10px; margin-top: 56px; font-size: 13px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); text-decoration: none; }
        .sx-mouse { width: 24px; height: 38px; border: 2px solid currentColor; border-radius: 14px; position: relative; }
        .sx-mouse::after { content: ""; position: absolute; left: 50%; top: 7px; width: 4px; height: 8px; margin-left: -2px; border-radius: 2px; background: var(--accent); animation: sx-wheel 1.8s var(--ease) infinite; }
        @keyframes sx-wheel { 0% { opacity: 0; transform: translateY(-4px); } 40% { opacity: 1; } 100% { opacity: 0; transform: translateY(10px); } }

        /* Marquee */
        .sx-mq-wrap { overflow: hidden; padding: 24px 0; }
        .sx-marquee { background: var(--navy); color: #fff; overflow: hidden; padding: 22px 0; transform: rotate(-1.2deg); margin: 0 -20px; box-shadow: 0 20px 40px -20px {{ $rgba($deep, .5) }}; }
        .sx-track { display: flex; gap: 48px; width: max-content; animation: sx-marq 38s linear infinite; }
        .sx-marquee:hover .sx-track { animation-play-state: paused; }
        .sx-track span { font-family: var(--serif); font-size: clamp(22px, 2.6vw, 32px); white-space: nowrap; display: flex; align-items: center; gap: 48px; }
        .sx-track span::after { content: ""; width: 12px; height: 12px; background: var(--gold); transform: rotate(45deg); }
        @keyframes sx-marq { to { transform: translateX(-50%); } }

        /* Sections */
        section { scroll-margin-top: 80px; }
        .sx-sec { padding: 120px 0; }
        .sx-eyebrow { display: inline-flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: var(--accent); margin: 0 0 18px; }
        .sx-eyebrow::before { content: ""; width: 34px; height: 2px; background: currentColor; }
        .sx-h2 { font-family: var(--serif); font-weight: 600; font-size: clamp(36px, 5vw, 60px); line-height: 1.06; letter-spacing: -.025em; margin: 0; }
        .sx-h2 em { font-weight: 400; color: var(--accent); }
        .sx-sub { color: var(--muted); max-width: 560px; font-size: 18px; margin: 18px 0 0; }
        .sx-rv { transition: opacity 1s var(--ease), transform 1s var(--ease); }
        .sx-js .sx-rv { opacity: 0; transform: translateY(40px); }
        .sx-js .sx-rv.sx-in { opacity: 1; transform: none; }
        .sx-d1 { transition-delay: .1s; } .sx-d2 { transition-delay: .2s; } .sx-d3 { transition-delay: .3s; }

        .sx-about { display: grid; grid-template-columns: .9fr 1.1fr; gap: 80px; align-items: start; }
        .sx-about-text p { margin: 0 0 20px; color: var(--ink-2); font-size: 18px; white-space: pre-line; }
        .sx-about-text p:first-child::first-letter { font-family: var(--serif); font-size: 4.4em; float: left; line-height: .82; margin: 8px 12px 0 0; color: var(--accent); }
        .sx-sticky { position: sticky; top: 120px; }
        .sx-sig { font-family: var(--serif); font-style: italic; font-size: 30px; color: var(--navy-2); margin-top: 28px; }
        .sx-values { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 36px; }
        .sx-value { background: var(--paper); border: 1px solid var(--line); border-radius: 18px; padding: 22px; transition: transform .5s var(--ease), box-shadow .5s var(--ease), border-color .5s, opacity 1s var(--ease); }
        .sx-value:hover, .sx-js .sx-value.sx-rv.sx-in:hover { transform: translateY(-6px); box-shadow: 0 24px 40px -18px {{ $rgba($deep, .22) }}; }
        .sx-value .sx-ic { width: 44px; height: 44px; margin-bottom: 14px; }
        .sx-value h3 { margin: 0 0 4px; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); font-weight: 800; }
        .sx-value p { margin: 0; font-size: 17px; font-weight: 700; color: var(--ink); overflow-wrap: anywhere; }

        /* Cards (social accounts and links) */
        .sx-v-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: 24px; margin-bottom: 56px; }
        .sx-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; perspective: 1200px; }
        .sx-card { position: relative; background: var(--paper); border: 1px solid var(--line); border-radius: var(--r); padding: 30px 28px 26px; transform-style: preserve-3d; overflow: hidden; min-height: 250px; display: flex; flex-direction: column; text-decoration: none; color: inherit; }
        .sx-cards .sx-card.sx-rv { transition: opacity 1s var(--ease), transform .9s var(--ease), box-shadow .5s var(--ease); }
        .sx-card:hover { box-shadow: 0 40px 70px -30px {{ $rgba($deep, .35) }}; }
        .sx-card::before { content: ""; position: absolute; inset: 0; background: radial-gradient(420px circle at var(--mx, 50%) var(--my, 0%), {{ $rgba($accent, .1) }}, transparent 45%); opacity: 0; transition: opacity .4s; }
        .sx-card:hover::before { opacity: 1; }
        .sx-num { position: absolute; right: 22px; top: 10px; font-family: var(--serif); font-size: 84px; line-height: 1; color: var(--blush); font-weight: 600; transition: color .5s; }
        .sx-card:hover .sx-num { color: var(--blush-2); }
        .sx-tile { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 18px; position: relative; font-family: var(--serif); }
        .sx-tile .sx-i { width: 26px; height: 26px; }
        .sx-card h3 { font-size: 22px; font-weight: 700; letter-spacing: -.01em; margin: 24px 0 2px; position: relative; }
        .sx-meta { font-size: 14px; font-weight: 700; color: var(--muted); position: relative; overflow-wrap: anywhere; }
        .sx-kpi { margin-top: auto; padding-top: 20px; border-top: 1px dashed var(--line); display: flex; align-items: center; justify-content: space-between; gap: 12px; font-weight: 700; color: var(--navy-2); font-size: 15px; position: relative; }
        .sx-kpi b { font-family: var(--serif); font-size: 26px; font-weight: 600; letter-spacing: -.02em; }
        .sx-arrow-c { width: 42px; height: 42px; flex: none; border-radius: 50%; display: grid; place-items: center; background: var(--ground); transition: background .4s, color .4s, transform .4s var(--ease); }
        .sx-card:hover .sx-arrow-c { background: var(--accent); color: var(--accent-text); transform: rotate(-45deg); }
        .sx-pills { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; }

        /* Motto band */
        .sx-band { position: relative; background: var(--navy); color: #fff; overflow: hidden; padding: 140px 0 210px; text-align: center; }
        .sx-band-img { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: .28; }
        .sx-stars { position: absolute; inset: 0; background-image: radial-gradient(1.5px 1.5px at 20% 30%, #fff 50%, transparent 51%), radial-gradient(1px 1px at 70% 20%, #fff 50%, transparent 51%), radial-gradient(1.2px 1.2px at 40% 12%, #fff 50%, transparent 51%), radial-gradient(1px 1px at 85% 45%, #fff 50%, transparent 51%), radial-gradient(1.4px 1.4px at 10% 60%, #fff 50%, transparent 51%), radial-gradient(1px 1px at 58% 40%, #fff 50%, transparent 51%); opacity: .5; animation: sx-tw 6s ease-in-out infinite alternate; }
        @keyframes sx-tw { to { opacity: .2; } }
        .sx-moon { position: absolute; top: 70px; right: 14%; width: 70px; height: 70px; border-radius: 50%; background: #f4e7cf; box-shadow: 0 0 60px 10px rgba(244, 231, 207, .25); }
        .sx-range { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: auto; will-change: transform; }
        .sx-quote { position: relative; z-index: 2; max-width: 880px; margin: 0 auto; padding: 0 24px; }
        .sx-quote .sx-mark { font-family: var(--serif); font-size: 120px; line-height: .5; color: var(--gold); display: block; margin-bottom: 24px; }
        .sx-quote blockquote { margin: 0; font-family: var(--serif); font-size: clamp(26px, 3.8vw, 46px); line-height: 1.22; letter-spacing: -.015em; }
        .sx-quote cite { display: block; margin-top: 28px; font-style: normal; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; font-size: 13px; color: {{ $mix($deep, '#ffffff', 0.78) }}; }

        /* Services and hours */
        .sx-exp { display: grid; grid-template-columns: .8fr 1.2fr; gap: 80px; }
        .sx-exp-list { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--line); }
        .sx-exp-item { border-bottom: 1px solid var(--line); display: grid; grid-template-columns: 56px 1fr 44px; align-items: center; gap: 12px; padding: 24px 4px; }
        .sx-exp-item .sx-n { font-family: var(--serif); color: var(--accent); font-size: 18px; }
        .sx-exp-item .sx-t { font-size: clamp(19px, 2.2vw, 25px); font-weight: 700; letter-spacing: -.01em; transition: transform .5s var(--ease), color .3s; }
        .sx-exp-item:hover .sx-t { transform: translateX(10px); color: var(--accent); }
        .sx-plus { width: 40px; height: 40px; border-radius: 50%; border: 1.5px solid var(--line); display: grid; place-items: center; transition: transform .5s var(--ease), background .3s, border-color .3s, color .3s; }
        .sx-plus .sx-i { width: 16px; height: 16px; }
        .sx-exp-item:hover .sx-plus { transform: rotate(-45deg); background: var(--accent); border-color: var(--accent); color: var(--accent-text); }
        .sx-hours { margin-top: 32px; background: var(--paper); border: 1px solid var(--line); border-radius: 20px; padding: 22px 26px; }
        .sx-hours h3 { margin: 0 0 8px; font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
        .sx-hours h3 .sx-i { color: var(--accent); width: 18px; height: 18px; }
        .sx-hours div { display: flex; justify-content: space-between; gap: 16px; padding: 6px 0; font-size: 15px; border-top: 1px dashed var(--line); }
        .sx-hours dt { color: var(--muted); } .sx-hours dd { margin: 0; text-align: right; }
        .sx-hours .sx-today { font-weight: 800; } .sx-hours .sx-today dt { color: var(--accent); }

        /* Portfolio */
        .sx-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; margin-top: 56px; }
        .sx-shot { all: unset; box-sizing: border-box; cursor: pointer; display: block; border-radius: 20px; overflow: hidden; position: relative; background: var(--paper); border: 1px solid var(--line); }
        .sx-shot img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; transition: transform .8s var(--ease); }
        .sx-shot:hover img { transform: scale(1.06); }
        .sx-shot span { display: block; padding: 12px 16px; font-size: 14px; font-weight: 700; color: var(--ink-2); }

        /* Map */
        .sx-map { display: grid; grid-template-columns: 1fr 1.2fr; gap: 0; border-radius: var(--r); overflow: hidden; background: var(--paper); border: 1px solid var(--line); }
        .sx-map-info { padding: 40px; display: flex; flex-direction: column; gap: 18px; justify-content: center; }
        .sx-map-frame { min-height: 300px; background: var(--blush); position: relative; }
        .sx-map-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .sx-map-load { all: unset; cursor: pointer; position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; font-weight: 700; color: var(--accent); background-image: radial-gradient({{ $rgba($accent, .25) }} 1px, transparent 1.5px); background-size: 18px 18px; }
        .sx-map-load .sx-i { width: 32px; height: 32px; }

        /* Contact */
        .sx-contact { position: relative; background: var(--accent); color: var(--accent-text); border-radius: 36px; padding: 72px 64px; overflow: hidden; display: grid; grid-template-columns: 1fr 1fr; gap: 56px; }
        .sx-contact.sx-solo { grid-template-columns: 1fr; }
        .sx-contact::before { content: ""; position: absolute; width: 520px; height: 520px; border-radius: 50%; border: 1px solid {{ $rgba($p['accent_text'], .18) }}; right: -180px; top: -200px; }
        .sx-contact::after { content: ""; position: absolute; width: 340px; height: 340px; border-radius: 50%; border: 1px solid {{ $rgba($p['accent_text'], .14) }}; right: -90px; top: -110px; }
        .sx-contact .sx-h2, .sx-contact .sx-h2 em { color: var(--accent-text); }
        .sx-contact .sx-h2 em { opacity: .85; }
        .sx-contact .sx-sub { color: {{ $rgba($p['accent_text'], .86) }}; }
        .sx-contact .sx-eyebrow { color: {{ $p['accent_text'] === '#ffffff' ? '#ffd9a0' : 'inherit' }}; }
        .sx-c-list { list-style: none; margin: 36px 0 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
        .sx-c-list li { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; font-weight: 600; }
        .sx-c-list .sx-ic { width: 46px; height: 46px; border-radius: 14px; background: {{ $rgba($p['accent_text'], .14) }}; color: inherit; }
        .sx-c-list a { text-decoration: none; overflow-wrap: anywhere; min-width: 0; }
        .sx-c-list a:hover { text-decoration: underline; text-underline-offset: 4px; }
        .sx-c-list small { opacity: .75; }
        .sx-copy { border: 1px solid {{ $rgba($p['accent_text'], .5) }}; background: transparent; color: inherit; border-radius: 999px; padding: 6px 12px; font: 700 12px var(--sans); cursor: pointer; min-height: 32px; transition: background .3s, color .3s; }
        .sx-copy:hover { background: var(--accent-text); color: var(--accent); }
        .sx-socials { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }
        .sx-socials a { width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; border: 1.5px solid {{ $rgba($p['accent_text'], .45) }}; transition: background .3s, color .3s, transform .4s var(--ease); }
        .sx-socials a:hover { background: var(--accent-text); color: var(--accent); transform: translateY(-4px); }
        .sx-form { position: relative; z-index: 1; background: {{ $dark ? $c['surface'] : '#ffffff' }}; color: var(--ink); border-radius: 26px; padding: 34px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 30px 60px -20px rgba(0, 0, 0, .35); }
        .sx-form h3 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
        .sx-field { position: relative; }
        .sx-field input, .sx-field textarea { width: 100%; font: 500 16px var(--sans); color: var(--ink); background: var(--ground); border: 1.5px solid var(--line); border-radius: 14px; padding: 22px 16px 8px; transition: border-color .3s; resize: vertical; }
        .sx-field label { position: absolute; left: 17px; top: 15px; font-size: 15px; color: var(--muted); pointer-events: none; transition: all .25s var(--ease); }
        .sx-field input:focus, .sx-field textarea:focus { outline: none; border-color: var(--accent); box-shadow: none; }
        .sx-field input:focus + label, .sx-field input:not(:placeholder-shown) + label, .sx-field textarea:focus + label, .sx-field textarea:not(:placeholder-shown) + label { top: 6px; font-size: 12px; font-weight: 700; color: var(--accent); }

        /* Save and share */
        .sx-share { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 28px; }
        .sx-share button, .sx-share a { display: inline-flex; align-items: center; gap: 8px; min-height: 46px; padding: 0 20px; border-radius: var(--radius); background: var(--paper); border: 1px solid var(--line); font: 700 14px var(--sans); color: var(--ink); text-decoration: none; cursor: pointer; transition: transform .35s var(--ease), border-color .3s; }
        .sx-share button:hover, .sx-share a:hover { transform: translateY(-3px); border-color: var(--accent); }
        .sx-share .sx-i { width: 18px; height: 18px; color: var(--accent); }

        footer.sx-foot { padding: 40px 0 50px; }
        footer.sx-foot .sx-wrap { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; color: var(--muted); font-size: 14px; }
        footer.sx-foot a { text-decoration: none; }
        footer.sx-foot a:hover { text-decoration: underline; }
        .sx-totop { width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; background: var(--navy); color: #fff; text-decoration: none; transition: transform .4s var(--ease); }
        .sx-totop:hover { transform: translateY(-4px); text-decoration: none !important; }
        .sx-toast { position: fixed; left: 50%; bottom: 28px; transform: translate(-50%, 220%); visibility: hidden; background: var(--navy); color: #fff; padding: 14px 22px; border-radius: 999px; font-weight: 700; font-size: 14px; z-index: 130; transition: transform .5s var(--ease); box-shadow: 0 18px 40px {{ $rgba($deep, .35) }}; }
        .sx-toast.sx-show { transform: translate(-50%, 0); visibility: visible; }
        .sx-modal { position: fixed; inset: 0; z-index: 150; display: grid; place-items: center; background: rgba(0, 0, 0, .6); padding: 24px; }
        .sx-modal-card { width: 100%; max-width: 320px; border-radius: 28px; background: #fff; color: #111827; padding: 26px; text-align: center; }
        .sx-modal-card svg { width: 100%; height: auto; }
        .sx-lightbox { position: fixed; inset: 0; z-index: 150; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background: rgba(0, 0, 0, .86); padding: 16px; color: #fff; }
        .sx-lightbox img { max-height: 80vh; max-width: 100%; border-radius: 14px; object-fit: contain; }

        @if ($t['card_style'] === 'glass')
            .sx-value, .sx-card, .sx-hours, .sx-map, .sx-chip, .sx-shot { background: {{ $rgba($c['surface'], $dark ? .55 : .62) }}; -webkit-backdrop-filter: blur(18px) saturate(150%); backdrop-filter: blur(18px) saturate(150%); border-color: {{ $dark ? 'rgba(255,255,255,.12)' : 'rgba(255,255,255,.7)' }}; }
        @elseif ($t['card_style'] === 'outline')
            .sx-value, .sx-card, .sx-hours, .sx-map, .sx-shot { background: transparent; border-color: {{ $rgba($accent, .45) }}; }
        @endif

        @media (max-width: 980px) {
            .sx-hero-grid, .sx-about, .sx-exp, .sx-contact, .sx-map { grid-template-columns: 1fr; gap: 56px; }
            .sx-map { gap: 0; }
            .sx-portrait { justify-self: center; max-width: 400px; }
            .sx-sticky { position: static; }
            .sx-contact { padding: 56px 32px; }
        }
        @media (max-width: 860px) {
            .sx-menu { display: inline-flex; }
            .sx-links { position: fixed; inset: 72px 16px auto 16px; flex-direction: column; align-items: stretch; background: var(--paper); border: 1px solid var(--line); border-radius: 22px; padding: 14px; box-shadow: 0 30px 60px -20px {{ $rgba($deep, .3) }}; opacity: 0; transform: translateY(-12px); pointer-events: none; transition: opacity .35s var(--ease), transform .35s var(--ease); }
            .sx-nav.sx-open .sx-links { opacity: 1; transform: none; pointer-events: auto; }
            .sx-links a { padding: 14px 16px; }
            .sx-links a::after { display: none; }
            .sx-nav-cta { display: none !important; }
        }
        @media (max-width: 640px) {
            body { font-size: 16px; }
            .sx-sec { padding: 84px 0; }
            .sx-hero { padding: 112px 0 64px; }
            .sx-values { grid-template-columns: 1fr; }
            .sx-stats { gap: 12px; }
            .sx-contact { padding: 44px 22px; border-radius: 28px; }
            .sx-form { padding: 24px; }
            .sx-band { padding: 100px 0 160px; }
            .sx-map-info { padding: 28px; }
            .sx-cue { display: none; }
        }
        @media (max-width: 480px) {
            .sx-info { grid-template-columns: 1fr; }
            .sx-stats { grid-template-columns: 1fr 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
            .sx-js .sx-rv, .sx-js .sx-info .sx-float { opacity: 1; transform: none; }
            .sx-js .sx-h1 .sx-line span { transform: none; }
            .sx-js .sx-frame { clip-path: none; }
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="{{ $preview ? 'sx-has-preview' : '' }}" x-data="{ qr: false, photo: null, shared: false }">
<a class="sx-skip" href="#main">Skip to content</a>
@if ($preview)
    <div class="sx-preview">Preview{{ $available ? '' : ' · this profile is not live, visitors see a "not available" page' }} · visits here aren't counted</div>
@endif
<div class="sx-progress" aria-hidden="true"></div>
@if ($hasBackdrop)
    <div class="sx-backdrop" aria-hidden="true">
        @if ($t['effect'] === 'aurora')<div class="sx-aurora"><span></span><span></span></div>@endif
        @if ($t['effect'] === 'mesh')<div class="sx-mesh"></div>@endif
        @if ($p['background_image'])
            <div class="sx-backdrop-img" style="background-image: url('{{ $p['background_image'] }}'); filter: blur({{ $p['background_blur'] }}px)"></div>
            <div class="sx-backdrop-overlay" style="background: {{ $rgba($c['bg'], $p['background_overlay'] / 100) }}"></div>
        @endif
        @if ($t['effect'] === 'grid')<div class="sx-grid"></div>@endif
        @if ($t['effect'] === 'dots')<div class="sx-dots"></div>@endif
    </div>
@endif

<header class="sx-nav" id="sx-nav">
    <div class="sx-wrap">
        <a href="#top" class="sx-brand" aria-label="{{ $p['name'] }}, back to top"><span class="sx-mono">{{ $initials($p['name']) }}</span><span>{{ $p['name'] }}</span></a>
        @if ($nav->isNotEmpty())
            <nav aria-label="Primary">
                <ul class="sx-links" id="sx-links">
                    @foreach ($nav as [$id, $label])<li><a href="#{{ $id }}">{{ $label }}</a></li>@endforeach
                </ul>
            </nav>
        @endif
        <div style="display: flex; gap: 10px; align-items: center">
            @if ($showContact)
                <a href="#contact" class="sx-btn sx-nav-cta sx-magnetic">Let’s talk</a>
            @elseif ($p['show_save_contact'] && $on('actions'))
                <a href="{{ $urls['vcard'] }}" class="sx-btn sx-nav-cta">Save contact</a>
            @endif
            @if ($nav->isNotEmpty())
                <button type="button" class="sx-menu" id="sx-menu" aria-label="Open menu" aria-expanded="false" aria-controls="sx-links"><span></span></button>
            @endif
        </div>
    </div>
</header>

<main id="main">
    {{-- Hero --}}
    <section class="sx-hero" id="top">
        <svg class="sx-topo" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true" fill="none" stroke="{{ $mix($c['bg'], $dark ? '#ffffff' : '#000000', 0.12) }}" stroke-width="1">
            @for ($i = 0; $i < 8; $i++)
                @php $y = 720 - $i * 60; @endphp
                <path d="M-50 {{ $y }} C {{ 200 + $i * 20 }} {{ $y - 80 }}, {{ 380 + $i * 20 }} {{ $y + 40 }}, {{ 620 + $i * 20 }} {{ $y - 40 }} S {{ 1040 + $i * 20 }} {{ $y - 160 }}, 1500 {{ $y - 80 }}"/>
            @endfor
        </svg>
        <div class="sx-wrap sx-hero-grid">
            <div>
                @if ($p['headline'])
                    <span class="sx-chip sx-rv"><span class="sx-pulse"></span>{{ $p['headline'] }}</span>
                @endif
                @if ($greeting)<p class="sx-greet sx-rv sx-d1">{{ $greeting }}</p>@endif
                <h1 class="sx-h1">
                    <span class="sx-line"><span>{{ $line1 }}{{ $line2 === '' ? '.' : '' }}</span></span>
                    @if ($line2 !== '')<span class="sx-line"><span>{{ $line2 }}.</span></span>@endif
                </h1>
                @php $roleBits = collect([$p['title'], $p['company'], $city])->filter()->unique(); @endphp
                @if ($roleBits->isNotEmpty())
                    <p class="sx-role sx-rv sx-d2">{!! $roleBits->map(fn ($b) => e($b))->implode(' <i></i> ') !!}</p>
                @endif
                @if ($lead)
                    <p class="sx-lead sx-rv sx-d3">{{ $lead }}</p>
                @else
                    <div style="height: 28px"></div>
                @endif
                @if ($on('actions') && ($actions->isNotEmpty() || $p['show_save_contact']))
                    <div class="sx-ctas sx-rv sx-d3" aria-label="Contact {{ $p['name'] }}">
                        @if ($p['show_save_contact'])
                            <a href="{{ $urls['vcard'] }}" class="sx-btn sx-magnetic"><x-lucide-user-plus class="sx-i" /> Save contact</a>
                        @endif
                        @if ($showContact)
                            <a href="#contact" class="sx-btn sx-ghost sx-magnetic">Let’s talk <x-lucide-arrow-right class="sx-i sx-arrow" /></a>
                        @endif
                        @if ($actions->isNotEmpty())
                            <span class="sx-quick">
                                @foreach ($actions as $a)
                                    <a href="{{ $a['href'] }}" data-track="{{ $a['track'] }}" class="sx-round" aria-label="{{ $a['label'] }}" title="{{ $a['label'] }}" @if (! empty($a['external'])) target="_blank" rel="noopener" @endif><x-dynamic-component :component="'lucide-'.$a['icon']" class="sx-i" /></a>
                                @endforeach
                            </span>
                        @endif
                    </div>
                @endif
                @if ($stats->isNotEmpty())
                    <div class="sx-stats sx-rv sx-d3" style="--n: {{ $stats->count() }}">
                        @foreach ($stats as $s)
                            @php $n = $stat($s['value']); @endphp
                            <div class="sx-stat"><b @if ($n) data-count="{{ $n['n'] }}" data-pre="{{ $n['pre'] }}" data-suf="{{ $n['suf'] }}" data-dec="{{ $n['dec'] }}" @endif>{{ $s['value'] }}</b><span>{{ $s['label'] }}</span></div>
                        @endforeach
                    </div>
                @endif
                @if ($nav->isNotEmpty())
                    <a href="#{{ $nav[0][0] }}" class="sx-cue sx-rv sx-d3"><span class="sx-mouse" aria-hidden="true"></span>Scroll to explore</a>
                @endif
            </div>
            <div class="sx-portrait" id="sx-portrait">
                <div class="sx-pwrap">
                    <div class="sx-outline" aria-hidden="true"></div>
                    <div class="sx-frame">
                        @if ($p['photo'])
                            <img src="{{ $p['photo'] }}" alt="Portrait of {{ $p['name'] }}" style="object-position: 50% 30%" fetchpriority="high">
                        @else
                            <div class="sx-ph" aria-hidden="true">{{ $p['initials'] }}</div>
                        @endif
                    </div>
                    @if ($p['company_logo'])
                        <span class="sx-logo-badge"><img src="{{ $p['company_logo'] }}" alt="{{ $p['company'] }}"></span>
                    @endif
                </div>
                @if ($info->isNotEmpty())
                    <div class="sx-info" style="--n: {{ $info->count() }}">
                        @foreach ($info as $x)
                            <div class="sx-float"><span class="sx-ic"><x-dynamic-component :component="'lucide-'.$x['icon']" class="sx-i" /></span><span>{{ $x['title'] }}<small>{{ $x['sub'] }}</small></span></div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Marquee of services --}}
    @if ($on('business') && count($p['services']) > 0)
        @php $words = collect($p['services'])->map(fn ($w) => '<span>'.e($w).'</span>')->implode(''); @endphp
        <div class="sx-mq-wrap"><div class="sx-marquee" aria-hidden="true"><div class="sx-track">{!! str_repeat($words, count($p['services']) < 4 ? 4 : 2) !!}</div></div></div>
    @endif

    @foreach ($p['sections'] as $section)
        @switch($section)
            @case('about')
                @if ($showAbout)
                    <section class="sx-sec" id="about">
                        <div class="sx-wrap sx-about">
                            <div class="sx-sticky">
                                <p class="sx-eyebrow sx-rv">About</p>
                                <h2 class="sx-h2 sx-rv sx-d1">{{ $p['title'] ? 'The story behind the' : 'A little about' }} <em>{{ $p['title'] ? 'work' : 'me' }}</em>.</h2>
                                <p class="sx-sig sx-rv sx-d2">— {{ $p['name'] }}</p>
                            </div>
                            <div>
                                <div class="sx-about-text">
                                    @foreach (array_merge($paras($p['intro']), $p['description'] !== $p['intro'] && $p['description'] !== $lead ? $paras($p['description']) : []) as $i => $para)
                                        <p class="sx-rv {{ $i ? 'sx-d'.min($i, 3) : '' }}">{{ $para }}</p>
                                    @endforeach
                                </div>
                                @if ($facts->isNotEmpty())
                                    <div class="sx-values">
                                        @foreach ($facts as $i => $f)
                                            <div class="sx-value sx-rv {{ $i ? 'sx-d'.min($i, 3) : '' }}"><div class="sx-ic"><x-lucide-sparkles class="sx-i" /></div><h3>{{ $f['label'] }}</h3><p>{{ $f['value'] }}</p></div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif
                @if ($motto)
                    <section class="sx-band" aria-label="Motto">
                        @if ($p['cover'])<div class="sx-band-img" style="background-image: url('{{ $p['cover'] }}')" aria-hidden="true"></div>@endif
                        <div class="sx-stars" aria-hidden="true"></div><div class="sx-moon" aria-hidden="true"></div>
                        <div class="sx-quote sx-rv"><span class="sx-mark" aria-hidden="true">“</span><blockquote>{{ $motto }}</blockquote><cite>{{ $p['name'] }}</cite></div>
                        <svg class="sx-range" data-speed="0.10" viewBox="0 0 1440 260" preserveAspectRatio="none" aria-hidden="true" style="bottom: 40px; opacity: .5"><path fill="{{ $mix($deep, '#ffffff', 0.22) }}" d="M0 260 L0 170 L90 120 L170 150 L260 70 L330 115 L420 40 L520 120 L600 90 L700 20 L790 100 L880 60 L980 130 L1060 50 L1150 110 L1240 70 L1340 130 L1440 90 L1440 260 Z"/><path fill="#fff" opacity=".8" d="M700 20 L730 45 L715 42 L700 55 L688 44 L672 46 Z M420 40 L448 62 L432 60 L420 70 L410 60 L396 62 Z M1060 50 L1086 72 L1070 69 L1060 80 L1050 70 L1036 72 Z"/></svg>
                        <svg class="sx-range" data-speed="0.18" viewBox="0 0 1440 220" preserveAspectRatio="none" aria-hidden="true" style="opacity: .85"><path fill="{{ $mix($deep, '#ffffff', 0.1) }}" d="M0 220 L0 150 L120 90 L210 130 L320 60 L430 140 L540 100 L650 150 L760 80 L870 140 L980 95 L1100 160 L1200 100 L1320 150 L1440 110 L1440 220 Z"/></svg>
                        <svg class="sx-range" data-speed="0" viewBox="0 0 1440 140" preserveAspectRatio="none" aria-hidden="true"><path fill="{{ $c['bg'] }}" d="M0 140 L0 100 C 200 60, 360 120, 560 90 S 980 40, 1180 80 S 1380 100, 1440 70 L1440 140 Z"/></svg>
                    </section>
                @endif
                @break

            @case('social')
                @if ($socialCards)
                    <section class="sx-sec" id="social" style="padding-top: 40px">
                        <div class="sx-wrap">
                            <div class="sx-v-head">
                                <div><p class="sx-eyebrow sx-rv">Follow</p><h2 class="sx-h2 sx-rv sx-d1">Find me <em>online</em>.</h2></div>
                                <p class="sx-sub sx-rv sx-d2" style="margin: 0">Follow along for updates, new work and behind-the-scenes.</p>
                            </div>
                            <div class="sx-cards">
                                @foreach ($p['social'] as $i => $s)
                                    @php $n = $s['count'] !== null ? $stat(\App\Services\Social\SocialStatsService::format($s['count'])) : null; @endphp
                                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener me" data-track="social" data-target="{{ $s['key'] }}" class="sx-card sx-tilt sx-rv {{ $i % 3 ? 'sx-d'.($i % 3) : '' }}">
                                        <span class="sx-num" aria-hidden="true">{{ $num($i) }}</span>
                                        <span class="sx-tile" style="background: {{ $s['background'] }}; color: {{ $s['text'] }}"><x-dynamic-component :component="'lucide-'.(IconLibrary::SOCIAL[$s['key']] ?? 'link')" class="sx-i" /></span>
                                        <h3>{{ $s['label'] }}</h3>
                                        <span class="sx-meta">{{ $s['verb'] }} on {{ $s['label'] }}</span>
                                        <span class="sx-kpi">
                                            @if ($s['count_label'])
                                                <span><b @if ($n) data-count="{{ $n['n'] }}" data-suf="{{ $n['suf'] }}" data-dec="{{ $n['dec'] }}" @endif>{{ \App\Services\Social\SocialStatsService::format($s['count']) }}</b> {{ \Illuminate\Support\Str::after($s['count_label'], ' ') }}</span>
                                            @else
                                                <span>{{ $s['verb'] }}</span>
                                            @endif
                                            <span class="sx-arrow-c"><x-lucide-arrow-right class="sx-i" style="width: 18px; height: 18px" /></span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @elseif ($socialIcons && ! $showContact)
                    <section class="sx-sec" style="padding: 40px 0">
                        <div class="sx-wrap" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 12px">
                            @foreach ($p['social'] as $s)
                                <a href="{{ $s['url'] }}" target="_blank" rel="noopener me" data-track="social" data-target="{{ $s['key'] }}" class="sx-round" aria-label="{{ $s['label'] }}" title="{{ $s['label'] }}{{ $s['count_label'] ? ' · '.$s['count_label'] : '' }}"><x-dynamic-component :component="'lucide-'.(IconLibrary::SOCIAL[$s['key']] ?? 'link')" class="sx-i" /></a>
                            @endforeach
                        </div>
                    </section>
                @endif
                @break

            @case('business')
                @if ($p['services'] || $p['hours'])
                    <section class="sx-sec" id="services">
                        <div class="sx-wrap sx-exp">
                            <div>
                                <p class="sx-eyebrow sx-rv">{{ $p['services'] ? 'Services' : 'Visit' }}</p>
                                <h2 class="sx-h2 sx-rv sx-d1">{!! $p['services'] ? 'How I can <em>help</em>.' : 'Opening <em>hours</em>.' !!}</h2>
                                @if ($p['hours'])
                                    <dl class="sx-hours sx-rv sx-d2">
                                        <h3><x-lucide-clock class="sx-i" /> Opening hours</h3>
                                        @foreach ($days as $key => $label)
                                            @if (! empty($p['hours'][$key]))
                                                <div class="{{ strtolower(now()->englishDayOfWeek) === $key ? 'sx-today' : '' }}"><dt>{{ $label }}</dt><dd>{{ $p['hours'][$key] }}</dd></div>
                                            @endif
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                            @if ($p['services'])
                                <ul class="sx-exp-list">
                                    @foreach ($p['services'] as $i => $service)
                                        <li class="sx-exp-item sx-rv {{ $i ? 'sx-d'.min((int) ceil($i / 2), 3) : '' }}">
                                            <span class="sx-n">{{ $num($i) }}</span><span class="sx-t">{{ $service }}</span>
                                            <span class="sx-plus" aria-hidden="true"><x-lucide-arrow-up-right class="sx-i" /></span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </section>
                @endif
                @break

            @case('links')
                @if ($p['links'])
                    <section class="sx-sec" id="work" style="padding-top: 40px">
                        <div class="sx-wrap">
                            <div class="sx-v-head"><div><p class="sx-eyebrow sx-rv">Work</p><h2 class="sx-h2 sx-rv sx-d1">Explore my <em>work</em>.</h2></div></div>
                            <div class="sx-cards">
                                @foreach ($p['links'] as $i => $l)
                                    <a href="{{ $l['url'] }}" target="_blank" rel="noopener" data-track="custom" data-target="link-{{ $i + 1 }}" class="sx-card sx-tilt sx-rv {{ $i % 3 ? 'sx-d'.($i % 3) : '' }}">
                                        <span class="sx-num" aria-hidden="true">{{ $num($i) }}</span>
                                        <span class="sx-tile" style="background: {{ $tile[$i % count($tile)] }}"><x-dynamic-component :component="'lucide-'.$l['icon']" class="sx-i" /></span>
                                        <h3>{{ $l['label'] }}</h3>
                                        <span class="sx-meta">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($l['url'], '/')) }}</span>
                                        <span class="sx-kpi"><span>Open</span><span class="sx-arrow-c"><x-lucide-arrow-right class="sx-i" style="width: 18px; height: 18px" /></span></span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('ctas')
                @if ($p['ctas'])
                    <section class="sx-sec" style="padding: 40px 0" aria-label="Links">
                        <div class="sx-wrap sx-pills sx-rv">
                            @foreach ($p['ctas'] as $i => $cta)
                                <a href="{{ $cta['url'] }}" target="_blank" rel="noopener" data-track="custom" data-target="cta-{{ $i + 1 }}" class="sx-btn sx-magnetic {{ $cta['style'] === 'outline' ? 'sx-ghost' : '' }}">{{ $cta['label'] }} <x-lucide-arrow-up-right class="sx-i sx-arrow" /></a>
                            @endforeach
                        </div>
                    </section>
                @endif
                @break

            @case('portfolio')
                @if ($p['portfolio'])
                    <section class="sx-sec" id="portfolio" style="padding-top: 40px">
                        <div class="sx-wrap">
                            <p class="sx-eyebrow sx-rv">Portfolio</p>
                            <h2 class="sx-h2 sx-rv sx-d1">Selected <em>work</em>.</h2>
                            <div class="sx-gallery">
                                @foreach ($p['portfolio'] as $i => $item)
                                    <button type="button" class="sx-shot sx-rv {{ $i % 3 ? 'sx-d'.($i % 3) : '' }}" @click="photo = @js(['url' => $item['url'], 'caption' => $item['caption']])">
                                        <img src="{{ $item['url'] }}" alt="{{ $item['caption'] ?? 'Portfolio image' }}" loading="lazy">
                                        @if ($item['caption'])<span>{{ $item['caption'] }}</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('map')
                @if ($p['map_url'])
                    <section class="sx-sec" id="location" style="padding-top: 40px" x-data="{ map: false }">
                        <div class="sx-wrap">
                            <div class="sx-map sx-rv">
                                <div class="sx-map-info">
                                    <p class="sx-eyebrow" style="margin: 0">Location</p>
                                    <h2 class="sx-h2" style="font-size: clamp(30px, 4vw, 44px)">Come and <em>say hello</em>.</h2>
                                    <p class="sx-sub" style="margin: 0">{{ $p['address'] ?: 'See the map for directions.' }}</p>
                                    <div><a href="{{ $p['map_url'] }}" target="_blank" rel="noopener" data-track="custom" data-target="map" class="sx-btn">Directions <x-lucide-arrow-up-right class="sx-i sx-arrow" /></a></div>
                                </div>
                                <div class="sx-map-frame">
                                    @if ($p['map_embed'])
                                        {{-- Click-to-load keeps Google from seeing visitors who don't use the map. --}}
                                        <template x-if="map"><iframe src="{{ $p['map_embed'] }}" loading="lazy" referrerpolicy="no-referrer" title="Map"></iframe></template>
                                        <button type="button" class="sx-map-load" x-show="! map" @click="map = true"><x-lucide-map class="sx-i" /> Show map</button>
                                    @else
                                        <a href="{{ $p['map_url'] }}" target="_blank" rel="noopener" class="sx-map-load"><x-lucide-map-pin class="sx-i" /> Open in Maps</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('contact')
                @if ($showContact)
                    @php $form = (bool) $p['email']; @endphp
                    <section class="sx-sec" id="contact" style="padding-top: 20px">
                        <div class="sx-wrap">
                            <div class="sx-contact sx-rv {{ $form ? '' : 'sx-solo' }}">
                                <div style="position: relative; z-index: 1; min-width: 0">
                                    <p class="sx-eyebrow">Contact</p>
                                    <h2 class="sx-h2">Let’s work <em>together</em>.</h2>
                                    <p class="sx-sub">The quickest way to reach {{ \Illuminate\Support\Str::before($p['name'], ' ') }}.</p>
                                    <ul class="sx-c-list">
                                        @foreach ($contactRows as $r)
                                            <li>
                                                <span class="sx-ic"><x-dynamic-component :component="'lucide-'.$r['icon']" class="sx-i" /></span>
                                                @if ($r['href'])
                                                    <a href="{{ $r['href'] }}" @if (! empty($r['track'])) data-track="{{ $r['track'] }}" @endif @if (! empty($r['external'])) target="_blank" rel="noopener" @endif>{{ $r['value'] }}</a>
                                                @else
                                                    <span>{{ $r['value'] }}</span>
                                                @endif
                                                @if (! empty($r['note']))<small>· {{ $r['note'] }}</small>@endif
                                                @if (! empty($r['copy']))<button class="sx-copy" type="button" data-copy="{{ $r['copy'] }}">Copy</button>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($socialIcons)
                                        <div class="sx-socials">
                                            @foreach ($p['social'] as $s)
                                                <a href="{{ $s['url'] }}" target="_blank" rel="noopener me" data-track="social" data-target="{{ $s['key'] }}" aria-label="{{ $s['label'] }}" title="{{ $s['label'] }}{{ $s['count_label'] ? ' · '.$s['count_label'] : '' }}"><x-dynamic-component :component="'lucide-'.(IconLibrary::SOCIAL[$s['key']] ?? 'link')" class="sx-i" /></a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @if ($form)
                                    {{-- Opens the visitor's email app; nothing is sent to this server. --}}
                                    <form class="sx-form" id="sx-cform" novalidate data-to="{{ $p['email'] }}">
                                        <h3>Send a message</h3>
                                        <div class="sx-field"><input id="sx-fname" name="name" placeholder=" " required autocomplete="name"><label for="sx-fname">Your name</label></div>
                                        <div class="sx-field"><input id="sx-femail" name="email" type="email" placeholder=" " required autocomplete="email"><label for="sx-femail">Your email</label></div>
                                        <div class="sx-field"><textarea id="sx-fmsg" name="message" rows="4" placeholder=" " required></textarea><label for="sx-fmsg">Message</label></div>
                                        <button class="sx-btn sx-navy" type="submit" data-track="email">Send message <x-lucide-arrow-right class="sx-i sx-arrow" /></button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif
                @break
        @endswitch
    @endforeach

    {{-- Save and share --}}
    <section class="sx-wrap sx-share" aria-label="Save and share">
        @if ($p['show_save_contact'] && $on('actions'))
            <a href="{{ $urls['vcard'] }}"><x-lucide-user-plus class="sx-i" /> Save contact</a>
        @endif
        <button type="button" @click="navigator.share ? navigator.share({ title: @js($p['name']), url: (@js($urls['public']) || location.href) }).catch(() => {}) : navigator.clipboard.writeText((@js($urls['public']) || location.href)).then(() => { shared = true; setTimeout(() => shared = false, 2000) })">
            <x-lucide-share-2 class="sx-i" /> <span x-text="shared ? 'Link copied' : 'Share'">Share</span>
        </button>
        @if ($qrSvg)
            <button type="button" @click="qr = true"><x-lucide-qr-code class="sx-i" /> QR code</button>
        @endif
    </section>
</main>

<footer class="sx-foot">
    <div class="sx-wrap">
        <span>© <span id="sx-yr">{{ now()->year }}</span> {{ $p['name'] }} ·
            @if ($export) Made with {{ config('app.name') }} @else <a href="{{ route('home') }}">Made with {{ config('app.name') }}</a> @endif
        </span>
        <a href="#top" class="sx-totop" aria-label="Back to top"><x-lucide-arrow-up class="sx-i" style="width: 18px; height: 18px" /></a>
    </div>
</footer>
<div class="sx-toast" id="sx-toast" role="status" aria-live="polite"></div>

@if ($qrSvg)
    <div x-cloak x-show="qr" x-transition.opacity class="sx-modal" @click.self="qr = false" @keydown.escape.window="qr = false" role="dialog" aria-modal="true" aria-label="QR code">
        <div class="sx-modal-card">
            {!! $qrSvg !!}
            <p style="margin: 12px 0 0; font-weight: 700">{{ $p['name'] }}</p>
            <p style="margin: 2px 0 0; font-size: 13px; color: #6b7280">Scan with a phone camera to open this page</p>
            <button type="button" class="sx-btn sx-navy" style="width: 100%; margin-top: 18px" @click="qr = false">Close</button>
        </div>
    </div>
@endif
<div x-cloak x-show="photo" x-transition.opacity class="sx-lightbox" @click="photo = null" @keydown.escape.window="photo = null" role="dialog" aria-modal="true">
    <img :src="photo?.url" alt="">
    <p x-text="photo?.caption"></p>
</div>

<script>
(function () {
    var d = document.documentElement, reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (s) { return document.querySelector(s); }, $$ = function (s) { return [].slice.call(document.querySelectorAll(s)); };
    requestAnimationFrame(function () { setTimeout(function () { d.classList.add('sx-loaded'); }, 80); });
    var toastT;
    function toast(m) { var t = $('#sx-toast'); t.textContent = m; t.classList.add('sx-show'); clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove('sx-show'); }, 2400); }
    var nav = $('#sx-nav'), prog = $('.sx-progress'), ranges = $$('.sx-range[data-speed]'), band = $('.sx-band'), portrait = $('#sx-portrait'), ticking = false;
    function onScroll() {
        var y = scrollY, h = d.scrollHeight - innerHeight;
        nav.classList.toggle('sx-scrolled', y > 20);
        prog.style.transform = 'scaleX(' + (h > 0 ? y / h : 0) + ')';
        if (!reduce) {
            if (band) { var br = band.getBoundingClientRect(); if (br.bottom > 0 && br.top < innerHeight) ranges.forEach(function (r) { r.style.transform = 'translateY(' + (br.top * parseFloat(r.dataset.speed)) + 'px)'; }); }
            if (portrait && y < innerHeight) portrait.style.transform = 'translateY(' + (y * 0.06) + 'px)';
        }
        ticking = false;
    }
    addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(onScroll); ticking = true; } }, { passive: true });
    addEventListener('resize', onScroll); onScroll();
    var mb = $('#sx-menu');
    if (mb) {
        mb.addEventListener('click', function () { var o = nav.classList.toggle('sx-open'); mb.setAttribute('aria-expanded', o); mb.setAttribute('aria-label', o ? 'Close menu' : 'Open menu'); });
        $$('#sx-links a').forEach(function (a) { a.addEventListener('click', function () { nav.classList.remove('sx-open'); mb.setAttribute('aria-expanded', 'false'); }); });
    }
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('sx-in'); io.unobserve(e.target); } }); }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
        $$('.sx-rv').forEach(function (el) { io.observe(el); });
        var links = $$('#sx-links a');
        var so = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) links.forEach(function (a) { a.classList.toggle('sx-active', a.getAttribute('href') === '#' + e.target.id); }); }); }, { rootMargin: '-45% 0px -50% 0px' });
        $$('main section[id]').forEach(function (s) { so.observe(s); });
        var co = new IntersectionObserver(function (es) { es.forEach(function (e) {
            if (!e.isIntersecting) return; co.unobserve(e.target);
            var el = e.target, end = parseFloat(el.dataset.count), pre = el.dataset.pre || '', suf = el.dataset.suf || '', dec = +el.dataset.dec || 0;
            if (isNaN(end) || reduce) return;
            var fmt = function (v) { return pre + v.toLocaleString(undefined, { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suf; };
            var t0 = null; (function step(ts) { t0 = t0 || ts; var k = Math.min(1, (ts - t0) / 1800); el.textContent = fmt(end * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); else el.textContent = fmt(end); })(performance.now());
        }); }, { threshold: .6 });
        $$('[data-count]').forEach(function (el) { co.observe(el); });
    } else { $$('.sx-rv').forEach(function (el) { el.classList.add('sx-in'); }); }
    if (!reduce && matchMedia('(hover: hover)').matches) {
        $$('.sx-tilt').forEach(function (c) {
            c.addEventListener('mousemove', function (e) { c.style.transition = 'transform .15s ease-out, box-shadow .5s'; var r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height; c.style.transform = 'rotateX(' + ((.5 - y) * 8) + 'deg) rotateY(' + ((x - .5) * 10) + 'deg) translateY(-6px)'; c.style.setProperty('--mx', x * 100 + '%'); c.style.setProperty('--my', y * 100 + '%'); });
            c.addEventListener('mouseleave', function () { c.style.transition = 'transform .6s cubic-bezier(.22,1,.36,1), box-shadow .5s'; c.style.transform = ''; });
        });
        $$('.sx-magnetic').forEach(function (b) {
            b.addEventListener('mousemove', function (e) { var r = b.getBoundingClientRect(); b.style.transform = 'translate(' + ((e.clientX - r.left - r.width / 2) * .18) + 'px,' + ((e.clientY - r.top - r.height / 2) * .28) + 'px)'; });
            b.addEventListener('mouseleave', function () { b.style.transform = ''; });
        });
    }
    $$('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { var v = b.dataset.copy; (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { toast('Email copied to clipboard'); }, function () { toast(v); }); }); });
    var f = $('#sx-cform');
    if (f) f.addEventListener('submit', function (e) {
        e.preventDefault();
        var n = f.elements.name.value.trim(), m = f.elements.email.value.trim(), msg = f.elements.message.value.trim();
        if (!n || !m || !msg || !/^\S+@\S+\.\S+$/.test(m)) { toast('Please add your name, a valid email and a message'); return; }
        location.href = 'mailto:' + f.dataset.to + '?subject=' + encodeURIComponent('Message from ' + n) + '&body=' + encodeURIComponent(msg + '\n\n— ' + n + ' (' + m + ')');
        toast('Opening your email app…');
    });
})();
</script>
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
