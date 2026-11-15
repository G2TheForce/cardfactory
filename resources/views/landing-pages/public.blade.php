@php
    use App\Support\Scene\IconLibrary;

    /*
     * A landing page built in the landing page designer. Self-contained styles
     * (classes prefixed "lp-" so they never collide with Tailwind) driven by
     * the page's colour tokens. Used for /p/{slug}, previews and the static
     * site export ($export holds the copied CSS/JS paths, or null).
     */
    $export ??= null;
    // The static export renders outside a request, without the shared error bag.
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $t = $p['style'];
    $rgba = function (string $hex, float $a): string {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return "rgba($r,$g,$b,$a)";
    };
    $sections = collect($p['sections'])->filter(fn ($s) => $preview || ! $s['empty'])->values();
    $hasErrors = $errors->getBag('lead')->any();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $t['dark'] ? 'lp-dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $p['title'] }}</title>
    @if ($p['description'])<meta name="description" content="{{ $p['description'] }}">@endif
    @if ($preview || ! $p['allow_indexing'])
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="{{ $t['bg'] }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $p['title'] }}">
    @if ($p['description'])<meta property="og:description" content="{{ $p['description'] }}">@endif
    @if ($urls['public'])<meta property="og:url" content="{{ $urls['public'] }}"><link rel="canonical" href="{{ $urls['public'] }}">@endif
    @if ($p['share_image'] && ! $export)<meta property="og:image" content="{{ $p['share_image'] }}">@endif
    <script>document.documentElement.classList.add('lp-js')</script>
    @if ($export)
        <link rel="icon" href="favicon.svg" type="image/svg+xml">
        @if ($export['css'])<link rel="stylesheet" href="{{ $export['css'] }}">@endif
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css'])
    @endif
    <style>
        :root {
            --bg: {{ $t['bg'] }}; --surface: {{ $t['surface'] }}; --alt: {{ $t['alt'] }}; --text: {{ $t['text'] }}; --muted: {{ $t['muted'] }}; --line: {{ $t['border'] }};
            --primary: {{ $t['primary'] }}; --on-primary: {{ $t['on_primary'] }}; --accent: {{ $t['accent'] }}; --on-accent: {{ $t['on_accent'] }};
            --accent-soft: {{ $rgba($t['accent'], $t['dark'] ? .16 : .1) }}; --accent-glow: {{ $rgba($t['accent'], .35) }};
            --hfont: {!! $t['heading_font'] !!}; --bfont: {!! $t['body_font'] !!}; --radius: {{ $t['radius'] }};
            --ease: cubic-bezier(.22, 1, .36, 1); --wrap: 1160px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        html, body { overflow-x: clip; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: var(--bfont); font-size: 17px; line-height: 1.65; -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        h1, h2, h3 { font-family: var(--hfont); line-height: 1.12; letter-spacing: -.02em; margin: 0; font-weight: 700; }
        p { margin: 0; }
        ::selection { background: var(--accent); color: var(--on-accent); }
        :focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; border-radius: 6px; }
        [id] { scroll-margin-top: 84px; }
        .lp-i { width: 20px; height: 20px; flex: none; }
        .lp-wrap { max-width: var(--wrap); margin: 0 auto; padding: 0 24px; }
        .lp-skip { position: absolute; left: -999px; top: 12px; background: var(--primary); color: var(--on-primary); padding: 10px 16px; border-radius: 8px; z-index: 200; }
        .lp-skip:focus { left: 12px; }
        .lp-preview { position: sticky; top: 0; z-index: 140; background: #111827; color: #fff; text-align: center; font: 600 12px/1.4 var(--bfont); padding: 8px 16px; }

        /* Navigation */
        .lp-nav { position: sticky; top: 0; z-index: 100; background: {{ $rgba($t['bg'], .82) }}; -webkit-backdrop-filter: saturate(1.4) blur(14px); backdrop-filter: saturate(1.4) blur(14px); border-bottom: 1px solid transparent; transition: border-color .3s, box-shadow .3s; }
        .lp-preview + .lp-nav { top: 33px; }
        .lp-nav.lp-scrolled { border-color: var(--line); box-shadow: 0 8px 30px -18px rgba(0,0,0,.25); }
        .lp-nav .lp-wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; height: 68px; }
        .lp-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; font-family: var(--hfont); font-weight: 700; font-size: 19px; letter-spacing: -.01em; min-width: 0; }
        .lp-brand img { height: 38px; width: auto; max-width: 160px; object-fit: contain; }
        .lp-brand span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lp-links { display: flex; align-items: center; gap: 2px; list-style: none; margin: 0; padding: 0; }
        .lp-links a { display: block; padding: 8px 13px; border-radius: 999px; text-decoration: none; font-weight: 600; font-size: 15px; color: var(--muted); transition: color .2s, background .2s; }
        .lp-links a:hover { color: var(--text); background: var(--accent-soft); }
        .lp-menu { display: none; width: 44px; height: 44px; border-radius: 12px; border: 1px solid var(--line); background: var(--surface); color: var(--text); cursor: pointer; align-items: center; justify-content: center; }
        @media (max-width: 860px) {
            .lp-links, .lp-nav .lp-nav-cta { display: none; }
            .lp-menu { display: inline-flex; }
            .lp-nav.lp-open .lp-links { display: flex; position: absolute; top: 68px; left: 0; right: 0; flex-direction: column; align-items: stretch; padding: 12px 16px 18px; background: var(--bg); border-bottom: 1px solid var(--line); }
            .lp-nav.lp-open .lp-links a { padding: 12px 14px; font-size: 16px; }
        }

        /* Buttons */
        .lp-btns { display: flex; flex-wrap: wrap; gap: 12px; }
        .lp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 50px; padding: 0 26px; border-radius: var(--radius); border: 0; cursor: pointer; font: 700 15px/1 var(--bfont); text-decoration: none; color: var(--on-accent); background: var(--accent); box-shadow: 0 10px 24px -10px var(--accent-glow); transition: transform .3s var(--ease), box-shadow .3s var(--ease), filter .2s; }
        .lp-btn:hover { transform: translateY(-2px); box-shadow: 0 16px 30px -12px var(--accent-glow); filter: brightness(1.05); }
        .lp-btn .lp-i { width: 18px; height: 18px; }
        .lp-btn.lp-ghost { background: transparent; color: inherit; box-shadow: inset 0 0 0 1.5px currentColor; }
        .lp-btn.lp-ghost:hover { background: var(--accent-soft); }
        .lp-btn.lp-sm { min-height: 42px; padding: 0 18px; font-size: 14px; }
        .lp-btn.lp-block { width: 100%; }

        /* Sections */
        .lp-section { padding: 96px 0; position: relative; }
        .lp-section.lp-alt { background: var(--alt); }
        .lp-head { max-width: 680px; margin: 0 auto 52px; text-align: center; }
        .lp-head.lp-left { margin-left: 0; text-align: left; }
        .lp-eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--accent); margin-bottom: 14px; }
        .lp-eyebrow::before { content: ""; width: 22px; height: 2px; background: currentColor; border-radius: 2px; }
        .lp-h2 { font-size: clamp(30px, 4.2vw, 46px); }
        .lp-lead { font-size: 18px; color: var(--muted); margin-top: 16px; }
        .lp-prose p + p { margin-top: 1em; }
        .lp-prose { color: var(--muted); font-size: 17px; }
        .lp-placeholder { border: 2px dashed var(--line); border-radius: 18px; padding: 36px; text-align: center; color: var(--muted); font-size: 15px; }
        @media (max-width: 720px) { .lp-section { padding: 68px 0; } .lp-head { margin-bottom: 36px; } }

        /* Hero */
        .lp-hero { padding: 104px 0 96px; overflow: hidden; }
        .lp-hero h1 { font-size: clamp(38px, 6.4vw, 72px); }
        .lp-hero .lp-sub { font-size: clamp(17px, 1.7vw, 20px); color: var(--muted); margin-top: 22px; max-width: 620px; }
        .lp-hero .lp-btns { margin-top: 34px; }
        .lp-hero-center { text-align: center; }
        .lp-hero-center .lp-sub { margin-left: auto; margin-right: auto; }
        .lp-hero-center .lp-btns { justify-content: center; }
        .lp-hero-center .lp-hero-img { margin-top: 64px; border-radius: 26px; box-shadow: 0 40px 80px -40px rgba(0,0,0,.45); aspect-ratio: 16 / 8; object-fit: cover; width: 100%; }
        .lp-hero-glow { position: absolute; inset: -20% -10% auto; height: 620px; background: radial-gradient(closest-side, var(--accent-soft), transparent 70%); pointer-events: none; z-index: 0; }
        .lp-hero .lp-wrap { position: relative; z-index: 1; }
        .lp-split { display: grid; grid-template-columns: 1.05fr .95fr; gap: 64px; align-items: center; }
        .lp-split-media { position: relative; }
        .lp-split-media img, .lp-split-media .lp-art { border-radius: 28px; aspect-ratio: 4 / 5; object-fit: cover; width: 100%; box-shadow: 0 40px 80px -40px rgba(0,0,0,.45); }
        .lp-art { background: radial-gradient(at 20% 15%, var(--accent) 0, transparent 55%), radial-gradient(at 85% 80%, var(--primary) 0, transparent 55%), var(--alt); }
        .lp-split-media::after { content: ""; position: absolute; inset: auto -18px -18px auto; width: 46%; height: 46%; border-radius: 28px; border: 2px solid var(--accent); z-index: -1; }
        .lp-cover { min-height: min(88vh, 760px); display: flex; align-items: center; color: #fff; background: linear-gradient(135deg, var(--primary), {{ $rgba($t['accent'], .9) }}); background-size: cover; background-position: center; }
        .lp-cover::before { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(0,0,0,.7), rgba(0,0,0,.25)); }
        .lp-cover .lp-sub { color: rgba(255,255,255,.86); }
        .lp-cover .lp-eyebrow { color: #fff; }
        .lp-cover .lp-wrap { width: 100%; }
        .lp-cover .lp-inner { max-width: 720px; }
        @media (max-width: 860px) { .lp-split { grid-template-columns: 1fr; gap: 44px; } .lp-hero { padding: 72px 0; } }

        /* About */
        .lp-about { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center; }
        .lp-about.lp-flip > :first-child { order: 2; }
        .lp-about img { border-radius: 24px; width: 100%; aspect-ratio: 5 / 4; object-fit: cover; }
        .lp-about .lp-prose { margin-top: 20px; }
        .lp-about.lp-solo { grid-template-columns: 1fr; max-width: 760px; margin: 0 auto; text-align: center; }
        @media (max-width: 860px) { .lp-about { grid-template-columns: 1fr; gap: 36px; } .lp-about.lp-flip > :first-child { order: 0; } }

        /* Cards */
        .lp-grid { display: grid; gap: 22px; grid-template-columns: repeat(var(--cols, 3), minmax(0, 1fr)); }
        @media (max-width: 960px) { .lp-grid { --cols: 2 !important; } }
        @media (max-width: 600px) { .lp-grid { --cols: 1 !important; } }
        .lp-card { background: var(--surface); border: 1px solid var(--line); border-radius: 22px; padding: 30px; transition: transform .35s var(--ease), box-shadow .35s var(--ease), border-color .3s; }
        .lp-card:hover { transform: translateY(-4px); box-shadow: 0 24px 50px -30px rgba(0,0,0,.35); border-color: {{ $rgba($t['accent'], .45) }}; }
        .lp-tile { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); margin-bottom: 20px; }
        .lp-tile .lp-i { width: 24px; height: 24px; }
        .lp-card h3 { font-size: 21px; }
        .lp-card p { color: var(--muted); margin-top: 10px; font-size: 16px; }
        .lp-price { margin-top: 18px; font-weight: 800; color: var(--accent); font-size: 16px; }

        /* Gallery */
        .lp-gallery figure { margin: 0; position: relative; overflow: hidden; border-radius: 20px; background: var(--alt); }
        .lp-gallery img { width: 100%; aspect-ratio: 1; object-fit: cover; transition: transform .8s var(--ease); }
        .lp-gallery figure:hover img { transform: scale(1.06); }
        .lp-gallery figcaption { position: absolute; inset: auto 0 0; padding: 40px 18px 14px; color: #fff; font-size: 14px; font-weight: 600; background: linear-gradient(transparent, rgba(0,0,0,.65)); }

        /* Stats */
        .lp-stats { background: var(--primary); color: var(--on-primary); padding: 72px 0; }
        .lp-stats .lp-h2 { text-align: center; margin-bottom: 40px; font-size: clamp(26px, 3vw, 36px); }
        .lp-stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 28px; text-align: center; }
        .lp-stat-num { font-family: var(--hfont); font-size: clamp(40px, 5vw, 58px); font-weight: 800; letter-spacing: -.03em; line-height: 1; }
        .lp-stat-label { margin-top: 10px; opacity: .78; font-weight: 600; font-size: 15px; }

        /* Testimonials */
        .lp-quote { display: flex; flex-direction: column; }
        .lp-stars { display: flex; gap: 3px; color: #f5b301; margin-bottom: 16px; }
        .lp-stars .lp-i { width: 18px; height: 18px; fill: currentColor; }
        .lp-quote blockquote { margin: 0; font-size: 18px; line-height: 1.6; color: var(--text); flex: 1; }
        .lp-person { display: flex; align-items: center; gap: 12px; margin-top: 24px; }
        .lp-avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--accent); color: var(--on-accent); display: grid; place-items: center; font-weight: 800; font-size: 15px; flex: none; }
        .lp-person strong { display: block; font-size: 15px; }
        .lp-person span { color: var(--muted); font-size: 14px; }

        /* Pricing */
        .lp-plans { display: grid; gap: 22px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); align-items: stretch; max-width: 1040px; margin: 0 auto; }
        .lp-plan { display: flex; flex-direction: column; position: relative; }
        .lp-plan.lp-featured { border: 2px solid var(--accent); box-shadow: 0 30px 60px -36px var(--accent-glow); }
        .lp-badge { position: absolute; top: -13px; left: 50%; transform: translateX(-50%); background: var(--accent); color: var(--on-accent); font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: 5px 12px; border-radius: 999px; white-space: nowrap; }
        .lp-plan-price { font-family: var(--hfont); font-size: 44px; font-weight: 800; letter-spacing: -.03em; margin-top: 14px; }
        .lp-plan-price small { font-family: var(--bfont); font-size: 15px; font-weight: 600; color: var(--muted); letter-spacing: 0; }
        .lp-plan ul { list-style: none; padding: 0; margin: 22px 0 28px; display: grid; gap: 10px; flex: 1; }
        .lp-plan li { display: flex; gap: 10px; color: var(--muted); font-size: 15px; }
        .lp-plan li .lp-i { color: var(--accent); width: 18px; height: 18px; margin-top: 3px; }

        /* FAQ */
        .lp-faq { max-width: 800px; margin: 0 auto; display: grid; gap: 12px; }
        .lp-faq details { background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 0 24px; transition: border-color .3s; }
        .lp-faq details[open] { border-color: {{ $rgba($t['accent'], .5) }}; }
        .lp-faq summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 20px 0; font-weight: 700; font-size: 17px; }
        .lp-faq summary::-webkit-details-marker { display: none; }
        .lp-faq summary .lp-i { transition: transform .3s var(--ease); color: var(--accent); }
        .lp-faq details[open] summary .lp-i { transform: rotate(45deg); }
        .lp-faq .lp-answer { padding: 0 0 22px; color: var(--muted); white-space: pre-line; }

        /* Call to action */
        .lp-cta-box { border-radius: 32px; padding: clamp(44px, 7vw, 80px) clamp(24px, 6vw, 72px); text-align: center; color: var(--on-primary); background: radial-gradient(at 15% 0%, {{ $rgba($t['accent'], .55) }}, transparent 55%), radial-gradient(at 100% 100%, {{ $rgba($t['accent'], .35) }}, transparent 50%), var(--primary); position: relative; overflow: hidden; }
        .lp-cta-box p { opacity: .85; font-size: 18px; max-width: 600px; margin: 16px auto 0; }
        .lp-cta-box .lp-btns { justify-content: center; margin-top: 32px; }

        /* Contact */
        .lp-contact { display: grid; grid-template-columns: .9fr 1.1fr; gap: 48px; align-items: start; }
        .lp-contact.lp-solo { grid-template-columns: 1fr; max-width: 720px; margin: 0 auto; }
        .lp-info { display: grid; gap: 14px; margin-top: 28px; }
        .lp-info a, .lp-info div { display: flex; gap: 14px; align-items: flex-start; text-decoration: none; }
        .lp-info .lp-tile { width: 44px; height: 44px; border-radius: 13px; margin: 0; }
        .lp-info .lp-tile .lp-i { width: 20px; height: 20px; }
        .lp-info strong { display: block; font-size: 13px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 700; }
        .lp-info span { white-space: pre-line; font-weight: 600; }
        .lp-form { display: grid; gap: 14px; }
        .lp-form .lp-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 600px) { .lp-form .lp-row { grid-template-columns: 1fr; } }
        .lp-form label { display: block; font-size: 14px; font-weight: 700; margin-bottom: 6px; }
        .lp-form input, .lp-form textarea { width: 100%; font: inherit; font-size: 16px; color: var(--text); background: var(--bg); border: 1.5px solid var(--line); border-radius: 12px; padding: 12px 14px; transition: border-color .2s, box-shadow .2s; }
        .lp-form input:focus, .lp-form textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
        .lp-form textarea { min-height: 130px; resize: vertical; }
        .lp-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .lp-error { color: #dc2626; font-size: 13px; margin-top: 4px; }
        .lp-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 14px; padding: 14px 16px; font-weight: 600; }
        .lp-note { font-size: 13px; color: var(--muted); }
        .lp-map { margin-top: 28px; border-radius: 20px; overflow: hidden; border: 1px solid var(--line); }
        .lp-map iframe { display: block; width: 100%; height: 280px; border: 0; }
        @media (max-width: 860px) { .lp-contact { grid-template-columns: 1fr; } }

        /* Footer */
        .lp-footer { border-top: 1px solid var(--line); padding: 44px 0; color: var(--muted); font-size: 14px; }
        .lp-footer .lp-wrap { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px; }
        .lp-social { display: flex; gap: 8px; }
        .lp-social a { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; border: 1px solid var(--line); color: var(--text); transition: background .2s, color .2s, transform .3s var(--ease); }
        .lp-social a:hover { background: var(--accent); color: var(--on-accent); transform: translateY(-3px); }
        .lp-social .lp-i { width: 18px; height: 18px; }
        .lp-made a { color: inherit; }

        /* Reveal on scroll */
        @if ($t['animations'])
        .lp-js .lp-reveal { opacity: 0; transform: translateY(26px); transition: opacity .9s var(--ease), transform .9s var(--ease); }
        .lp-js .lp-reveal.lp-in { opacity: 1; transform: none; }
        @endif
        @media (prefers-reduced-motion: reduce) { .lp-js .lp-reveal { opacity: 1; transform: none; transition: none; } html { scroll-behavior: auto; } }
    </style>
