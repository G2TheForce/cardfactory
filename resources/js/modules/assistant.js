// AI assistant chat (resources/views/components/assistant-widget.blade.php and
// resources/views/assistant/index.blade.php). Replies stream in as plain text.

const STORAGE_KEY = 'smartcard.assistant.conversation';

function escapeHtml(text) {
    return text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/** A small, safe Markdown subset: the text is escaped first, then formatted. */
export function formatReply(text) {
    const blocks = escapeHtml(text).split(/```/);

    return blocks
        .map((block, i) => {
            if (i % 2 === 1) {
                return `<pre class="my-2 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100">${block.replace(/^\w*\n/, '')}</pre>`;
            }

            return block
                .split('\n')
                .map((line) => {
                    let html = line
                        .replace(/`([^`]+)`/g, '<code class="rounded bg-gray-100 px-1 text-[0.85em]">$1</code>')
                        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                    if (/^#{1,6}\s/.test(line)) return `<p class="mt-2 font-semibold text-gray-900">${html.replace(/^#{1,6}\s/, '')}</p>`;
                    if (/^\s*[-*]\s/.test(line)) return `<p class="pl-4 -indent-3">• ${html.replace(/^\s*[-*]\s/, '')}</p>`;
                    if (/^\s*\d+\.\s/.test(line)) return `<p class="pl-4 -indent-4">${html}</p>`;
                    return line.trim() === '' ? '<p class="h-2"></p>' : `<p>${html}</p>`;
                })
                .join('');
        })
        .join('');
}

export default (config = {}) => ({
    mode: config.mode ?? 'widget',
    configured: config.configured ?? true,
    routes: config.routes ?? {},
    conversations: config.conversations ?? [],
    remaining: config.remaining ?? null,
    open: false,
    conversation: null,
    title: '',
    messages: [],
    input: '',
    sending: false,
    loading: false,
    copiedIndex: null,

    init() {
        if (this.mode === 'page') {
            const fromUrl = new URLSearchParams(window.location.search).get('c');
            if (fromUrl) this.load(fromUrl);
        } else {
            try {
                this.conversation = window.sessionStorage.getItem(STORAGE_KEY);
            } catch (e) {
                this.conversation = null;
            }
        }
    },

    toggle() {
        this.open = !this.open;
        if (this.open) {
            if (this.conversation && this.messages.length === 0) this.load(this.conversation);
            this.$nextTick(() => this.$refs.input?.focus());
        }
    },

    format: formatReply,

    remember(ulid) {
        this.conversation = ulid;
        if (this.mode === 'page') {
            const url = new URL(window.location.href);
            ulid ? url.searchParams.set('c', ulid) : url.searchParams.delete('c');
            window.history.replaceState({}, '', url);
            return;
        }
        try {
            ulid ? window.sessionStorage.setItem(STORAGE_KEY, ulid) : window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // Private browsing: the chat simply starts fresh on the next page.
        }
    },

    newChat() {
        this.remember(null);
        this.title = '';
        this.messages = [];
        this.$nextTick(() => this.$refs.input?.focus());
    },

    async load(ulid) {
        this.loading = true;
        try {
            const response = await fetch(this.routes.conversation.replace('__ID__', ulid), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('missing');
            const data = await response.json();
            this.title = data.title;
            this.messages = data.messages;
            this.remember(data.ulid);
            this.scrollDown();
        } catch (e) {
            this.newChat();
        } finally {
            this.loading = false;
        }
    },

    async remove(ulid) {
        if (!window.confirm('Delete this conversation?')) return;
        await fetch(this.routes.conversation.replace('__ID__', ulid), {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() },
        });
        this.conversations = this.conversations.filter((c) => c.ulid !== ulid);
        if (this.conversation === ulid) this.newChat();
    },

    ask(text) {
        this.input = text;
        this.send();
    },

    async send() {
        const message = this.input.trim();
        if (!message || this.sending) return;

        this.input = '';
        this.sending = true;
        this.messages.push({ role: 'user', content: message });
        this.messages.push({ role: 'assistant', content: '', failed: false });
        // Always go through the reactive array, so Alpine sees each change.
        const reply = () => this.messages[this.messages.length - 1];
        this.scrollDown();

        try {
            const response = await fetch(this.routes.send, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'text/plain, application/json', 'X-CSRF-TOKEN': this.csrf() },
                body: JSON.stringify({ message, conversation: this.conversation, page: document.title }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                reply().content = data.message ?? (response.status === 429 ? 'Too many questions at once. Please wait a minute.' : 'Something went wrong. Please try again.');
                reply().failed = true;
                return;
            }

            const ulid = response.headers.get('X-Assistant-Conversation');
            const isNew = ulid && ulid !== this.conversation;
            this.remember(ulid);
            if (isNew) {
                this.title = decodeURIComponent(response.headers.get('X-Assistant-Title') ?? '');
                if (this.mode === 'page') this.conversations.unshift({ ulid, title: this.title, updated: 'just now' });
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            for (;;) {
                const { value, done } = await reader.read();
                if (done) break;
                reply().content += decoder.decode(value, { stream: true });
                this.scrollDown();
            }
            if (this.remaining !== null) this.remaining = Math.max(0, this.remaining - 1);
        } catch (e) {
            reply().content = reply().content || 'Could not reach the server. Check your connection and try again.';
            reply().failed = true;
        } finally {
            this.sending = false;
            this.scrollDown();
            this.$nextTick(() => this.$refs.input?.focus());
        }
    },

    async copy(index) {
        try {
            await navigator.clipboard.writeText(this.messages[index].content);
            this.copiedIndex = index;
            setTimeout(() => (this.copiedIndex = null), 2000);
        } catch (e) {
            window.prompt('Copy this text:', this.messages[index].content);
        }
    },

    keydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.send();
        }
    },

    csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    scrollDown() {
        this.$nextTick(() => {
            const el = this.$refs.scroller;
            if (el) el.scrollTop = el.scrollHeight;
        });
    },
});
