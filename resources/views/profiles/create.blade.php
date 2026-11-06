<x-layouts.app title="New digital profile">
    <x-slot:breadcrumbs>
        <a href="{{ route('profiles.index') }}" class="hover:text-gray-700">Digital profiles</a><span>/</span><span class="text-gray-900">New profile</span>
    </x-slot:breadcrumbs>

    <x-page-header title="New digital profile" description="Pick whose card this is and how it looks. You can change everything later." />

    <form method="POST" action="{{ route('profiles.store') }}" class="card max-w-3xl"
          x-data="{ slug: @js(old('slug', $suggested)), touched: @js((bool) old('slug')), names: @js($customers->mapWithKeys(fn ($c) => [$c->ulid => $c->full_name])) }">
        @csrf
        <div class="card-body space-y-6">
            <div>
                <label for="customer" class="form-label">Customer <span class="text-red-500">*</span></label>
                <select id="customer" name="customer" class="select-base" required
                        @change="if (! touched) slug = (names[$event.target.value] || '').toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50)">
                    @if ($customers->count() > 1)<option value="">Choose a customer…</option>@endif
                    @foreach ($customers as $c)
                        <option value="{{ $c->ulid }}" @selected(old('customer', $selected?->ulid) === $c->ulid)>{{ $c->full_name }}{{ $c->email ? ' · '.$c->email : '' }}</option>
                    @endforeach
                </select>
                @error('customer')<p class="form-error">{{ $message }}</p>@enderror
                <p class="form-hint">The profile shows this customer's details, following their "show on profile" choices.</p>
            </div>

            <div>
                <label for="slug" class="form-label">Web address</label>
                <div class="flex rounded-lg shadow-soft">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">{{ preg_replace('#^https?://#', '', url('/card')) }}/</span>
                    <input id="slug" name="slug" x-model="slug" @input="touched = true" class="input-base rounded-l-none shadow-none" placeholder="jane-doe" maxlength="60" pattern="[a-z0-9][a-z0-9-]{1,58}[a-z0-9]">
                </div>
                @error('slug')<p class="form-error">{{ $message }}</p>@enderror
                <p class="form-hint">Lowercase letters, numbers and dashes. Leave empty to use the customer's name.</p>
            </div>

            @foreach (App\Support\ProfileThemes::byCategory() as $category)
                <fieldset>
                    <legend class="form-label">{{ $category['label'] }} themes</legend>
                    <p class="-mt-1 mb-2 text-xs text-gray-500">{{ $category['description'] }}</p>
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                        @foreach ($category['themes'] as $key => $theme)
                            <label class="cursor-pointer rounded-xl border border-gray-200 p-2 transition has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                                <input type="radio" name="profile_theme" value="{{ $key }}" class="sr-only" @checked(old('profile_theme', 'classic') === $key)>
                                @include('profiles.partials.theme-swatch', ['theme' => $theme])
                                <span class="mt-2 block text-center text-xs font-semibold text-gray-800">{{ $theme['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            @error('profile_theme')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
            <a href="{{ route('profiles.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Create profile</button>
        </div>
    </form>
</x-layouts.app>
