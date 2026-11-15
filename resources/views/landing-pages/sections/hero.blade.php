@php
    $buttons = collect([
        filled($d['button_label']) ? ['label' => $d['button_label'], 'url' => $d['button_url'] ?: '#contact', 'ghost' => false] : null,
        filled($d['secondary_label']) ? ['label' => $d['secondary_label'], 'url' => $d['secondary_url'] ?: '#', 'ghost' => true] : null,
    ])->filter();
    $external = fn ($url) => str_starts_with($url, 'http');
@endphp
@if ($d['variant'] === 'cover')
    <section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section lp-hero lp-cover" @if ($d['image']) style="background-image: url('{{ $d['image'] }}')" @endif>
        <div class="lp-wrap" style="position:relative">
            <div class="lp-inner lp-reveal">
                @if (filled($d['eyebrow']))<div class="lp-eyebrow">{{ $d['eyebrow'] }}</div>@endif
                <h1>{{ $d['heading'] ?: $p['company'] }}</h1>
                @if (filled($d['subheading']))<p class="lp-sub">{{ $d['subheading'] }}</p>@endif
                @if ($buttons->isNotEmpty())
                    <div class="lp-btns">
                        @foreach ($buttons as $b)
                            <a href="{{ $b['url'] }}" class="lp-btn {{ $b['ghost'] ? 'lp-ghost' : '' }}" @if ($external($b['url'])) target="_blank" rel="noopener" @endif>{{ $b['label'] }}@unless ($b['ghost'])<x-lucide-arrow-right class="lp-i" />@endunless</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@elseif ($d['variant'] === 'split')
    <section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section lp-hero">
        <div class="lp-hero-glow"></div>
        <div class="lp-wrap lp-split">
            <div class="lp-reveal">
                @if (filled($d['eyebrow']))<div class="lp-eyebrow">{{ $d['eyebrow'] }}</div>@endif
                <h1>{{ $d['heading'] ?: $p['company'] }}</h1>
                @if (filled($d['subheading']))<p class="lp-sub">{{ $d['subheading'] }}</p>@endif
                @if ($buttons->isNotEmpty())
                    <div class="lp-btns">
                        @foreach ($buttons as $b)
                            <a href="{{ $b['url'] }}" class="lp-btn {{ $b['ghost'] ? 'lp-ghost' : '' }}" @if ($external($b['url'])) target="_blank" rel="noopener" @endif>{{ $b['label'] }}@unless ($b['ghost'])<x-lucide-arrow-right class="lp-i" />@endunless</a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="lp-split-media lp-reveal">
                @if ($d['image'])<img src="{{ $d['image'] }}" alt="">@else<div class="lp-art" role="presentation"></div>@endif
            </div>
        </div>
    </section>
@else
    <section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section lp-hero lp-hero-center">
        <div class="lp-hero-glow"></div>
        <div class="lp-wrap">
            <div class="lp-reveal">
                @if (filled($d['eyebrow']))<div class="lp-eyebrow">{{ $d['eyebrow'] }}</div>@endif
                <h1 style="max-width: 900px; margin: 0 auto">{{ $d['heading'] ?: $p['company'] }}</h1>
                @if (filled($d['subheading']))<p class="lp-sub">{{ $d['subheading'] }}</p>@endif
                @if ($buttons->isNotEmpty())
                    <div class="lp-btns">
                        @foreach ($buttons as $b)
                            <a href="{{ $b['url'] }}" class="lp-btn {{ $b['ghost'] ? 'lp-ghost' : '' }}" @if ($external($b['url'])) target="_blank" rel="noopener" @endif>{{ $b['label'] }}@unless ($b['ghost'])<x-lucide-arrow-right class="lp-i" />@endunless</a>
                        @endforeach
                    </div>
                @endif
            </div>
            @if ($d['image'])<img src="{{ $d['image'] }}" alt="" class="lp-hero-img lp-reveal">@endif
        </div>
    </section>
@endif
