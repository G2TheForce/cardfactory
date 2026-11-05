@php $user = auth()->user(); @endphp
<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" class="flex items-center gap-2.5 rounded-lg p-1.5 hover:bg-gray-100" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
        <x-avatar :user="$user" size="sm" />
        <span class="hidden text-sm font-medium text-gray-700 sm:block">{{ $user->name }}</span>
        <x-lucide-chevron-down class="hidden h-4 w-4 text-gray-400 sm:block" />
    </button>
    <div x-show="open" x-cloak x-transition.origin.top.right
         class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lift">
        <div class="border-b border-gray-100 px-4 py-3">
            <p class="truncate text-sm font-semibold text-gray-900">{{ $user->name }}</p>
            <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
        </div>
        <a href="{{ route('settings.profile') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <x-lucide-user class="h-4 w-4 text-gray-400" /> Profile settings
        </a>
        <a href="{{ route('settings.tokens') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <x-lucide-key-round class="h-4 w-4 text-gray-400" /> API tokens
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                <x-lucide-log-out class="h-4 w-4 text-gray-400" /> Sign out
            </button>
        </form>
    </div>
</div>
