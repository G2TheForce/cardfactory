@php
    $assistantConfig = [
        'mode' => 'page',
        'configured' => $configured,
        'remaining' => $remaining,
        'conversations' => $conversations,
        'routes' => ['send' => route('assistant.messages.store'), 'conversation' => route('assistant.conversations.show', '__ID__')],
    ];
@endphp
<x-layouts.app title="AI assistant">
    <div x-data="assistant(@js($assistantConfig))">
        <x-page-header title="AI assistant" description="Ask how anything in SmartCard Studio works, or have it write bios, taglines and landing page text. Your chats are private to you.">
            <x-slot:actions>
                <button type="button" class="btn-primary" @click="newChat()"><x-lucide-message-square-plus class="h-4 w-4" /> New chat</button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
            <aside class="card h-fit overflow-hidden">
                <p class="card-header card-title">Your chats</p>
                <ul class="max-h-[60vh] divide-y divide-gray-100 overflow-y-auto">
                    <template x-for="item in conversations" :key="item.ulid">
                        <li class="group flex items-center gap-2 px-4 py-2.5" :class="item.ulid === conversation ? 'bg-brand-50/60' : 'hover:bg-gray-50'">
                            <button type="button" class="min-w-0 flex-1 text-left" @click="load(item.ulid)">
                                <span class="block truncate text-sm font-medium text-gray-900" x-text="item.title"></span>
                                <span class="block text-xs text-gray-400" x-text="item.updated"></span>
                            </button>
                            <button type="button" class="btn-ghost p-1 opacity-60 hover:opacity-100" @click="remove(item.ulid)" aria-label="Delete chat"><x-lucide-trash-2 class="h-3.5 w-3.5" /></button>
                        </li>
                    </template>
                    <li x-show="conversations.length === 0" class="px-4 py-6 text-center text-xs text-gray-400">No chats yet.</li>
                </ul>
            </aside>

            <section class="card flex h-[70vh] min-h-[28rem] flex-col overflow-hidden">
                @include('assistant.partials.chat', ['mode' => 'page', 'configured' => $configured])
            </section>
        </div>
    </div>
</x-layouts.app>
