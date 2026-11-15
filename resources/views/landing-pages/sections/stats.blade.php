<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-stats">
    <div class="lp-wrap">
        @if (filled($d['heading']))<h2 class="lp-h2 lp-reveal">{{ $d['heading'] }}</h2>@endif
        @if ($d['items'])
            <div class="lp-stat-row">
                @foreach ($d['items'] as $item)
                    <div class="lp-reveal">
                        <div class="lp-stat-num" data-count="{{ $item['value'] }}">{{ $item['value'] }}</div>
                        <div class="lp-stat-label">{{ $item['label'] }}</div>
                    </div>
                @endforeach
            </div>
        @else
            @include('landing-pages.sections._empty', ['message' => 'Add numbers in the editor.'])
        @endif
    </div>
</section>
