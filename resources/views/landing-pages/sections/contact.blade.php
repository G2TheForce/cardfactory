@php
    $rows = collect([
        filled($d['email']) ? ['icon' => 'mail', 'label' => 'Email', 'value' => $d['email'], 'href' => 'mailto:'.$d['email']] : null,
        filled($d['phone']) ? ['icon' => 'phone', 'label' => 'Phone', 'value' => $d['phone'], 'href' => $d['phone_href']] : null,
        $d['whatsapp_href'] ? ['icon' => 'message-circle', 'label' => 'WhatsApp', 'value' => $d['whatsapp'], 'href' => $d['whatsapp_href'], 'external' => true] : null,
        filled($d['address']) ? ['icon' => 'map-pin', 'label' => 'Address', 'value' => $d['address']] : null,
        filled($d['hours']) ? ['icon' => 'clock', 'label' => 'Hours', 'value' => $d['hours']] : null,
    ])->filter();
    $bag = $errors->getBag('lead');
    // A static export can't receive form posts: offer an email button instead.
    $form = $d['show_form'] && ! $export;
    $mailto = $export && $d['show_form'] && filled($d['email']);
@endphp
<section id="{{ $s['anchor'] }}" data-section="{{ $s['id'] }}" class="lp-section {{ $altClass }}">
    <div class="lp-wrap">
        <div class="lp-contact {{ $form || $mailto ? '' : 'lp-solo' }}">
            <div class="lp-reveal">
                @if (filled($d['eyebrow']))<div class="lp-eyebrow">{{ $d['eyebrow'] }}</div>@endif
                @if (filled($d['heading']))<h2 class="lp-h2">{{ $d['heading'] }}</h2>@endif
                @if (filled($d['text']))<p class="lp-lead">{{ $d['text'] }}</p>@endif
                @if ($rows->isNotEmpty())
                    <div class="lp-info">
                        @foreach ($rows as $row)
                            @if (isset($row['href']))
                                <a href="{{ $row['href'] }}" @if ($row['external'] ?? false) target="_blank" rel="noopener" @endif>
                            @else
                                <div>
                            @endif
                                <span class="lp-tile"><x-dynamic-component :component="'lucide-'.$row['icon']" class="lp-i" /></span>
                                <span><strong>{{ $row['label'] }}</strong><span>{{ $row['value'] }}</span></span>
                            @if (isset($row['href']))</a>@else</div>@endif
                        @endforeach
                    </div>
                @endif
                @if ($d['map_embed'])
                    <div class="lp-map"><iframe src="{{ $d['map_embed'] }}" title="Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
                @endif
            </div>

            @if ($form)
                <div class="lp-card lp-reveal" style="padding: 32px">
                    @if (session('lead_sent'))
                        <div class="lp-success" role="status">{{ session('lead_sent') }}</div>
                    @else
                        <form method="POST" action="{{ $urls['contact'] }}" class="lp-form" @if ($preview) onsubmit="return false" @endif>
                            @csrf
                            <div>
                                <label for="lp-name">Your name</label>
                                <input id="lp-name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                                @if ($bag->has('name'))<p class="lp-error">{{ $bag->first('name') }}</p>@endif
                            </div>
                            <div class="lp-row">
                                <div>
                                    <label for="lp-email">Email</label>
                                    <input id="lp-email" type="email" name="email" value="{{ old('email') }}" maxlength="190" autocomplete="email">
                                    @if ($bag->has('email'))<p class="lp-error">{{ $bag->first('email') }}</p>@endif
                                </div>
                                <div>
                                    <label for="lp-phone">Phone</label>
                                    <input id="lp-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel">
                                    @if ($bag->has('phone'))<p class="lp-error">{{ $bag->first('phone') }}</p>@endif
                                </div>
                            </div>
                            <div>
                                <label for="lp-message">Message</label>
                                <textarea id="lp-message" name="message" maxlength="3000">{{ old('message') }}</textarea>
                                @if ($bag->has('message'))<p class="lp-error">{{ $bag->first('message') }}</p>@endif
                            </div>
                            <div class="lp-hp" aria-hidden="true"><label for="lp-website">Website</label><input id="lp-website" name="company_website" tabindex="-1" autocomplete="off"></div>
                            <button type="submit" class="lp-btn lp-block">Send message<x-lucide-send class="lp-i" /></button>
                            <p class="lp-note">{{ $preview ? 'Preview: the form is switched off here.' : 'We only use your details to reply to you.' }}</p>
                        </form>
                    @endif
                </div>
            @elseif ($mailto)
                <div class="lp-card lp-reveal" style="padding: 32px; text-align:center">
                    <h3>Send us a message</h3>
                    <p style="color: var(--muted); margin: 10px 0 22px">We usually reply within one working day.</p>
                    <a href="mailto:{{ $d['email'] }}" class="lp-btn">Email {{ $p['company'] }}<x-lucide-mail class="lp-i" /></a>
                </div>
            @endif
        </div>
    </div>
</section>
