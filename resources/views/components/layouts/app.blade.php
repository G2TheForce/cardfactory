@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full" x-data="{ sidebarOpen: false }">
@php $navigation = \App\Support\Navigation::for(auth()->user()); @endphp
<div class="min-h-full">
    {{-- Mobile sidebar --}}
    <div x-show="sidebarOpen" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/40" @click="sidebarOpen = false"></div>
        <div x-show="sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 flex w-72 flex-col bg-white shadow-xl">
            <x-sidebar :navigation="$navigation" />
        </div>
    </div>

    {{-- Desktop sidebar --}}
    <aside class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:w-64 lg:flex-col border-r border-gray-200 bg-white">
        <x-sidebar :navigation="$navigation" />
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/80 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" class="btn-ghost -ml-2 p-2 lg:hidden" @click="sidebarOpen = true" aria-label="Open navigation">
                <x-lucide-menu class="h-5 w-5" />
            </button>
            <div class="min-w-0 flex-1">
                @isset($breadcrumbs)
                    <nav class="flex items-center gap-1.5 truncate text-sm text-gray-500" aria-label="Breadcrumb">{{ $breadcrumbs }}</nav>
                @endisset
            </div>
            <x-user-menu />
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