</head>
<body>
<a href="#main" class="lp-skip">Skip to content</a>
@if ($preview)
    <div class="lp-preview">Preview{{ $page->is_published ? '' : ' · draft, not published yet' }}. Visitors you send here are not counted.</div>
@endif

@if ($p['show_nav'])
    <header class="lp-nav" data-lp-nav>
        <div class="lp-wrap">
            <a href="#top" class="lp-brand">
                @if ($p['logo'])<img src="{{ $p['logo'] }}" alt="{{ $p['company'] }}">@endif
                @if (! $p['logo'])<span>{{ $p['company'] }}</span>@endif
            </a>
            @if ($p['nav'])
                <ul class="lp-links" id="lp-links">
                    @foreach ($p['nav'] as $link)
                        <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif
            <div style="display:flex;gap:10px;align-items:center">
                @if ($p['nav_cta'])<a href="{{ $p['nav_cta']['href'] }}" class="lp-btn lp-sm lp-nav-cta">{{ $p['nav_cta']['label'] }}</a>@endif
                @if ($p['nav'])
                    <button type="button" class="lp-menu" aria-controls="lp-links" aria-expanded="false" aria-label="Menu" data-lp-menu><x-lucide-menu class="lp-i" /></button>
                @endif
            </div>
        </div>
    </header>
