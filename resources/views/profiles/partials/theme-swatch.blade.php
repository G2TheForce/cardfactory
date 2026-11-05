{{-- Miniature of a profile theme. Expects $theme and optional $accent. --}}
@php $accent ??= $theme['accent']; @endphp
<span class="block overflow-hidden rounded-lg ring-1 ring-black/10" style="background: {{ $theme['colors']['bg'] }}">
    <span class="block h-7" style="background: {{ $theme['header'] === 'centered' ? $theme['colors']['bg'] : $accent }}"></span>
    <span class="-mt-3 mx-auto block h-6 w-6 rounded-full ring-2" style="background: {{ $accent }}; --tw-ring-color: {{ $theme['colors']['surface'] }}"></span>
    <span class="mx-auto mt-1.5 block h-1.5 w-12 rounded-full" style="background: {{ $theme['colors']['text'] }}"></span>
    <span class="mx-auto mt-1 block h-1 w-8 rounded-full" style="background: {{ $theme['colors']['muted'] }}"></span>
    <span class="mx-2 mb-2 mt-2 block h-3 rounded" style="background: {{ $accent }}"></span>
    <span class="mx-2 mb-2 block h-5 rounded" style="background: {{ $theme['colors']['surface'] }}; border: 1px solid {{ $theme['colors']['border'] }}"></span>
</span>
