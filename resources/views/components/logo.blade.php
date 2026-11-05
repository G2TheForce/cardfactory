@props(['dark' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lift">
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M14.5 9.5a3.5 3.5 0 0 1 0 5"/><path d="M17 8a6 6 0 0 1 0 8"/><path d="M6 10h4M6 13.5h3"/>
        </svg>
    </span>
    <span class="text-base font-bold tracking-tight {{ $dark ? 'text-white' : 'text-gray-900' }}">SmartCard<span class="text-brand-600 {{ $dark ? 'text-brand-300' : '' }}"> Studio</span></span>
</span>
