@props(['label', 'value', 'icon', 'change' => null, 'hint' => null, 'tone' => 'brand'])
@php
$tones = [
    'brand' => 'bg-brand-50 text-brand-600', 'sky' => 'bg-sky-50 text-sky-600', 'emerald' => 'bg-emerald-50 text-emerald-600',
    'amber' => 'bg-amber-50 text-amber-600', 'violet' => 'bg-violet-50 text-violet-600', 'rose' => 'bg-rose-50 text-rose-600',
];
@endphp
<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
        <span class="grid h-9 w-9 place-items-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-dynamic-component :component="'lucide-'.$icon" class="h-[18px] w-[18px]" />
        </span>
    </div>
    <p class="mt-3 text-2xl font-bold tracking-tight text-gray-900">{{ $value }}</p>
    <div class="mt-1.5 flex items-center gap-1.5 text-xs">
        @if (! is_null($change))
            <span @class(['inline-flex items-center gap-0.5 font-semibold', 'text-emerald-600' => $change >= 0, 'text-red-600' => $change < 0])>
                @if ($change >= 0) <x-lucide-trending-up class="h-3.5 w-3.5" /> @else <x-lucide-trending-down class="h-3.5 w-3.5" /> @endif
                {{ $change >= 0 ? '+' : '' }}{{ $change }}%
            </span>
            <span class="text-gray-400">vs previous 30 days</span>
        @elseif ($hint)
            <span class="text-gray-400">{{ $hint }}</span>
        @endif
    </div>
</div>
