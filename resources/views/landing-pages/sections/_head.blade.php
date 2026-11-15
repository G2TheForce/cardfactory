@if (filled($d['eyebrow'] ?? null) || filled($d['heading'] ?? null) || filled($intro ?? null))
    <div class="lp-head lp-reveal {{ ($left ?? false) ? 'lp-left' : '' }}">
        @if (filled($d['eyebrow'] ?? null))<div class="lp-eyebrow">{{ $d['eyebrow'] }}</div>@endif
        @if (filled($d['heading'] ?? null))<h2 class="lp-h2">{{ $d['heading'] }}</h2>@endif
        @if (filled($intro ?? null))<p class="lp-lead">{{ $intro }}</p>@endif
    </div>
@endif