@endif

<main id="main">
    <span id="top"></span>
    @php $alt = false; @endphp
    @foreach ($sections as $s)
        @php
            $d = $s['data'];
            $band = in_array($s['type'], ['hero', 'stats', 'cta'], true);
            if (! $band) { $alt = ! $alt; }
        @endphp
        @include('landing-pages.sections.'.$s['type'], ['s' => $s, 'd' => $d, 'altClass' => ! $band && ! $alt ? 'lp-alt' : ''])
    @endforeach
    @if ($sections->isEmpty())
        <section class="lp-section"><div class="lp-wrap"><div class="lp-placeholder">This page has no sections yet.</div></div></section>
    @endif
</main>

<footer class="lp-footer">
    <div class="lp-wrap">
        <div>
            <div style="font-family: var(--hfont); font-weight: 700; font-size: 17px; color: var(--text)">{{ $p['company'] }}</div>
            <div style="margin-top:4px">{{ $p['footer'] }}</div>
        </div>
        @if ($p['social'])
            <div class="lp-social">
                @foreach ($p['social'] as $social)
                    <a href="{{ $social['url'] }}" target="_blank" rel="noopener me" aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}"><x-dynamic-component :component="'lucide-'.(IconLibrary::SOCIAL[$social['key']] ?? 'link')" class="lp-i" /></a>
                @endforeach
            </div>
        @endif
        <div class="lp-made">@if ($export) Made with {{ config('app.name') }} @else <a href="{{ route('home') }}">Made with {{ config('app.name') }}</a> @endif</div>
    </div>
