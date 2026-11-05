@props(['model', 'label', 'step' => '0.1', 'min' => null, 'max' => null, 'geometry' => true, 'suffix' => null])
<label class="block">
    <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">{{ $label }}</span>
    <span class="relative block">
        <input type="number" step="{{ $step }}" @if(! is_null($min)) min="{{ $min }}" @endif @if(! is_null($max)) max="{{ $max }}" @endif
               class="input-base py-1.5 pr-8 text-xs" x-model.number="{{ $model }}" @input.debounce.300ms="changed({ geometry: {{ $geometry ? 'true' : 'false' }} })">
        @if ($suffix)<span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">{{ $suffix }}</span>@endif
    </span>
</label>
