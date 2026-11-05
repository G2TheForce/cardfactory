@props(['customer', 'size' => 'md'])
@php
    $classes = ['sm' => 'h-9 w-9 text-xs', 'md' => 'h-11 w-11 text-sm', 'lg' => 'h-20 w-20 text-xl'][$size] ?? 'h-11 w-11 text-sm';
    $parts = preg_split('/\s+/', trim($customer->full_name)) ?: [''];
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
@endphp
@if ($customer->profile_photo_path)
    <img src="{{ Storage::disk('public')->url($customer->profile_photo_path) }}" alt="" {{ $attributes->merge(['class' => "$classes shrink-0 rounded-full object-cover ring-1 ring-black/5"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$classes grid shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-violet-500 font-semibold text-white"]) }} aria-hidden="true">{{ $initials }}</span>
@endif
