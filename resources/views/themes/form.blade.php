@php
    $editing = $theme->exists;
    $mergeTag = fn ($key) => '{'.'{'.$key.'}'.'}';
@endphp
<x-layouts.app :title="$editing ? 'Edit '.$theme->name : 'New theme'">
    <x-slot:breadcrumbs>
        <a href="{{ route('themes.index') }}" class="hover:text-gray-700">Themes</a><span>/</span>
        @if ($editing)<a href="{{ route('themes.show', $theme) }}" class="hover:text-gray-700">{{ $theme->name }}</a><span>/</span>@endif
        <span class="text-gray-900">{{ $editing ? 'Edit' : 'New theme' }}</span>
    </x-slot:breadcrumbs>
    <x-page-header :title="$editing ? 'Edit theme configuration' : 'Create a theme'" description="Themes are stored as editable layout data. Use the card designer for visual layout editing, or edit the scene JSON directly below." />

    <form method="POST" action="{{ $editing ? route('themes.update', $theme) : route('themes.store') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field name="name" label="Theme name" :value="$theme->name" required class="sm:col-span-2" />
                    <div>
                        <label for="category" class="form-label">Category</label>
                        <select id="category" name="category" class="select-base">
                            @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category', $theme->category) === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <x-field name="sort_order" type="number" label="Sort order" :value="$theme->sort_order ?? 100" min="0" />
                    <div class="sm:col-span-2">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" rows="2" class="input-base">{{ old('description', $theme->description) }}</textarea>
                        @error('description')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 sm:col-span-2">
                        <input type="checkbox" name="is_premium" value="1" class="checkbox-base" @checked(old('is_premium', $theme->is_premium))> Premium theme
                    </label>
                </div>
            </div>

            @foreach (['front' => 'Front side', 'back' => 'Back side'] as $side => $label)
                @php
                    $sceneJson = is_string(old($side)) ? old($side) : json_encode($theme->$side, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                @endphp
                <div class="card" x-data="{ text: @js($sceneJson), invalid: false,
                    format() { try { this.text = JSON.stringify(JSON.parse(this.text), null, 2); this.invalid = false } catch (e) { this.invalid = true } } }">
                    <div class="card-header">
                        <h2 class="card-title">{{ $label }} scene</h2>
                        <button type="button" class="btn-ghost btn-sm" @click="format()">Validate & format</button>
                    </div>
                    <div class="p-5">
                        <textarea name="{{ $side }}" x-model="text" rows="14" spellcheck="false" class="input-base font-mono text-xs leading-5" aria-label="{{ $label }} scene JSON"></textarea>
                        <p class="form-error" x-show="invalid" x-cloak>This is not valid JSON.</p>
                        @foreach ($errors->getMessages() as $key => $messages)
                            @if (str_starts_with($key, $side))<p class="form-error">{{ $key }}: {{ $messages[0] }}</p>@endif
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="card">
                <div class="card-header"><h2 class="card-title">Merge fields</h2></div>
                <div class="flex flex-wrap gap-2 p-5">
                    @foreach ($mergeFields as $key => $label)
                        <code class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" title="{{ $label }}">{{ $mergeTag($key) }}</code>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Palette</h2></div>
                <div class="grid grid-cols-2 gap-3 p-5">
                    @foreach (App\Support\Scene\Scene::PALETTE_KEYS as $key)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-2 text-xs font-medium capitalize text-gray-700">
                            <input type="color" name="palette[{{ $key }}]" value="{{ old("palette.$key", $theme->palette[$key] ?? '#000000') }}" class="h-8 w-10 cursor-pointer rounded border-0 p-0"> {{ $key }}
                        </label>
                    @endforeach
                    @error('palette.*')<p class="form-error col-span-2">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Typography</h2></div>
                <div class="space-y-4 p-5">
                    @foreach (['heading' => 'Heading font', 'body' => 'Body font'] as $key => $label)
                        <div>
                            <label for="fonts-{{ $key }}" class="form-label">{{ $label }}</label>
                            <select id="fonts-{{ $key }}" name="fonts[{{ $key }}]" class="select-base">
                                @foreach ($fonts as $font)<option value="{{ $font['name'] }}" @selected(old("fonts.$key", $theme->fonts[$key] ?? 'Inter') === $font['name'])>{{ $font['name'] }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Card size & print layout</h2></div>
                <div class="grid grid-cols-2 gap-4 p-5">
                    <div class="col-span-2">
                        <label for="orientation" class="form-label">Orientation</label>
                        <select id="orientation" name="orientation" class="select-base">
                            <option value="landscape" @selected(old('orientation', $theme->orientation) === 'landscape')>Landscape</option>
                            <option value="portrait" @selected(old('orientation', $theme->orientation) === 'portrait')>Portrait</option>
                        </select>
                    </div>
                    <x-field name="width_mm" type="number" step="0.1" label="Width (mm)" :value="(float) $theme->width_mm" required />
                    <x-field name="height_mm" type="number" step="0.1" label="Height (mm)" :value="(float) $theme->height_mm" required />
                    <x-field name="layout[bleed_mm]" id="bleed" type="number" step="0.5" label="Bleed (mm)" :value="$theme->layout['bleed_mm'] ?? 3" required />
                    <x-field name="layout[safe_mm]" id="safe" type="number" step="0.5" label="Safe area (mm)" :value="$theme->layout['safe_mm'] ?? 3" required />
                    <x-field name="layout[corner_radius_mm]" id="radius" type="number" step="0.5" label="Corner radius (mm)" :value="$theme->layout['corner_radius_mm'] ?? 0" required class="col-span-2" />
                </div>
            </div>

            <div class="flex gap-3">
                <a href="{{ $editing ? route('themes.show', $theme) : route('themes.index') }}" class="btn-secondary flex-1">Cancel</a>
                <button class="btn-primary flex-1">{{ $editing ? 'Save theme' : 'Create theme' }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>
