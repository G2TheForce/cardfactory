{{-- Floating AI assistant on every app page (not on the assistant's own page). --}}
@php
    $showAssistant = auth()->check() && Route::has('assistant.index') && auth()->user()->can('assistant.use') && ! request()->routeIs('assistant.*');
    $assistantConfig = $showAssistant ? [
        'mode' => 'widget',
        'configured' => app(\App\Services\Assistant\AssistantService::class)->isConfigured(),
        'routes' => ['send' => route('assistant.messages.store'), 'conversation' => route('assistant.conversations.show', '__ID__')],
    ] : [];
@endphp
@if ($showAssistant)
    <div x-data="assistant(@js($assistantConfig))" class="fixed bottom-4 right-4 z-40 flex flex-col items-end gap-3 sm:bottom-6 sm:right-6" @keydown.escape.window="open = false">
        <section x-show="open" x-cloak x-transition.origin.bottom.right
                 class="flex h-[min(36rem,calc(100vh-7rem))] w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl sm:w-96"
                 role="dialog" aria-label="AI assistant">
            <header class="flex items-center gap-2 border-b border-gray-100 px-4 py-3">
                <x-lucide-sparkles class="h-4 w-4 text-brand-600" />
                <p class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900" x-text="title || 'AI assistant'"></p>
                <button type="button" class="btn-ghost p-1.5" @click="newChat()" title="New chat" aria-label="New chat"><x-lucide-message-square-plus class="h-4 w-4" /></button>
                <a href="{{ route('assistant.index') }}" class="btn-ghost p-1.5" title="Open full page" aria-label="Open full page"
                   :href="conversation ? '{{ route('assistant.index') }}?c=' + conversation : '{{ route('assistant.index') }}'"><x-lucide-maximize-2 class="h-4 w-4" /></a>
                <button type="button" class="btn-ghost p-1.5" @click="open = false" aria-label="Close"><x-lucide-x class="h-4 w-4" /></button>
            </header>
            @include('assistant.partials.chat', ['mode' => 'widget', 'configured' => $assistantConfig['configured']])
        </section>

        <button type="button" @click="toggle()" class="grid h-12 w-12 place-items-center rounded-full bg-brand-600 text-white shadow-lg transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                :aria-expanded="open" aria-label="Open AI assistant">
            <x-lucide-sparkles x-show="!open" class="h-5 w-5" />
            <x-lucide-x x-show="open" x-cloak class="h-5 w-5" />
        </button>
    </div>
@endif
