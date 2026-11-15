<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section" style="padding-top: 48px; padding-bottom: 48px">
    <div class="lp-wrap">
        <div class="lp-cta-box lp-reveal">
            @if (filled($d['heading']))<h2 class="lp-h2">{{ $d['heading'] }}</h2>@endif
            @if (filled($d['text']))<p>{{ $d['text'] }}</p>@endif
            @if (filled($d['button_label']))
                <div class="lp-btns"><a href="{{ $d['button_url'] ?: '#contact' }}" class="lp-btn" @if (str_starts_with($d['button_url'], 'http')) target="_blank" rel="noopener" @endif>{{ $d['button_label'] }}<x-lucide-arrow-right class="lp-i" /></a></div>
            @endif
        </div>
    </div>
</section>
