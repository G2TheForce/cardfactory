@props(['model', 'label' => 'Colour'])
{{-- Colour control: palette tokens (follow the design palette) or a custom colour. --}}
<div>
    <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">{{ $label }}</span>
    <div class="flex flex-wrap items-center gap-1.5">
        <template x-for="key in paletteKeys" :key="key">
            <button type="button" class="h-6 w-6 rounded-full border border-black/10 transition"
                    :style="`background:${design.palette[key]}`"
                    :class="{{ $model }} === 'palette.' + key ? 'ring-2 ring-brand-500 ring-offset-1' : 'hover:scale-110'"
                    :title="'Palette ' + key" :aria-label="'Use palette ' + key"
                    @click="{{ $model }} = 'palette.' + key; changed({ geometry: false })"></button>
        </template>
        <label class="relative ml-1 flex h-7 items-center gap-1.5 rounded-md border border-gray-200 bg-white px-1.5 text-[11px] font-mono text-gray-600">
            <input type="color" class="h-5 w-6 cursor-pointer border-0 bg-transparent p-0" :value="hex({{ $model }})"
                   @input="{{ $model }} = $event.target.value; changed({ geometry: false })" aria-label="Custom {{ strtolower($label) }}">
            <span x-text="String({{ $model }} ?? '').startsWith('palette.') ? String({{ $model }}).slice(8) : {{ $model }}"></span>
        </label>
    </div>
</div>
