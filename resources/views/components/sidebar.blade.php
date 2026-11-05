@props(['navigation'])
<div class="flex h-16 shrink-0 items-center px-5">
    <a href="{{ route('dashboard') }}"><x-logo /></a>
</div>
<nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 pb-6 pt-2" aria-label="Main">
    @foreach ($navigation as $section)
        <div>
            @if ($section['label'])
                <p class="mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $section['label'] }}</p>
            @endif
            <ul class="space-y-0.5">
                @foreach ($section['items'] as $item)
                    @php $active = request()->routeIs($item['active']); @endphp
                    <li>
                        <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $active]) @if($active) aria-current="page" @endif>
                            <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand-600' : 'text-gray-400' }}" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
<div class="border-t border-gray-100 p-4">
    <div class="rounded-xl bg-gradient-to-br from-brand-50 to-sky-50 p-4 ring-1 ring-brand-100">
        <p class="text-xs font-semibold text-brand-800">{{ auth()->user()->getRoleNames()->map(fn ($r) => config("smartcard.roles.$r.label", $r))->implode(', ') ?: 'No role' }}</p>
        <p class="mt-0.5 truncate text-xs text-brand-700/70">{{ auth()->user()->email }}</p>
    </div>
</div>
