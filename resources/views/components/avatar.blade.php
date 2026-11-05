@props(['user', 'size' => 'md'])
@php $classes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-16 w-16 text-lg'][$size]; @endphp
@if ($url = $user->avatarUrl())
    <img src="{{ $url }}" alt="" {{ $attributes->merge(['class' => "$classes rounded-full object-cover ring-2 ring-white"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$classes inline-grid place-items-center rounded-full bg-gradient-to-br from-brand-500 to-sky-500 font-semibold text-white ring-2 ring-white"]) }}>{{ $user->initials() }}</span>
@endif
