<x-layouts.app title="Brand kits">
    <x-page-header title="Brand kits" description="A company's logo, colours, fonts, background, details and social links in one place. Apply a kit to any design, or start new designs from it.">
        <x-slot:actions>
            <a href="{{ route('brand-kits.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New brand kit</a>
        </x-slot:actions>
    </x-page-header>

    @if ($kits->isEmpty())
        <div class="card"><x-empty-state icon="swatch-book" title="No brand kits yet" description="Save a company's logo, colours and fonts once and reuse them on every card design.">
            <a href="{{ route('brand-kits.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New brand kit</a>
        </x-empty-state></div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($kits as $kit)
                <article class="card overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lift">
                    <a href="{{ route('brand-kits.show', $kit) }}" class="flex h-24 items-center justify-center gap-4 px-5" style="background: {{ ($kit->background['type'] ?? 'solid') === 'gradient' ? 'linear-gradient('.($kit->background['angle'] ?? 135).'deg, '.($kit->background['color'] ?? '#FFFFFF').', '.($kit->background['color2'] ?? $kit->primary_color).')' : ($kit->background['color'] ?? '#FFFFFF') }}" aria-label="Open {{ $kit->name }}">
                        @if ($kit->logo_path)
                            <img src="{{ Storage::disk('public')->url($kit->logo_path) }}" alt="{{ $kit->name }} logo" class="max-h-14 max-w-[60%] object-contain">
                        @else
                            <span class="text-xl font-bold" style="color: {{ $kit->primary_color }}">{{ $kit->company_details['name'] ?? $kit->name }}</span>
                        @endif
                    </a>
                    <div class="border-t border-gray-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-sm font-semibold text-gray-900"><a href="{{ route('brand-kits.show', $kit) }}" class="hover:text-brand-700">{{ $kit->name }}</a></h2>
                                <p class="mt-0.5 truncate text-xs text-gray-500">{{ $kit->typography['heading'] ?? 'Inter' }} / {{ $kit->typography['body'] ?? 'Inter' }} · {{ $kit->card_designs_count }} {{ Str::plural('design', $kit->card_designs_count) }}@if ($kit->user_id !== auth()->id()) · {{ $kit->user?->name }}@endif</p>
                            </div>
                            <div class="flex shrink-0 gap-1" aria-label="Colours">
                                @foreach ([$kit->primary_color, $kit->secondary_color, $kit->accent_color] as $c)<span class="h-5 w-5 rounded-full ring-1 ring-black/10" style="background: {{ $c }}" title="{{ $c }}"></span>@endforeach
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <a href="{{ route('brand-kits.show', $kit) }}" class="btn-secondary btn-sm flex-1">Open</a>
                            <a href="{{ route('brand-kits.edit', $kit) }}" class="btn-ghost btn-sm" aria-label="Edit {{ $kit->name }}"><x-lucide-pencil class="h-4 w-4" /></a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($kits->hasPages())<div class="mt-8">{{ $kits->links() }}</div>@endif
    @endif
</x-layouts.app>
