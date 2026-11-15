<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section {{ $altClass }}">
    <div class="lp-wrap">
        @include('landing-pages.sections._head', ['intro' => $d['intro']])
        @if ($d['items'])
            <div class="lp-plans">
                @foreach ($d['items'] as $plan)
                    <div class="lp-card lp-plan lp-reveal {{ $plan['featured'] ? 'lp-featured' : '' }}">
                        @if ($plan['featured'])<span class="lp-badge">Most popular</span>@endif
                        <h3>{{ $plan['name'] }}</h3>
                        <div class="lp-plan-price">{{ $plan['price'] }} @if (filled($plan['period']))<small>{{ $plan['period'] }}</small>@endif</div>
                        <ul>
                            @foreach ($plan['feature_list'] as $feature)
                                <li><x-lucide-check class="lp-i" />{{ $feature }}</li>
                            @endforeach
                        </ul>
                        @if (filled($plan['button_label']))
                            <a href="{{ $plan['button_url'] ?: '#contact' }}" class="lp-btn lp-block {{ $plan['featured'] ? '' : 'lp-ghost' }}">{{ $plan['button_label'] }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            @include('landing-pages.sections._empty', ['message' => 'Add plans in the editor.'])
        @endif
    </div>
</section>
