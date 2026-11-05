@foreach (['success' => 'emerald', 'error' => 'red', 'warning' => 'amber'] as $key => $color)
    @if (session($key))
        <div x-data="dismissible(6000)" x-show="show" x-transition role="status"
             class="mb-6 flex items-start gap-3 rounded-xl bg-{{ $color }}-50 px-4 py-3 text-sm text-{{ $color }}-800 ring-1 ring-{{ $color }}-200">
            @if ($key === 'success') <x-lucide-circle-check class="mt-0.5 h-4 w-4 shrink-0" /> @else <x-lucide-circle-alert class="mt-0.5 h-4 w-4 shrink-0" /> @endif
            <p class="flex-1">{{ session($key) }}</p>
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss"><x-lucide-x class="h-4 w-4" /></button>
        </div>
    @endif
@endforeach
{{-- Safelist for dynamic colours: bg-emerald-50 text-emerald-800 ring-emerald-200 bg-red-50 text-red-800 ring-red-200 bg-amber-50 text-amber-800 ring-amber-200 --}}
