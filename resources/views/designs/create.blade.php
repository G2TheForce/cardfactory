<x-layouts.app title="New card design">
    @push('head')
        @if ($fontsUrl)<link rel="stylesheet" href="{{ $fontsUrl }}">@endif
    @endpush
    <x-slot:breadcrumbs>
        <a href="{{ route('designs.index') }}" class="hover:text-gray-700">Card designs</a><span>/</span><span class="text-gray-900">New</span>
    </x-slot:breadcrumbs>

    <form method="POST" action="{{ route('designs.store') }}" x-data="{ theme: @js(old('theme', $selectedTheme)), name: @js(old('name', '')) }">
        @csrf
        <x-page-header title="New card design" description="Pick a theme to start from, or begin with a blank card.">
            <x-slot:actions>
                <a href="{{ route('designs.index') }}" class="btn-ghost">Cancel</a>
                <button class="btn-primary"><x-lucide-pen-tool class="h-4 w-4" /> Create and open editor</button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_340px]">
            <div class="card p-5">
                <h2 class="card-title mb-4">Start from</h2>
                @error('theme')<p class="form-error mb-3">{{ $message }}</p>@enderror
                <input type="hidden" name="theme" :value="theme ?? ''">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    <button type="button" @click="theme = null"
                            class="flex aspect-[1.585] flex-col items-center justify-center rounded-xl border-2 border-dashed text-sm font-medium transition"
                            :class="theme ? 'border-gray-200 text-gray-500 hover:border-gray-300' : 'border-brand-500 bg-brand-50 text-brand-700'">
                        <x-lucide-square-dashed class="mb-2 h-6 w-6" /> Blank card
                    </button>
                    @foreach ($themes as $t)
                        <button type="button" @click="theme = @js($t->ulid); if (! name) name = @js($t->name.' card')"
                                class="rounded-xl p-2 text-left ring-2 transition"
                                :class="theme === @js($t->ulid) ? 'ring-brand-500 bg-brand-50' : 'ring-transparent hover:ring-gray-200'"
                                aria-label="Start from {{ $t->name }}">
                            @if ((float) $t->height_mm > (float) $t->width_mm)
                                <div class="flex justify-center gap-1.5"><div class="w-[39.8%]"><x-card-preview :svg="$previews[$t->id]['front']" /></div><div class="w-[39.8%]"><x-card-preview :svg="$previews[$t->id]['back']" /></div></div>
                            @else
                                <x-card-preview :svg="$previews[$t->id]['front']" />
                            @endif
                            <span class="mt-2 block truncate text-xs font-semibold text-gray-800">{{ $t->name }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="card h-fit space-y-5 p-5 xl:sticky xl:top-24">
                <div>
                    <label for="name" class="form-label">Design name <span class="text-red-500">*</span></label>
                    <input id="name" name="name" x-model="name" required maxlength="120" class="input-base" placeholder="e.g. Alex Morgan, 2026 card">
                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div x-show="! theme">
                    <label for="size" class="form-label">Card size</label>
                    <select id="size" name="size" class="select-base">
                        @foreach ($sizes as $key => $size)
                            <option value="{{ $key }}" @selected(old('size', 'iso') === $key)>{{ $size['label'] }}</option>
                        @endforeach
                    </select>
                    @error('size')<p class="form-error">{{ $message }}</p>@enderror
                    <fieldset class="mt-3 flex gap-2">
                        <legend class="sr-only">Orientation</legend>
                        @foreach (['landscape' => 'Landscape', 'portrait' => 'Portrait'] as $value => $label)
                            <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                <input type="radio" name="orientation" value="{{ $value }}" class="sr-only" @checked(old('orientation', 'landscape') === $value)> {{ $label }}
                            </label>
                        @endforeach
                    </fieldset>
                </div>
                <p x-show="theme" x-cloak class="text-xs text-gray-500">The card size and both sides come from the selected theme. Everything stays editable.</p>

                @if ($customers->isNotEmpty())
                    <div>
                        <label for="customer" class="form-label">Customer</label>
                        <select id="customer" name="customer" class="select-base">
                            <option value="">No customer (use sample details)</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->ulid }}" @selected(old('customer', $selectedCustomer) === $customer->ulid)>{{ $customer->full_name }}{{ $customer->email ? ' · '.$customer->email : '' }}</option>
                            @endforeach
                        </select>
                        <p class="form-hint">Merge fields such as name and phone are filled from this customer.</p>
                        @error('customer')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                @elseif ($selectedCustomer)
                    <input type="hidden" name="customer" value="{{ $selectedCustomer }}">
                @endif

                <button class="btn-primary w-full"><x-lucide-pen-tool class="h-4 w-4" /> Create and open editor</button>
            </div>
        </div>
    </form>
</x-layouts.app>
