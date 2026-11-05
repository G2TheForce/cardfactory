<x-layouts.app :title="$theme->name">
    @push('head')
        @if ($fontsUrl)<link rel="stylesheet" href="{{ $fontsUrl }}">@endif
    @endpush
    <x-slot:breadcrumbs>
        <a href="{{ route('themes.index') }}" class="hover:text-gray-700">Themes</a><span>/</span><span class="text-gray-900">{{ $theme->name }}</span>
    </x-slot:breadcrumbs>

    <x-page-header :title="$theme->name" :description="$theme->description">
        <x-slot:actions>
            <form method="POST" action="{{ route('themes.favorite', $theme) }}">
                @csrf
                <button class="btn-secondary"><x-lucide-heart class="h-4 w-4 {{ $isFavorite ? 'fill-rose-500 text-rose-500' : '' }}" /> {{ $isFavorite ? 'Favourited' : 'Favourite' }}</button>
            </form>
            <form method="POST" action="{{ route('themes.duplicate', $theme) }}">
                @csrf
                <button class="btn-secondary"><x-lucide-copy class="h-4 w-4" /> Duplicate</button>
            </form>
            <a href="{{ route('themes.customize', $theme) }}" class="btn-primary"><x-lucide-palette class="h-4 w-4" /> Customise</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card p-6 xl:col-span-2" x-data="{ side: 'front' }">
            <div class="mb-5 flex items-center justify-between">
                <div class="flex gap-1 rounded-lg bg-gray-100 p-1" role="tablist">
                    <button type="button" role="tab" :aria-selected="side === 'front'" @click="side = 'front'" :class="side === 'front' ? 'bg-white shadow-soft text-gray-900' : 'text-gray-500'" class="rounded-md px-3 py-1 text-sm font-medium">Front</button>
                    <button type="button" role="tab" :aria-selected="side === 'back'" @click="side = 'back'" :class="side === 'back' ? 'bg-white shadow-soft text-gray-900' : 'text-gray-500'" class="rounded-md px-3 py-1 text-sm font-medium">Back</button>
                </div>
                <a :href="'{{ url('themes/'.$theme->ulid.'/preview') }}/' + side + '.svg'" target="_blank" class="btn-ghost btn-sm"><x-lucide-download class="h-4 w-4" /> SVG preview</a>
            </div>
            <div class="mx-auto max-w-2xl bg-[radial-gradient(circle,#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] p-6 sm:p-10">
                <div x-show="side === 'front'"><x-card-preview :svg="$preview['front']" /></div>
                <div x-show="side === 'back'" x-cloak><x-card-preview :svg="$preview['back']" /></div>
            </div>
            <p class="mt-4 text-center text-xs text-gray-500">Preview uses sample details. Your own information replaces them when you design a card.</p>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2>
                    @switch($theme->status)
                        @case('published')<span class="badge-green">Published</span>@break
                        @case('private')<span class="badge-brand">Private</span>@break
                        @default<span class="badge-gray">Draft</span>
                    @endswitch
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between px-5 py-3"><dt class="text-gray-500">Category</dt><dd class="font-medium">{{ App\Support\Themes\ThemeLibrary::CATEGORIES[$theme->category] ?? $theme->category }}</dd></div>
                    <div class="flex justify-between px-5 py-3"><dt class="text-gray-500">Size</dt><dd class="font-medium">{{ (float) $theme->width_mm }} × {{ (float) $theme->height_mm }} mm · {{ ucfirst($theme->orientation) }}</dd></div>
                    <div class="flex justify-between px-5 py-3"><dt class="text-gray-500">Fonts</dt><dd class="text-right font-medium">{{ $theme->fonts['heading'] ?? '' }} / {{ $theme->fonts['body'] ?? '' }}</dd></div>
                    <div class="flex justify-between px-5 py-3"><dt class="text-gray-500">Elements</dt><dd class="font-medium">{{ count($theme->front['elements'] ?? []) }} front · {{ count($theme->back['elements'] ?? []) }} back</dd></div>
                    <div class="flex justify-between px-5 py-3"><dt class="text-gray-500">Used in</dt><dd class="font-medium">{{ $designsCount }} {{ Str::plural('design', $designsCount) }}</dd></div>
                    @if ($theme->parent)
                        <div class="flex justify-between px-5 py-3"><dt class="text-gray-500">Based on</dt><dd class="font-medium">@can('view', $theme->parent)<a href="{{ route('themes.show', $theme->parent) }}" class="text-brand-600 hover:underline">{{ $theme->parent->name }}</a>@else{{ $theme->parent->name }}@endcan</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Colour palette</h2></div>
                <div class="grid grid-cols-3 gap-3 p-5">
                    @foreach ($theme->palette ?? [] as $key => $color)
                        <div>
                            <div class="h-10 rounded-lg ring-1 ring-black/10" style="background: {{ e($color) }}"></div>
                            <p class="mt-1 text-xs font-medium capitalize text-gray-700">{{ $key }}</p>
                            <p class="font-mono text-[11px] uppercase text-gray-400">{{ $color }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if (auth()->user()->can('update', $theme) || auth()->user()->can('publish', $theme) || auth()->user()->can('delete', $theme) || auth()->user()->can('reset', $theme))
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Manage</h2></div>
                    <div class="flex flex-wrap gap-2 p-5">
                        @can('update', $theme)
                            <a href="{{ route('themes.edit', $theme) }}" class="btn-secondary btn-sm"><x-lucide-pencil class="h-4 w-4" /> Edit configuration</a>
                        @endcan
                        @can('reset', $theme)
                            <form method="POST" action="{{ route('themes.reset', $theme) }}" x-data @submit="if (! confirm('Reset this theme to the original design? Your customisations will be lost.')) $event.preventDefault()">
                                @csrf <button class="btn-secondary btn-sm"><x-lucide-rotate-ccw class="h-4 w-4" /> Reset to original</button>
                            </form>
                        @endcan
                        @can('publish', $theme)
                            @if ($theme->status === 'published')
                                <form method="POST" action="{{ route('themes.unpublish', $theme) }}">@csrf <button class="btn-secondary btn-sm"><x-lucide-eye-off class="h-4 w-4" /> Unpublish</button></form>
                            @else
                                <form method="POST" action="{{ route('themes.publish', $theme) }}">@csrf <button class="btn-primary btn-sm"><x-lucide-send class="h-4 w-4" /> Publish</button></form>
                            @endif
                        @endcan
                        @can('delete', $theme)
                            <form method="POST" action="{{ route('themes.destroy', $theme) }}" x-data @submit="if (! confirm('Delete this theme? Existing card designs keep their copy of the layout.')) $event.preventDefault()">
                                @csrf @method('DELETE') <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50"><x-lucide-trash-2 class="h-4 w-4" /> Delete</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
