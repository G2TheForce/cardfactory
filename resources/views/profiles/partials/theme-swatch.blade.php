{{-- Miniature of a profile theme. Expects $theme and optional $accent. --}}
@php $accent ??= $theme['accent']; @endphp
@if (($theme['layout'] ?? null) === 'studio')
    {{-- Profile Studio: a tiny one-page site with an arched portrait and a dark band. --}}
    <span class="block overflow-hidden rounded-lg ring-1 ring-black/10" style="background: {{ $theme['colors']['bg'] }}">
        <span class="flex items-center gap-1 px-2 pt-2">
            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $accent }}"></span>
            <span class="h-1 w-6 rounded-full" style="background: {{ $theme['colors']['text'] }}"></span>
            <span class="ml-auto h-1.5 w-4 rounded-full" style="background: {{ $accent }}"></span>
        </span>
        <span class="flex items-center gap-2 px-2 pb-2 pt-2">
            <span class="flex-1 space-y-1">
                <span class="block h-2 w-full rounded-sm" style="background: {{ $theme['colors']['text'] }}"></span>
                <span class="block h-2 w-3/4 rounded-sm" style="background: {{ $accent }}"></span>
                <span class="block h-1 w-full rounded-full" style="background: {{ $theme['colors']['muted'] }}; opacity: .6"></span>
                <span class="mt-1.5 block h-2 w-8 rounded-full" style="background: {{ $accent }}"></span>
            </span>
            <span class="block h-11 w-9 rounded-t-full rounded-b-md" style="background: color-mix(in srgb, {{ $accent }} 22%, {{ $theme['colors']['bg'] }}); box-shadow: 3px -3px 0 -1.5px {{ $theme['colors']['bg'] }}, 3px -3px 0 0 {{ $accent }}"></span>
        </span>
        <span class="block h-3" style="background: {{ $theme['deep'] ?? $theme['colors']['text'] }}"></span>
    </span>
@else
<span class="block overflow-hidden rounded-lg ring-1 ring-black/10" style="background: {{ $theme['colors']['bg'] }}">
    <span class="block h-7" style="background: {{ $theme['header'] === 'centered' ? $theme['colors']['bg'] : (isset($theme['gradient_to']) ? 'linear-gradient(120deg, '.$accent.', '.$theme['gradient_to'].')' : $accent) }}"></span>
    <span class="-mt-3 mx-auto block h-6 w-6 rounded-full ring-2" style="background: {{ $accent }}; --tw-ring-color: {{ $theme['colors']['surface'] }}"></span>
    <span class="mx-auto mt-1.5 block h-1.5 w-12 rounded-full" style="background: {{ $theme['colors']['text'] }}"></span>
    <span class="mx-auto mt-1 block h-1 w-8 rounded-full" style="background: {{ $theme['colors']['muted'] }}"></span>
    <span class="mx-2 mb-2 mt-2 block h-3 rounded" style="background: {{ $accent }}"></span>
    <span class="mx-2 mb-2 block h-5 rounded" style="background: {{ $theme['colors']['surface'] }}; border: 1px solid {{ $theme['colors']['border'] }}"></span>
</span>
@endif
