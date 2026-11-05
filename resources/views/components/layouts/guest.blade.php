@props(['title' => null, 'heading' => '', 'subheading' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white">
<div class="flex min-h-full">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm">
            <a href="{{ route('home') }}" class="inline-flex"><x-logo /></a>
            <h1 class="mt-8 text-2xl font-bold tracking-tight text-gray-900">{{ $heading }}</h1>
            @isset($subheading)<p class="mt-2 text-sm text-gray-500">{{ $subheading }}</p>@endisset
            <div class="mt-8">
                @if (session('status'))
                    <div class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
    <div class="relative hidden flex-1 overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-indigo-900 lg:block">
        <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 20%, rgba(255,255,255,.35) 0, transparent 40%), radial-gradient(circle at 80% 70%, rgba(14,165,233,.5) 0, transparent 45%);"></div>
        <div class="relative flex h-full flex-col items-center justify-center p-16">
            <div class="w-full max-w-md -rotate-6">
                <x-sample-card variant="midnight" />
            </div>
            <div class="mt-[-40px] w-full max-w-md translate-x-10 rotate-3">
                <x-sample-card variant="pearl" />
            </div>
            <p class="mt-14 max-w-sm text-center text-lg font-medium text-white/90">Design print-ready NFC business cards and the digital profile behind every tap.</p>
        </div>
    </div>
</div>
</body>
</html>
