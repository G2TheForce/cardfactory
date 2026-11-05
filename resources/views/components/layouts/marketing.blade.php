@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <meta name="description" content="Design print-ready NFC smart business cards, QR codes and mobile digital profiles in one place.">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white" x-data="{ menu: false }">
<header class="sticky top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}"><x-logo /></a>
        <nav class="hidden items-center gap-8 text-sm font-medium text-gray-600 md:flex" aria-label="Primary">
            <a href="{{ route('home') }}#features" class="hover:text-gray-900">Features</a>
            <a href="{{ route('home') }}#themes" class="hover:text-gray-900">Themes</a>
            <a href="{{ route('home') }}#how" class="hover:text-gray-900">How it works</a>
            <a href="{{ route('home') }}#pricing" class="hover:text-gray-900">Pricing</a>
            <a href="{{ route('home') }}#faq" class="hover:text-gray-900">FAQ</a>
        </nav>
        <div class="hidden items-center gap-2 md:flex">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary">Open dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
                <a href="{{ route('register') }}" class="btn-primary">Start free</a>
            @endauth
        </div>
        <button class="btn-ghost p-2 md:hidden" @click="menu = !menu" aria-label="Toggle menu"><x-lucide-menu class="h-5 w-5" /></button>
    </div>
    <div x-show="menu" x-cloak x-transition class="border-t border-gray-100 px-4 py-4 md:hidden">
        <nav class="flex flex-col gap-3 text-sm font-medium text-gray-700" @click="menu = false">
            <a href="{{ route('home') }}#features">Features</a><a href="{{ route('home') }}#themes">Themes</a>
            <a href="{{ route('home') }}#how">How it works</a><a href="{{ route('home') }}#pricing">Pricing</a><a href="{{ route('home') }}#faq">FAQ</a>
            @auth <a href="{{ route('dashboard') }}" class="btn-primary">Open dashboard</a>
            @else <a href="{{ route('login') }}" class="btn-secondary">Sign in</a><a href="{{ route('register') }}" class="btn-primary">Start free</a> @endauth
        </nav>
    </div>
</header>
<main>{{ $slot }}</main>
<footer class="border-t border-gray-100 bg-gray-50">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-10 text-sm text-gray-500 sm:flex-row sm:px-6 lg:px-8">
        <x-logo />
        <div class="flex gap-6">
            <a href="{{ route('legal', 'terms') }}" class="hover:text-gray-900">Terms</a>
            <a href="{{ route('legal', 'privacy') }}" class="hover:text-gray-900">Privacy</a>
            <a href="{{ route('home') }}#contact" class="hover:text-gray-900">Contact</a>
        </div>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </div>
</footer>
</body>
</html>
