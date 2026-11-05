@props(['icon' => 'sparkles', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-10 text-center']) }}>
    <span class="grid h-11 w-11 place-items-center rounded-xl bg-gray-100 text-gray-400">
        <x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" />
    </span>
    <p class="mt-3 text-sm font-semibold text-gray-900">{{ $title }}</p>
    @if ($description)<p class="mt-1 max-w-xs text-sm text-gray-500">{{ $description }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
