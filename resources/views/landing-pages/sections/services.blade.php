<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section {{ $altClass }}">
    <div class="lp-wrap">
        @include('landing-pages.sections._head', ['intro' => $d['intro']])
        @if ($d['items'])
            <div class="lp-grid" style="--cols: {{ $d['columns'] }}">
                @foreach ($d['items'] as $item)
                    <div class="lp-card lp-reveal">
                        <div class="lp-tile"><x-dynamic-component :component="'lucide-'.$item['icon']" class="lp-i" /></div>
                        @if (filled($item['title']))<h3>{{ $item['title'] }}</h3>@endif
                        @if (filled($item['text']))<p>{{ $item['text'] }}</p>@endif
                        @if (filled($item['price']))<div class="lp-price">{{ $item['price'] }}</div>@endif
                    </div>
                @endforeach
            </div>
        @else
            @include('landing-pages.sections._empty', ['message' => 'Add services in the editor.'])
        @endif
    </div>
</section>
