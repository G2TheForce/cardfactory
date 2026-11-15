@php $photos = collect($d['items'])->filter(fn ($i) => $i['image']); @endphp
<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section {{ $altClass }}">
    <div class="lp-wrap">
        @include('landing-pages.sections._head')
        @if ($photos->isNotEmpty())
            <div class="lp-grid lp-gallery" style="--cols: {{ $d['columns'] }}">
                @foreach ($photos as $item)
                    <figure class="lp-reveal">
                        <img src="{{ $item['image'] }}" alt="{{ $item['caption'] }}" loading="lazy">
                        @if (filled($item['caption']))<figcaption>{{ $item['caption'] }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>
        @else
            @include('landing-pages.sections._empty', ['message' => 'Upload photos in the editor. This section stays hidden until it has some.'])
        @endif
    </div>
</section>
