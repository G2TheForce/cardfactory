@php
    $palette = $theme->palette ?? [];
    $state = [
        'palette' => $palette,
        'fonts' => $theme->fonts ?? ['heading' => 'Inter', 'body' => 'Inter'],
        'corner_radius_mm' => (float) ($theme->layout['corner_radius_mm'] ?? 0),
    ];
    $paletteLabels = ['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'background' => 'Background', 'text' => 'Text', 'muted' => 'Muted text'];
@endphp
<x-layouts.app :title="'Customise '.$theme->name">
    @push('head')<link rel="stylesheet" href="{{ $fontsUrl }}">@endpush
    <x-slot:breadcrumbs>
        <a href="{{ route('themes.index') }}" class="hover:text-gray-700">Themes</a><span>/</span>
        <a href="{{ route('themes.show', $theme) }}" class="hover:text-gray-700">{{ $theme->name }}</a><span>/</span><span class="text-gray-900">Customise</span>
    </x-slot:breadcrumbs>

    <div x-data="themeCustomizer(@js($state), @js(route('themes.preview', $theme)))" class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_380px]">
        {{-- Live preview --}}
        <div class="card p-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">Customise {{ $theme->name }}</h1>
                <span class="text-xs text-gray-500" x-show="loading" x-cloak>Updating preview…</span>
                <span class="text-xs text-red-600" x-show="error" x-text="error" x-cloak></span>
            </div>
            <div class="grid gap-6 bg-[radial-gradient(circle,#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] p-4 sm:p-8 2xl:grid-cols-2">
                <figure>
                    <div class="card-svg overflow-hidden rounded-[3px] shadow-lift ring-1 ring-black/5 transition-opacity" :class="loading && 'opacity-70'" x-ref="front">{!! $preview['front'] !!}</div>
                    <figcaption class="mt-2 text-center text-xs font-medium text-gray-500">Front</figcaption>
                </figure>
                <figure>
                    <div class="card-svg overflow-hidden rounded-[3px] shadow-lift ring-1 ring-black/5 transition-opacity" :class="loading && 'opacity-70'" x-ref="back">{!! $preview['back'] !!}</div>
                    <figcaption class="mt-2 text-center text-xs font-medium text-gray-500">Back</figcaption>
                </figure>
            </div>
        </div>

        {{-- Controls --}}
        <form method="POST" action="{{ route('themes.customize.store', $theme) }}" class="card flex flex-col" @input.debounce.350ms="refresh()" @change="refresh()">
            @csrf
            <div class="card-header"><h2 class="card-title">Style</h2>
                <button type="button" class="text-xs font-semibold text-brand-600 hover:text-brand-700" @click="revert()">Undo changes</button>
            </div>
            <div class="flex-1 space-y-6 p-5">
                <fieldset>
                    <legend class="form-label">Colours</legend>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($paletteLabels as $key => $label)
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-2">
                                <input type="color" name="palette[{{ $key }}]" x-model="state.palette.{{ $key }}" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0">
                                <span class="min-w-0"><span class="block text-xs font-medium text-gray-700">{{ $label }}</span><span class="block font-mono text-[11px] uppercase text-gray-400" x-text="state.palette.{{ $key }}"></span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('palette.*')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>

                <div class="grid grid-cols-1 gap-4">
                    @foreach (['heading' => 'Heading font', 'body' => 'Body font'] as $key => $label)
                        <div>
                            <label for="font-{{ $key }}" class="form-label">{{ $label }}</label>
                            <select id="font-{{ $key }}" name="fonts[{{ $key }}]" x-model="state.fonts.{{ $key }}" class="select-base" :style="'font-family: \'' + state.fonts.{{ $key }} + '\''">
                                @foreach (collect($fonts)->groupBy('category') as $category => $group)
                                    <optgroup label="{{ ucfirst($category) }}">
                                        @foreach ($group as $font)<option value="{{ $font['name'] }}">{{ $font['name'] }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                <div>
                    <label for="radius" class="form-label">Corner radius <span class="font-normal text-gray-500" x-text="state.corner_radius_mm + ' mm'"></span></label>
                    <input id="radius" type="range" name="corner_radius_mm" min="0" max="6" step="0.5" x-model.number="state.corner_radius_mm" class="w-full accent-brand-600">
                    <p class="form-hint">Rounded corners need a printer that supports die-cut corners.</p>
                </div>

                <div class="border-t border-gray-100 pt-5">
                    <x-field name="name" label="New theme name" :value="$theme->name.' (custom)'" />
                </div>
            </div>
            <div class="space-y-2 border-t border-gray-100 p-5">
                <button type="submit" class="btn-primary w-full"><x-lucide-copy class="h-4 w-4" /> Save as new theme</button>
                @if ($canSaveInPlace)
                    <button type="submit" formaction="{{ route('themes.customize.update', $theme) }}" name="_method" value="PUT" class="btn-secondary w-full">Save changes to this theme</button>
                @endif
                @if ($canReset)
                    <button type="submit" form="reset-form" class="btn-ghost w-full">Reset to original</button>
                @endif
                <p class="pt-2 text-center text-xs text-gray-500">Element layout can be edited in the card designer.</p>
            </div>
        </form>
        @if ($canReset)
            <form id="reset-form" method="POST" action="{{ route('themes.reset', $theme) }}" onsubmit="return confirm('Reset this theme to the original design?')">@csrf</form>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('themeCustomizer', (initial, previewUrl) => ({
                    state: JSON.parse(JSON.stringify(initial)),
                    loading: false,
                    error: null,
                    controller: null,
                    revert() { this.state = JSON.parse(JSON.stringify(initial)); this.$nextTick(() => this.refresh()); },
                    async refresh() {
                        this.controller?.abort();
                        this.controller = new AbortController();
                        this.loading = true; this.error = null;
                        try {
                            const res = await fetch(previewUrl, {
                                method: 'POST', signal: this.controller.signal,
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                body: JSON.stringify(this.state),
                            });
                            if (!res.ok) throw new Error(res.status === 422 ? 'Some values are invalid.' : 'Preview failed.');
                            const data = await res.json();
                            this.$refs.front.innerHTML = data.front;
                            this.$refs.back.innerHTML = data.back;
                        } catch (e) {
                            if (e.name !== 'AbortError') this.error = e.message;
                        } finally { this.loading = false; }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
