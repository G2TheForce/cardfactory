<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Card not available · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-gray-50 px-6">
<div class="max-w-sm text-center">
    <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white text-gray-400 shadow-soft ring-1 ring-gray-200"><x-lucide-contact-round class="h-7 w-7" /></div>
    <h1 class="mt-5 text-xl font-bold text-gray-900">This card isn't available</h1>
    <p class="mt-2 text-sm text-gray-500">{{ $message ?? 'The digital business card you are looking for has been switched off or moved.' }}</p>
    <a href="{{ route('home') }}" class="mt-6 inline-block text-sm font-semibold text-brand-600 hover:text-brand-700">{{ config('app.name') }}</a>
</div>
</body>
</html>
