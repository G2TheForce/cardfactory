/**
 * Landing page designer (landing-pages/edit). Holds the section list, renders
 * each section's fields from the schema in App\Support\LandingPages\Sections,
 * uploads images, and refreshes the live preview iframe by submitting the
 * form to the draft-preview route.
 */
export default (config) => ({
    sections: config.sections,
    types: config.types,
    icons: config.icons,
    images: config.images,
    style: config.style,
    palettes: config.palettes,
    logoPath: config.logoPath || '',
    panel: config.panel || 'sections',
    open: null,
    adding: false,
    device: 'desktop',
    dirty: false,
    saving: false,
    uploading: null,
    uploadError: null,
    previewTimer: null,
    previewing: false,
    previewScroll: 0,

    init() {
        this.open = this.sections[0]?.id ?? null;
        this.$watch('sections', () => this.changed(), { deep: true });
        this.$watch('style', () => this.changed(), { deep: true });
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty && !this.saving) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        this.$nextTick(() => this.refreshPreview(true));
    },

    get sectionsJson() {
        return JSON.stringify(this.sections);
    },

    changed() {
        this.dirty = true;
        this.refreshPreview();
    },

    // Sections ------------------------------------------------------------
    label(section) {
        return this.types[section.type]?.label ?? section.type;
    },

    summary(section) {
        const d = section.data;
        return d.heading || d.eyebrow || (d.items?.length ? `${d.items.length} item${d.items.length === 1 ? '' : 's'}` : '');
    },

    fields(type) {
        return Object.entries(this.types[type]?.fields ?? {}).map(([name, field]) => ({ name, ...field }));
    },

    itemFields(type) {
        return Object.entries(this.types[type]?.items?.fields ?? {}).map(([name, field]) => ({ name, ...field }));
    },

    blank(fields) {
        const data = {};
        Object.entries(fields).forEach(([name, field]) => {
            data[name] = field.default ?? (field.type === 'toggle' ? false : '');
        });
        return data;
    },

    newId() {
        return Math.random().toString(36).slice(2, 10).padEnd(8, '0');
    },

    add(type) {
        const schema = this.types[type];
        const data = this.blank(schema.fields);
        if (schema.items) data.items = [this.blank(schema.items.fields)];
        const section = { id: this.newId(), type, hidden: false, data };
        const at = this.sections.findIndex((s) => s.id === this.open);
        this.sections.splice(at === -1 ? this.sections.length : at + 1, 0, section);
        this.adding = false;
        this.focus(section.id);
    },

    toggle(id) {
        this.open = this.open === id ? null : id;
        if (this.open) this.focus(id, false);
    },

    focus(id, openIt = true) {
        if (openIt) this.open = id;
        this.scrollPreview(id);
    },

    move(i, d) {
        const j = i + d;
        if (j < 0 || j >= this.sections.length) return;
        const [s] = this.sections.splice(i, 1);
        this.sections.splice(j, 0, s);
    },

    duplicate(i) {
        const copy = JSON.parse(JSON.stringify(this.sections[i]));
        copy.id = this.newId();
        this.sections.splice(i + 1, 0, copy);
        this.focus(copy.id);
    },

    remove(i) {
        if (!confirm(`Delete the ${this.label(this.sections[i])} section?`)) return;
        const [s] = this.sections.splice(i, 1);
        if (this.open === s.id) this.open = null;
    },

    canAddItem(section) {
        const max = this.types[section.type]?.items?.max ?? 0;
        return section.data.items.length < max;
    },

    addItem(section) {
        if (!this.canAddItem(section)) return;
        section.data.items.push(this.blank(this.types[section.type].items.fields));
    },

    moveItem(section, i, d) {
        const j = i + d;
        if (j < 0 || j >= section.data.items.length) return;
        const [item] = section.data.items.splice(i, 1);
        section.data.items.splice(j, 0, item);
    },

    removeItem(section, i) {
        section.data.items.splice(i, 1);
    },

    // Images --------------------------------------------------------------
    imageUrl(path) {
        return path ? this.images[path] ?? null : null;
    },

    async upload(event, target, key, token = key) {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file) return;
        this.uploading = token;
        this.uploadError = null;
        const body = new FormData();
        body.append('image', file);
        try {
            const res = await fetch(config.uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
                body,
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.errors?.image?.[0] || json.message || 'Upload failed.');
            this.images[json.path] = json.url;
            if (target === 'logo') {
                this.logoPath = json.path;
                this.changed();
            } else {
                target[key] = json.path;
            }
        } catch (e) {
            this.uploadError = e.message;
        } finally {
            this.uploading = null;
        }
    },

    // Style ---------------------------------------------------------------
    usePalette(key) {
        const p = this.palettes[key];
        Object.assign(this.style, { palette: key, primary: p.primary, accent: p.accent, mode: p.mode });
    },

    // Preview -------------------------------------------------------------
    refreshPreview(now = false) {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => {
            const f = this.$refs.form;
            const frame = this.$refs.preview;
            try {
                this.previewScroll = frame?.contentWindow?.scrollY ?? 0;
            } catch (e) {
                this.previewScroll = 0;
            }
            const saved = [f.action, f.target];
            f.action = config.previewUrl;
            f.target = 'lp-preview';
            this.previewing = true;
            f.submit();
            [f.action, f.target] = saved;
        }, now ? 0 : 500);
    },

    // After a refresh, keep the section being edited in view (or where the preview was).
    previewLoaded() {
        this.previewing = false;
        if (this.open && this.scrollPreview(this.open, 'instant')) return;
        try {
            this.$refs.preview.contentWindow.scrollTo(0, this.previewScroll);
        } catch (e) {
            // Not ready yet: leave it.
        }
    },

    scrollPreview(id, behavior = 'smooth') {
        try {
            const el = this.$refs.preview?.contentDocument?.querySelector(`[data-section="${id}"]`);
            el?.scrollIntoView({ behavior, block: 'start' });
            return !!el;
        } catch (e) {
            return false;
        }
    },

    save() {
        this.saving = true;
        this.$refs.form.submit();
    },
});
