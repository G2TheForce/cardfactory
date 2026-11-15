@php
    use App\Support\LandingPages\Sections;
    use App\Support\LandingPages\Styles;
@endphp
<x-layouts.app title="New landing page">
    <x-slot:breadcrumbs>
        <a href="{{ route('landing-pages.index') }}" class="hover:text-gray-700">Landing pages</a><span>/</span><span class="text-gray-900">New landing page</span>
    </x-slot:breadcrumbs>

    <x-page-header title="New landing page" description="Pick the customer and a starting template. Everything can be changed in the designer." />

    <form method="POST" action="{{ route('landing-pages.store') }}" class="card max-w-5xl">
        @csrf
        <div class="card-body space-y-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="customer" class="form-label">Customer <span class="text-red-500">*</span></label>
                    <select id="customer" name="customer" class="select-base" required>
                        @if ($customers->count() > 1)<option value="">Choose a customer…</option>@endif
                        @foreach ($customers as $c)
                            <option value="{{ $c->ulid }}" @selected(old('customer', $selected?->ulid) === $c->ulid)>{{ $c->full_name }}{{ $c->email ? ' · '.$c->email : '' }}</option>
                        @endforeach
                    </select>
                    @error('customer')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">Their company name and contact details fill in the template.</p>
                </div>
                <div>
                    <label for="name" class="form-label">Page name <span class="font-normal text-gray-400">(optional)</span></label>
                    <input id="name" name="name" value="{{ old('name') }}" class="input-base" maxlength="120" placeholder="e.g. Spring opening">
                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">For your list. Defaults to the company name.</p>
                </div>
                <div>
                    <label for="slug" class="form-label">Web address <span class="font-normal text-gray-400">(optional)</span></label>
                    <div class="flex rounded-lg shadow-soft">
                        <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">{{ preg_replace('#^https?://#', '', url('/p')) }}/</span>
                        <input id="slug" name="slug" value="{{ old('slug') }}" class="input-base rounded-l-none shadow-none" placeholder="my-business" maxlength="60" pattern="[a-z0-9][a-z0-9\-]{1,58}[a-z0-9]">
                    </div>
                    @error('slug')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">Leave empty to use the company name.</p>
                </div>
                <div>
                    <label for="brand_kit" class="form-label">Brand kit <span class="font-normal text-gray-400">(optional)</span></label>
                    <select id="brand_kit" name="brand_kit" class="select-base">
                        <option value="">Use the template's colours</option>
                        @foreach ($brandKits as $kit)
                            <option value="{{ $kit->ulid }}" @selected(old('brand_kit') === $kit->ulid)>{{ $kit->name }}</option>
                        @endforeach
                    </select>
                    @error('brand_kit')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-hint">Applies the kit's colours, logo and company details.</p>
                </div>
            </div>

            <fieldset>
                <legend class="form-label">Template <span class="text-red-500">*</span></legend>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($templates as $key => $template)
                        @php $style = Styles::resolve($template['style'] + Styles::defaults($template['style']['palette'])); @endphp
                        <label class="group cursor-pointer overflow-hidden rounded-xl border border-gray-200 transition hover:border-gray-300 has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                            <input type="radio" name="template" value="{{ $key }}" class="sr-only" @checked(old('template', 'consultant') === $key)>
                            {{-- Miniature of the page: nav, hero, a row of cards --}}
                            <span class="block p-3" style="background: {{ $style['bg'] }}">
                                <span class="flex items-center justify-between"><span class="h-1.5 w-10 rounded-full" style="background: {{ $style['text'] }}; opacity: .7"></span><span class="h-2.5 w-8 rounded" style="background: {{ $style['accent'] }}"></span></span>
                                <span class="mt-4 block h-2.5 w-3/4 rounded-full" style="background: {{ $style['text'] }}"></span>
                                <span class="mt-1.5 block h-2.5 w-1/2 rounded-full" style="background: {{ $style['text'] }}"></span>
                                <span class="mt-2 block h-1.5 w-2/3 rounded-full" style="background: {{ $style['muted'] }}; opacity: .6"></span>
                                <span class="mt-3 block h-3.5 w-14 rounded" style="background: {{ $style['accent'] }}; border-radius: {{ $style['radius'] }}"></span>
                                <span class="mt-4 grid grid-cols-3 gap-1.5">
                                    @for ($i = 0; $i < 3; $i++)<span class="h-6 rounded" style="background: {{ $style['surface'] }}; border: 1px solid {{ $style['border'] }}"></span>@endfor
                                </span>
                            </span>
                            <span class="block border-t border-gray-100 bg-white px-3 py-2.5">
                                <span class="block text-sm font-semibold text-gray-900">{{ $template['name'] }}</span>
                                <span class="block text-xs leading-snug text-gray-500">{{ $template['description'] }}</span>
                                <span class="mt-1.5 block text-[11px] text-gray-400">{{ collect($template['sections'])->map(fn ($s) => Sections::TYPES[$s[0]]['label'])->implode(' · ') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('template')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
            <a href="{{ route('landing-pages.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Create and open designer</button>
        </div>
    </form>
</x-layouts.app>
