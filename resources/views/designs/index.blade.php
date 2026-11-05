@php
    $statusBadges = ['draft' => 'badge-gray', 'final' => 'badge-green', 'archived' => 'badge-amber'];
@endphp
<x-layouts.app title="Card designs">
    @push('head')
        @if ($fontsUrl)<link rel="stylesheet" href="{{ $fontsUrl }}">@endif
        @if ($fontFaceCss)<style>{!! $fontFaceCss !!}</style>@endif
    @endpush

    <x-page-header title="Card designs" description="Front and back designs, saved as you work in the visual editor.">
        <x-slot:actions>
            <a href="{{ route('designs.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New design</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-5 flex flex-col gap-2 sm:flex-row">
        <div class="relative">
            <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by design or customer" class="input-base w-full pl-9 sm:w-72" aria-label="Search designs">
        </div>
        <select name="status" class="select-base sm:w-40" aria-label="Status" onchange="this.form.submit()">
            <option value="">Any status</option>
            @foreach (\App\Services\CardDesignService::STATUSES as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </form>

    @if ($designs->isEmpty())
        <div class="card">
            <x-empty-state icon="pen-tool" title="No card designs yet" description="Start from one of the themes or a blank card, then edit everything in the visual editor.">
                <a href="{{ route('designs.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New design</a>
            </x-empty-state>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($designs as $design)
                <article class="card overflow-hidden p-4 transition hover:-translate-y-0.5 hover:shadow-lift" x-data="{ side: 'front' }">
                    @php
                        // Keep rows even: portrait cards are shown at the height of a landscape card.
                        $previewWidth = min(100, round(63.1 * (float) $design->width_mm / max(1, (float) $design->height_mm), 1));
                    @endphp
                    <a href="{{ route('designs.edit', $design) }}" class="relative mx-auto block" style="width: {{ $previewWidth }}%" @mouseenter="side = 'back'" @mouseleave="side = 'front'" aria-label="Edit {{ $design->name }}">
                        <div x-show="side === 'front'"><x-card-preview :svg="$previews[$design->id]['front']" /></div>
                        <div x-show="side === 'back'" x-cloak><x-card-preview :svg="$previews[$design->id]['back']" /></div>
                    </a>
                    <div class="mt-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-semibold text-gray-900"><a href="{{ route('designs.edit', $design) }}" class="hover:text-brand-700">{{ $design->name }}</a></h2>
                            <p class="mt-0.5 truncate text-xs text-gray-500">
                                {{ $design->customer?->full_name ?? 'No customer' }}
                                @if ($design->theme) · {{ $design->theme->name }} @endif
                            </p>
                        </div>
                        <span class="{{ $statusBadges[$design->status] ?? 'badge-gray' }}">{{ ucfirst($design->status) }}</span>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('designs.edit', $design) }}" class="btn-secondary btn-sm flex-1"><x-lucide-pen-tool class="h-3.5 w-3.5" /> Open editor</a>
                        <form method="POST" action="{{ route('designs.duplicate', $design) }}">
                            @csrf
                            <button class="btn-ghost btn-sm" title="Duplicate" aria-label="Duplicate {{ $design->name }}"><x-lucide-copy class="h-4 w-4" /></button>
                        </form>
                        @can('delete', $design)
                            <form method="POST" action="{{ route('designs.destroy', $design) }}" onsubmit="return confirm('Delete this design?')">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50" title="Delete" aria-label="Delete {{ $design->name }}"><x-lucide-trash-2 class="h-4 w-4" /></button>
                            </form>
                        @endcan
                    </div>
                    <p class="mt-3 text-[11px] text-gray-400">Updated {{ $design->updated_at->diffForHumans() }} · v{{ $design->version }} · {{ rtrim(rtrim(number_format($design->width_mm, 1), '0'), '.') }} × {{ rtrim(rtrim(number_format($design->height_mm, 1), '0'), '.') }} mm</p>
                </article>
            @endforeach
        </div>
        @if ($designs->hasPages())<div class="mt-8">{{ $designs->links() }}</div>@endif
    @endif
</x-layouts.app>