</footer>

<script>
(function () {
    var nav = document.querySelector('[data-lp-nav]');
    var menu = document.querySelector('[data-lp-menu]');
    if (nav) {
        var onScroll = function () { nav.classList.toggle('lp-scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
    }
    if (menu && nav) {
        menu.addEventListener('click', function () {
            var open = nav.classList.toggle('lp-open');
            menu.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        nav.querySelectorAll('.lp-links a').forEach(function (a) { a.addEventListener('click', function () { nav.classList.remove('lp-open'); menu.setAttribute('aria-expanded', 'false'); }); });
    }

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var count = function (el) {
        var m = (el.getAttribute('data-count') || '').match(/^([^\d]*)([\d][\d,]*(?:\.\d+)?)(.*)$/);
        if (!m || reduce) return;
        var target = parseFloat(m[2].replace(/,/g, '')), dec = (m[2].split('.')[1] || '').length, comma = m[2].indexOf(',') > -1, start = null;
        var fmt = function (n) { var s = n.toFixed(dec); return comma ? Number(s).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) : s; };
        var step = function (t) { start = start || t; var k = Math.min(1, (t - start) / 1400); el.textContent = m[1] + fmt(target * (1 - Math.pow(1 - k, 3))) + m[3]; if (k < 1) requestAnimationFrame(step); };
        requestAnimationFrame(step);
    };
    var items = document.querySelectorAll('.lp-reveal, [data-count]');
    if (!('IntersectionObserver' in window)) { items.forEach(function (el) { el.classList.add('lp-in'); }); return; }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            e.target.classList.add('lp-in');
            if (e.target.hasAttribute('data-count')) count(e.target);
            io.unobserve(e.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(function (el) { io.observe(el); });
})();
</script>
@if ($hasErrors || session('lead_sent'))
    <script>document.getElementById('contact') && document.getElementById('contact').scrollIntoView();</script>
@endif
</body>
</html>
