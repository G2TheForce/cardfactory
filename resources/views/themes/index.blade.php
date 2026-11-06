<x-layouts.app title="Themes">
    @push('head')
        @if ($fontsUrl)<link rel="stylesheet" href="{{ $fontsUrl }}">@endif
    @endpush

    <x-page-header title="Card themes" description="Professional, fully editable starting points for front and back designs.">
        <x-slot:actions>
            @can('create', App\Models\CardTheme::class)
                <a href="{{ route('themes.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New theme</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Scope tabs --}}
    @php
        $tabs = ['all' => 'All themes', 'favorites' => 'Favourites ('.$counts['favorites'].')', 'mine' => 'My themes ('.$counts['mine'].')'];
        if (auth()->user()->can('themes.manage')) { $tabs['drafts'] = 'Drafts ('.$counts['drafts'].')'; }
    @endphp
    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <nav class="flex gap-1 overflow-x-auto rounded-xl bg-gray-100 p-1" aria-label="Theme scope">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('themes.index', array_filter(['scope' => $key === 'all' ? null : $key, 'category' => $filters['category'] ?? null, 'search' => $filters['search'] ?? null])) }}"
                   @class(['whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition', 'bg-white text-gray-900 shadow-soft' => $filters['scope'] === $key, 'text-gray-500 hover:text-gray-800' => $filters['scope'] !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="flex gap-2">
            @if ($filters['scope'] !== 'all')<input type="hidden" name="scope" value="{{ $filters['scope'] }}">@endif
            <div class="relative">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search themes" class="input-base w-56 pl-9" aria-label="Search themes">
            </div>
            <select name="category" class="select-base w-44" aria-label="Category" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($themes->isEmpty())
        <div class="card"><x-empty-state icon="palette" title="No themes found" description="Try another category, or clear your search." /></div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($themes as $theme)
                <article class="group card overflow-hidden p-4 transition hover:-translate-y-0.5 hover:shadow-lift" x-data="{ side: 'front' }">
                    @if ((float) $theme->height_mm > (float) $theme->width_mm)
                        {{-- Portrait themes show front and back side by side, at the height of a landscape card. --}}
                        <a href="{{ route('themes.show', $theme) }}" class="flex justify-center gap-3" aria-label="Open {{ $theme->name }}">
                            <div class="w-[39.8%]"><x-card-preview :svg="$previews[$theme->id]['front']" /></div>
                            <div class="w-[39.8%]"><x-card-preview :svg="$previews[$theme->id]['back']" /></div>
                        </a>
                    @else
                        <a href="{{ route('themes.show', $theme) }}" class="relative block" @mouseenter="side = 'back'" @mouseleave="side = 'front'" aria-label="Open {{ $theme->name }}">
                            <div x-show="side === 'front'"><x-card-preview :svg="$previews[$theme->id]['front']" /></div>
                            <div x-show="side === 'back'" x-cloak><x-card-preview :svg="$previews[$theme->id]['back']" /></div>
                        </a>
                    @endif
                    <div class="mt-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-semibold text-gray-900"><a href="{{ route('themes.show', $theme) }}" class="hover:text-brand-700">{{ $theme->name }}</a></h2>
                            <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                                {{ $categories[$theme->category] ?? $theme->category }}
                                @if ($theme->is_premium)<span class="badge-amber">Premium</span>@endif
                                @if ($theme->status === 'draft')<span class="badge-gray">Draft</span>@endif
                                @if ($theme->status === 'private')<span class="badge-brand">Mine</span>@endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('themes.favorite', $theme) }}">
                            @csrf
                            <button class="rounded-lg p-1.5 transition hover:bg-gray-100 {{ $theme->is_favorite ? 'text-rose-500' : 'text-gray-300 hover:text-rose-400' }}" aria-label="{{ $theme->is_favorite ? 'Remove from favourites' : 'Add to favourites' }}" aria-pressed="{{ $theme->is_favorite ? 'true' : 'false' }}">
                                <x-lucide-heart class="h-5 w-5 {{ $theme->is_favorite ? 'fill-current' : '' }}" />
                            </button>
                        </form>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <a href="{{ route('themes.customize', $theme) }}" class="btn-secondary btn-sm flex-1">Customise</a>
                        <a href="{{ route('themes.show', $theme) }}" class="btn-ghost btn-sm">Details</a>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($themes->hasPages())<div class="mt-8">{{ $themes->links() }}</div>@endif
    @endif
</x-layouts.app>
