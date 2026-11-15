@php
    $initials = fn (string $s) => mb_strtoupper(collect(preg_split('/\s+/', trim($s)) ?: [])->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '•';
@endphp
<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section {{ $altClass }}">
    <div class="lp-wrap">
        @include('landing-pages.sections._head')
        @if ($d['items'])
            <div class="lp-grid" style="--cols: {{ min(3, max(1, count($d['items']))) }}">
                @foreach ($d['items'] as $item)
                    <figure class="lp-card lp-quote lp-reveal" style="margin:0">
                        @if ((int) $item['rating'] > 0)
                            <div class="lp-stars" aria-label="{{ $item['rating'] }} out of 5 stars">
                                @for ($i = 0; $i < (int) $item['rating']; $i++)<x-lucide-star class="lp-i" />@endfor
                            </div>
                        @endif
                        <blockquote>“{{ $item['quote'] }}”</blockquote>
                        @if (filled($item['name']))
                            <figcaption class="lp-person">
                                <span class="lp-avatar" aria-hidden="true">{{ $initials($item['name']) }}</span>
                                <span><strong>{{ $item['name'] }}</strong>@if (filled($item['role']))<span>{{ $item['role'] }}</span>@endif</span>
                            </figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @else
            @include('landing-pages.sections._empty', ['message' => 'Add testimonials in the editor.'])
        @endif
    </div>
</section>
