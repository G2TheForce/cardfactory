@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    {{-- The editor module registers its Alpine component before app.js starts Alpine. --}}
    @vite(['resources/css/app.css', 'resources/js/editor/index.js', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full overflow-hidden bg-gray-100">
{{ $slot }}
@stack('scripts')
</body>
</html>
