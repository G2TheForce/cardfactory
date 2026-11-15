@php
    $paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', (string) $d['body']) ?: [])));
@endphp
<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section {{ $altClass }}">
    <div class="lp-wrap">
        <div class="lp-about {{ $d['image'] ? '' : 'lp-solo' }} {{ $d['image'] && $d['image_side'] === 'left' ? 'lp-flip' : '' }}">
            <div class="lp-reveal">
                @if (filled($d['eyebrow']))<div class="lp-eyebrow">{{ $d['eyebrow'] }}</div>@endif
                @if (filled($d['heading']))<h2 class="lp-h2">{{ $d['heading'] }}</h2>@endif
                @if ($paras)
                    <div class="lp-prose">
                        @foreach ($paras as $para)<p>{!! nl2br(e($para)) !!}</p>@endforeach
                    </div>
                @endif
            </div>
            @if ($d['image'])<img src="{{ $d['image'] }}" alt="" class="lp-reveal">@endif
        </div>
    </div>
</section>
