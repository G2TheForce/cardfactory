{{-- Decorative CSS-rendered business card used on marketing and auth screens. --}}
@props(['variant' => 'midnight', 'name' => 'Alex Morgan', 'title' => 'Head of Partnerships', 'company' => 'Northwind Studio'])
@php
$variants = [
    'midnight' => ['bg' => 'background: linear-gradient(135deg,#0f172a 0%,#1e293b 60%,#334155 100%)', 'text' => 'text-white', 'muted' => 'text-slate-300', 'accent' => '#fbbf24'],
    'pearl' => ['bg' => 'background: linear-gradient(135deg,#ffffff 0%,#f1f5f9 100%)', 'text' => 'text-slate-900', 'muted' => 'text-slate-500', 'accent' => '#4f46e5'],
    'aurora' => ['bg' => 'background: linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#db2777 100%)', 'text' => 'text-white', 'muted' => 'text-white/75', 'accent' => '#ffffff'],
    'gold' => ['bg' => 'background: linear-gradient(135deg,#0a0a0a 0%,#1c1917 100%)', 'text' => 'text-amber-100', 'muted' => 'text-amber-200/60', 'accent' => '#d4a843'],
    'ocean' => ['bg' => 'background: linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%)', 'text' => 'text-white', 'muted' => 'text-sky-100', 'accent' => '#e0f2fe'],
    'sage' => ['bg' => 'background: linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%)', 'text' => 'text-emerald-950', 'muted' => 'text-emerald-800/70', 'accent' => '#047857'],
];
@endphp
@php $v = $variants[$variant] ?? $variants['midnight']; @endphp
<div {{ $attributes->merge(['class' => 'relative aspect-[85.6/54] w-full overflow-hidden rounded-2xl shadow-2xl ring-1 ring-black/5']) }} style="{{ $v['bg'] }}">
    <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full opacity-20" style="background: {{ $v['accent'] }}"></div>
    <div class="relative flex h-full flex-col justify-between p-[7%]">
        <div class="flex items-center justify-between">
            <span class="text-[clamp(8px,1.6vw,13px)] font-semibold uppercase tracking-[0.2em] {{ $v['muted'] }}">{{ $company }}</span>
            <svg viewBox="0 0 24 24" class="h-auto w-[9%] shrink-0" fill="none" stroke="{{ $v['accent'] }}" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 8.32a7.43 7.43 0 0 1 0 7.36"/><path d="M9.46 6.21a11.76 11.76 0 0 1 0 11.58"/><path d="M12.91 4.1a15.91 15.91 0 0 1 .01 15.8"/><path d="M16.37 2a20.16 20.16 0 0 1 0 20"/></svg>
        </div>
        <div>
            <p class="text-[clamp(12px,2.6vw,22px)] font-bold leading-tight {{ $v['text'] }}">{{ $name }}</p>
            <p class="mt-0.5 text-[clamp(8px,1.5vw,13px)] {{ $v['muted'] }}">{{ $title }}</p>
            <div class="mt-[5%] h-[2px] w-[18%] rounded" style="background: {{ $v['accent'] }}"></div>
        </div>
    </div>
</div>
