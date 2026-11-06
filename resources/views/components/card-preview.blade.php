{{-- Renders trusted, server-generated card SVG markup (CardRenderingService escapes all user content). --}}
@props(['svg', 'shadow' => true, 'blank' => false])
<div {{ $attributes->merge(['class' => 'card-svg relative overflow-hidden '.($shadow ? 'rounded-[3px] shadow-lift ring-1 ring-black/5' : '')]) }}>{!! $svg !!}@if ($blank)<div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1 text-gray-400"><x-lucide-square-dashed class="h-6 w-6" /><span class="text-xs font-medium">Blank side</span></div>@endif</div>
