<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section {{ $altClass }}">
    <div class="lp-wrap">
        @include('landing-pages.sections._head')
        @if ($d['items'])
            <div class="lp-faq">
                @foreach ($d['items'] as $item)
                    <details class="lp-reveal" @if ($loop->first) open @endif>
                        <summary>{{ $item['question'] }}<x-lucide-plus class="lp-i" /></summary>
                        <div class="lp-answer">{{ $item['answer'] }}</div>
                    </details>
                @endforeach
            </div>
        @else
            @include('landing-pages.sections._empty', ['message' => 'Add questions in the editor.'])
        @endif
    </div>
</section>
