{{-- Renders trusted, server-generated card SVG markup (CardRenderingService escapes all user content). --}}
@props(['svg', 'shadow' => true])
<div {{ $attributes->merge(['class' => 'card-svg overflow-hidden '.($shadow ? 'rounded-[3px] shadow-lift ring-1 ring-black/5' : '')]) }}>{!! $svg !!}</div>
