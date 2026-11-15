@php
    $suggestions = [
        'How do I export a print-ready PDF with bleed?',
        'Write a short bio for a wedding photographer',
        'Give me three hero headlines for a café landing page',
        'How do I move an NFC card to another profile?',
    ];
@endphp
{{-- Message list and composer, shared by the floating widget and the full page. Needs the "assistant" Alpine component. --}}
<div x-ref="scroller" class="flex-1 space-y-4 overflow-y-auto p-4" aria-live="polite">
    <template x-if="loading">
        <p class="text-center text-sm text-gray-400">Loading…</p>
    </template>

    <template x-if="!loading && messages.length === 0">
        <div class="py-4 text-center">
            <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-sparkles class="h-5 w-5" /></span>
            <p class="mt-3 text-sm font-semibold text-gray-900">How can I help?</p>
            <p class="mt-1 text-xs text-gray-500">Ask how something works, or ask me to write a bio, tagline or landing page text.</p>
            <div class="mt-4 flex flex-col gap-2">
                @foreach ($suggestions as $suggestion)
                    <button type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-left text-xs text-gray-700 transition hover:border-brand-300 hover:bg-brand-50/40" :disabled="!configured || sending" @click="ask(@js($suggestion))">{{ $suggestion }}</button>
                @endforeach
            </div>
        </div>
    </template>

    <template x-for="(message, index) in messages" :key="index">
        <div :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
            <div x-show="message.role === 'user'" class="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-brand-600 px-3.5 py-2 text-sm text-white" x-text="message.content"></div>
            <div x-show="message.role === 'assistant'" class="group max-w-[92%]">
                <div class="rounded-2xl rounded-bl-md px-3.5 py-2 text-sm leading-relaxed"
                     :class="message.failed ? 'bg-amber-50 text-amber-900 ring-1 ring-amber-200' : 'bg-gray-100 text-gray-800'">
                    <span x-show="message.content === ''" class="inline-flex gap-1 py-1" aria-label="Writing">
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:150ms]"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:300ms]"></span>
                    </span>
                    <div x-html="format(message.content)"></div>
                </div>
                <button type="button" x-show="message.content !== '' && !message.failed && !(sending && index === messages.length - 1)"
                        class="mt-1 inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-700" @click="copy(index)">
                    <x-lucide-copy class="h-3.5 w-3.5" /><span x-text="copiedIndex === index ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </div>
    </template>
</div>

<form class="border-t border-gray-100 p-3" @submit.prevent="send()">
    @unless ($configured)
        <p class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">
            The assistant isn't set up yet. An administrator needs to add <code>ANTHROPIC_API_KEY</code> to the server's <code>.env</code> file.
        </p>
    @endunless
    <div class="flex items-end gap-2">
        <label for="assistant-input-{{ $mode }}" class="sr-only">Message the assistant</label>
        <textarea id="assistant-input-{{ $mode }}" x-ref="input" x-model="input" @keydown="keydown($event)" rows="2"
                  maxlength="{{ config('smartcard.assistant.max_message_length', 4000) }}"
                  class="input-base max-h-40 resize-none" placeholder="Ask anything about SmartCard Studio…"
                  :disabled="!configured"></textarea>
        <button type="submit" class="btn-primary h-10 w-10 shrink-0 p-0" :disabled="!configured || sending || input.trim() === ''" aria-label="Send">
            <x-lucide-send-horizontal class="h-4 w-4" />
        </button>
    </div>
    <p class="mt-1.5 flex justify-between text-[11px] text-gray-400">
        <span>Enter to send, Shift+Enter for a new line. Replies can be wrong; check before you publish.</span>
        <span x-show="remaining !== null" x-text="remaining + ' left today'"></span>
    </p>
</form>
