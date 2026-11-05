import { Stage } from './stage';
import { makeElement, textHeight, uid, PT_TO_MM } from './elements';

/**
 * Alpine component for the card design studio. Editor state (the scene JSON)
 * lives in Alpine; the Fabric stage is kept outside Alpine's reactivity.
 */

const HISTORY_LIMIT = 100;
const round = (n, p = 2) => Math.round(n * 10 ** p) / 10 ** p;
const clone = (v) => JSON.parse(JSON.stringify(v));
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function request(url, { method = 'GET', body, form } = {}) {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: form ?? (body ? JSON.stringify(body) : undefined),
        credentials: 'same-origin',
    });
    let data = null;
    try { data = await res.json(); } catch (e) { /* empty body */ }
    if (!res.ok) {
        const first = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        const err = new Error(first || data?.message || `Request failed (${res.status})`);
        err.status = res.status;
        throw err;
    }
    return data;
}

function cardEditor(config) {
    let stage = null;
    let renderTimer = null;
    let historyTimer = null;
    let renderSeq = 0;
    let past = [];
    let future = [];
    let lastSnapshot = null;
    let clipboard = [];

    return {
        design: config.design,
        urls: config.urls,
        assets: config.assets,
        fonts: config.fonts,
        fontOptions: config.fontOptions,
        mergeFields: config.mergeFields,
        iconNames: config.icons,
        fontsHeading: config.designFonts?.heading ?? 'Inter',
        fontsBody: config.designFonts?.body ?? 'Inter',
        paletteKeys: ['primary', 'secondary', 'accent', 'background', 'text', 'muted'],
        side: 'front',
        selectedIds: [],
        tab: 'elements',
        fields: config.hasCustomer ? 'customer' : 'sample',
        zoom: 6,
        dirty: false,
        saving: false,
        savedAt: null,
        rendering: false,
        notice: null,
        error: null,
        canUndo: false,
        canRedo: false,
        iconQuery: '',
        iconResults: [],
        uploading: false,
        fontFamily: '',
        previewOpen: false,
        previewSide: 'front',
        previewSvgs: { front: '', back: '' },
        settingsOpen: false,
        mobilePanel: null,
        autosave: true,

        // ------------------------------------------------------------ getters

        get scene() { return this.design[this.side]; },
        get elements() { return this.scene.elements; },
        get layers() { return [...this.elements].reverse(); },
        get selected() { return this.selectedIds.length === 1 ? this.elements.find((e) => e.id === this.selectedIds[0]) ?? null : null; },
        get selection() { return this.elements.filter((e) => this.selectedIds.includes(e.id)); },
        get isText() { return ['text', 'heading'].includes(this.selected?.type); },
        get isImage() { return ['image', 'logo', 'photo'].includes(this.selected?.type); },
        get zoomPercent() { return Math.round((this.zoom / 3.7795) * 100); }, // 100% ≈ physical size at 96dpi
        get statusText() {
            if (this.saving) return 'Saving…';
            if (this.error) return this.error;
            if (this.dirty) return 'Unsaved changes';
            return this.savedAt ? 'All changes saved' : 'Saved';
        },

        // ------------------------------------------------------------ lifecycle

        init() {
            stage = new Stage({
                host: this.$refs.stage,
                svgHost: this.$refs.svg,
                canvasEl: this.$refs.canvas,
                onCommit: (list) => this.commitTransforms(list),
                onSelect: (ids) => { this.selectedIds = ids; },
                onDblClick: (id) => this.focusText(id),
            });
            stage.setSize(this.sizeInfo());
            stage.setSvg(config.svgs.front);
            stage.load(this.elements);
            this.$nextTick(() => { this.zoom = stage.fitZoom(this.$refs.viewport); });
            lastSnapshot = this.snapshot();

            window.addEventListener('resize', () => { if (this.autoFit) this.zoom = stage.fitZoom(this.$refs.viewport); });
            window.addEventListener('keydown', (e) => this.onKey(e));
            window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
            document.fonts?.addEventListener?.('loadingdone', () => this.fitTextBoxes());
            setInterval(() => { if (this.autosave && this.dirty && !this.saving) this.save(true); }, 30000);
            this.autoFit = true;
            this.render(0);
        },

        sizeInfo() {
            return { width: this.design.width, height: this.design.height, bleed: this.design.bleed, safe: this.design.safe };
        },

        // ------------------------------------------------------------ rendering

        render(delay = 90) {
            clearTimeout(renderTimer);
            renderTimer = setTimeout(() => this.renderNow(), delay);
        },

        async renderNow() {
            const seq = ++renderSeq;
            const side = this.side;
            this.rendering = true;
            try {
                const { svg } = await request(this.urls.render, {
                    method: 'POST',
                    body: {
                        side: clone(this.design[side]),
                        palette: clone(this.design.palette),
                        fields: this.fields,
                        guides: this.design.showGuides,
                        bleed: true,
                        width_mm: this.design.width,
                        height_mm: this.design.height,
                        corner_radius_mm: this.design.radius,
                    },
                });
                if (seq !== renderSeq || side !== this.side) return;
                stage.setSvg(svg);
                this.fitTextBoxes();
                if (this.error && !this.saving) this.error = null;
            } catch (e) {
                if (seq === renderSeq) this.error = e.message;
            } finally {
                if (seq === renderSeq) this.rendering = false;
            }
        },

        /** Grow text boxes to fit their rendered text (keeps alignment anchor). */
        fitTextBoxes() {
            let changed = false;
            for (const el of this.elements) {
                if (!['text', 'heading'].includes(el.type) || el.hidden) continue;
                const node = this.$refs.svg.querySelector(`g[data-id="${CSS.escape(el.id)}"] text`);
                if (!node) continue;
                let bbox;
                try { bbox = node.getBBox(); } catch (e) { continue; }
                const h = textHeight(el);
                if (bbox.width > el.width + 0.05) {
                    const delta = bbox.width - el.width;
                    if (el.align === 'center') el.x = round(el.x - delta / 2);
                    else if (el.align === 'right') el.x = round(el.x - delta);
                    el.width = Math.ceil(bbox.width * 10) / 10;
                    changed = true;
                }
                if (Math.abs(h - el.height) > 0.05) {
                    el.height = h;
                    changed = true;
                }
            }
            if (changed) this.refreshStage();
        },

        refreshStage() {
            stage.load(this.elements, this.selectedIds);
        },

        // ------------------------------------------------------------ changes & history

        /** Call after any edit to the scene. */
        changed({ geometry = true } = {}) {
            this.dirty = true;
            if (geometry) this.refreshStage();
            this.render();
            clearTimeout(historyTimer);
            historyTimer = setTimeout(() => this.pushHistory(), 400);
        },

        snapshot() {
            return JSON.stringify({ front: this.design.front, back: this.design.back, palette: this.design.palette, width: this.design.width, height: this.design.height, radius: this.design.radius });
        },

        pushHistory() {
            clearTimeout(historyTimer);
            const snap = this.snapshot();
            if (snap === lastSnapshot) return;
            past.push(lastSnapshot);
            if (past.length > HISTORY_LIMIT) past.shift();
            future = [];
            lastSnapshot = snap;
            this.canUndo = true;
            this.canRedo = false;
        },

        restore(snap) {
            const data = JSON.parse(snap);
            Object.assign(this.design, data);
            lastSnapshot = snap;
            this.selectedIds = this.selectedIds.filter((id) => this.elements.some((e) => e.id === id));
            stage.setSize(this.sizeInfo());
            this.refreshStage();
            this.dirty = true;
            this.render(0);
            this.canUndo = past.length > 0;
            this.canRedo = future.length > 0;
        },

        undo() {
            this.pushHistory();
            if (!past.length) return;
            future.push(lastSnapshot);
            this.restore(past.pop());
        },

        redo() {
            if (!future.length) return;
            past.push(lastSnapshot);
            this.restore(future.pop());
        },

        commitTransforms(list) {
            for (const t of list) {
                const el = this.elements.find((e) => e.id === t.id);
                if (!el || el.locked) continue;
                const cx = t.x + t.width / 2;
                const cy = t.y + t.height / 2;
                if (el.type === 'text' || el.type === 'heading') {
                    if (Math.abs(t.scaleY - 1) > 0.001) {
                        el.fontSize = round(Math.max(2, Math.min(200, el.fontSize * t.scaleY)), 1);
                    }
                    el.width = round(Math.max(1, t.width));
                    el.height = textHeight(el);
                } else if (['qr', 'icon', 'social'].includes(el.type)) {
                    const s = round(Math.max(1, Math.min(t.width, t.height)));
                    el.width = s;
                    el.height = s;
                } else {
                    el.width = round(Math.max(0.5, t.width));
                    el.height = el.type === 'line' ? el.height : round(Math.max(0.5, t.height));
                }
                el.x = round(cx - el.width / 2);
                el.y = round(cy - el.height / 2);
                el.rotation = round(((t.rotation % 360) + 360) % 360 > 180 ? (t.rotation % 360) - 360 : t.rotation % 360);
            }
            this.changed();
        },

        // ------------------------------------------------------------ element actions

        add(kind, opts = {}) {
            if ((kind === 'heading' || kind === 'text') && !opts.fontFamily) {
                opts = { ...opts, fontFamily: kind === 'heading' ? this.fontsHeading : this.fontsBody };
            }
            const el = makeElement(kind, this.design, opts);
            if (kind === 'pattern') this.elements.unshift(el); else this.elements.push(el);
            this.selectedIds = [el.id];
            this.changed();
            if (window.innerWidth < 1024) this.mobilePanel = null;
        },

        select(id, event) {
            const el = this.elements.find((e) => e.id === id);
            if (!el) return;
            if (event?.shiftKey || event?.metaKey || event?.ctrlKey) {
                this.selectedIds = this.selectedIds.includes(id) ? this.selectedIds.filter((i) => i !== id) : [...this.selectedIds, id];
            } else {
                this.selectedIds = [id];
            }
            stage.select(this.selectedIds.filter((i) => !this.elements.find((e) => e.id === i)?.hidden), false);
        },

        deselect() {
            this.selectedIds = [];
            stage.select([], false);
        },

        selectAll() {
            this.selectedIds = this.elements.filter((e) => !e.hidden && !e.locked).map((e) => e.id);
            stage.select(this.selectedIds, false);
        },

        remove(ids = this.selectedIds) {
            const set = new Set(ids);
            this.scene.elements = this.elements.filter((e) => !set.has(e.id));
            this.selectedIds = [];
            this.changed();
        },

        duplicate(ids = this.selectedIds) {
            const copies = this.selection.filter((e) => ids.includes(e.id)).map((e) => ({ ...clone(e), id: uid(), x: round(e.x + 2), y: round(e.y + 2), locked: false }));
            if (!copies.length) return;
            this.elements.push(...copies);
            this.selectedIds = copies.map((c) => c.id);
            this.changed();
        },

        copy() {
            clipboard = clone(this.selection);
        },

        paste() {
            if (!clipboard.length) return;
            const copies = clipboard.map((e) => ({ ...clone(e), id: uid(), x: round(e.x + 2), y: round(e.y + 2) }));
            this.elements.push(...copies);
            clipboard = copies;
            this.selectedIds = copies.map((c) => c.id);
            this.changed();
        },

        nudge(dx, dy) {
            const movable = this.selection.filter((e) => !e.locked);
            if (!movable.length) return;
            movable.forEach((e) => { e.x = round(e.x + dx); e.y = round(e.y + dy); });
            this.changed();
        },

        arrange(where, id = null) {
            const ids = id ? [id] : this.selectedIds;
            if (!ids.length) return;
            const list = this.elements;
            const picked = list.filter((e) => ids.includes(e.id));
            const rest = list.filter((e) => !ids.includes(e.id));
            let result;
            if (where === 'front') result = [...rest, ...picked];
            else if (where === 'back') result = [...picked, ...rest];
            else {
                result = [...list];
                const step = where === 'forward' ? 1 : -1;
                const order = step === 1 ? [...picked].reverse() : picked;
                for (const el of order) {
                    const i = result.indexOf(el);
                    const j = i + step;
                    if (j < 0 || j >= result.length || ids.includes(result[j].id)) continue;
                    [result[i], result[j]] = [result[j], result[i]];
                }
            }
            this.scene.elements = result;
            this.changed();
        },

        align(edge) {
            const { width, height } = this.design;
            const items = this.selection.filter((e) => !e.locked);
            if (!items.length) return;
            // One element aligns to the card; several align to their common bounds.
            const bounds = items.length > 1
                ? { x1: Math.min(...items.map((e) => e.x)), y1: Math.min(...items.map((e) => e.y)), x2: Math.max(...items.map((e) => e.x + e.width)), y2: Math.max(...items.map((e) => e.y + e.height)) }
                : { x1: 0, y1: 0, x2: width, y2: height };
            for (const e of items) {
                if (edge === 'left') e.x = round(bounds.x1);
                if (edge === 'center') e.x = round((bounds.x1 + bounds.x2) / 2 - e.width / 2);
                if (edge === 'right') e.x = round(bounds.x2 - e.width);
                if (edge === 'top') e.y = round(bounds.y1);
                if (edge === 'middle') e.y = round((bounds.y1 + bounds.y2) / 2 - e.height / 2);
                if (edge === 'bottom') e.y = round(bounds.y2 - e.height);
            }
            this.changed();
        },

        toggle(id, key) {
            const el = this.elements.find((e) => e.id === id);
            if (!el) return;
            el[key] = !el[key];
            if (key === 'hidden' && el.hidden) this.selectedIds = this.selectedIds.filter((i) => i !== id);
            this.changed();
        },

        focusText(id) {
            this.selectedIds = [id];
            if (window.innerWidth < 1024) this.mobilePanel = 'right';
            this.$nextTick(() => this.$refs.textInput?.focus());
        },

        textChanged() {
            if (this.selected) this.selected.height = textHeight(this.selected);
            this.changed();
        },

        insertField(key) {
            const tag = '{{' + key + '}}';
            if (key === 'logo' || key === 'photo') return this.add(key);
            if (key === 'profile_url' && !this.isText) return this.add('qr');
            if (this.isText) {
                this.selected.text = (this.selected.text ? this.selected.text + ' ' : '') + tag;
                this.textChanged();
            } else {
                this.add('text', { text: tag });
            }
        },

        setFill(mode) {
            const el = this.selected;
            if (!el) return;
            if (mode === 'gradient' && typeof el.fill !== 'object') {
                el.fill = { type: 'gradient', angle: 135, stops: [{ offset: 0, color: this.hex(el.fill) }, { offset: 1, color: this.design.palette.secondary }] };
            } else if (mode === 'solid' && typeof el.fill === 'object') {
                el.fill = el.fill.stops?.[0]?.color ?? 'palette.primary';
            }
            this.changed({ geometry: false });
        },

        // ------------------------------------------------------------ background & palette

        setBackground(type) {
            const bg = this.scene.background;
            if (type === bg.type) return;
            const base = bg.color ?? (bg.stops?.[0]?.color) ?? '#ffffff';
            if (type === 'solid') this.scene.background = { type: 'solid', color: base };
            if (type === 'gradient') this.scene.background = { type: 'gradient', angle: 135, stops: [{ offset: 0, color: this.hex(base) }, { offset: 1, color: this.design.palette.accent }] };
            if (type === 'image') this.scene.background = { type: 'image', src: this.assets[0]?.src ?? null, fit: 'cover', color: this.hex(base), overlay: null, overlayOpacity: 0.4 };
            this.changed({ geometry: false });
        },

        useAsBackground(asset) {
            this.scene.background = { type: 'image', src: asset.src, fit: 'cover', color: '#ffffff', overlay: null, overlayOpacity: 0.4 };
            this.tab = 'background';
            this.changed({ geometry: false });
        },

        tag(key) {
            return '{' + '{' + key + '}' + '}';
        },

        hex(value) {
            if (!value || typeof value !== 'string') return '#000000';
            if (value.startsWith('palette.')) return this.design.palette[value.slice(8)] ?? '#000000';
            if (/^#[0-9a-f]{6}$/i.test(value)) return value;
            if (/^#[0-9a-f]{3}$/i.test(value)) return '#' + value.slice(1).split('').map((c) => c + c).join('');
            if (/^#[0-9a-f]{8}$/i.test(value)) return value.slice(0, 7);
            const m = value.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (m) return '#' + m.slice(1, 4).map((n) => (+n).toString(16).padStart(2, '0')).join('');
            return '#000000';
        },

        // ------------------------------------------------------------ sides, zoom, fields

        switchSide(side) {
            if (side === this.side) return;
            this.side = side;
            this.selectedIds = [];
            stage.setSvg('');
            this.refreshStage();
            this.render(0);
        },

        setZoom(z) {
            this.autoFit = false;
            this.zoom = stage.applyZoom(z);
        },

        fit() {
            this.autoFit = true;
            this.zoom = stage.fitZoom(this.$refs.viewport);
        },

        setFields(mode) {
            this.fields = mode;
            this.render(0);
        },

        toggleGuides() {
            this.design.showGuides = !this.design.showGuides;
            this.dirty = true;
            this.render(0);
        },

        applySize({ width, height }) {
            this.design.width = width;
            this.design.height = height;
            stage.setSize(this.sizeInfo());
            this.fit();
            this.changed();
        },

        // ------------------------------------------------------------ uploads

        async upload(event, type = 'image', addToCard = true) {
            const files = [...(event.target.files ?? event.dataTransfer?.files ?? [])];
            event.target.value = '';
            if (!files.length) return;
            this.uploading = true;
            this.error = null;
            try {
                for (const file of files) {
                    const form = new FormData();
                    form.append('file', file);
                    form.append('type', type);
                    const asset = await request(this.urls.upload, { method: 'POST', form });
                    this.assets.unshift(asset);
                    if (addToCard) this.addImage(asset);
                }
                this.flash('Upload complete.');
            } catch (e) {
                this.error = e.message;
            } finally {
                this.uploading = false;
            }
        },

        addImage(asset) {
            this.add('image', { src: asset.src, width: asset.width, height: asset.height, name: asset.name?.replace(/\.[a-z0-9]+$/i, '').slice(0, 40) || 'Image' });
        },

        replaceImage(asset) {
            if (!this.isImage) return;
            this.selected.src = asset.src;
            this.changed({ geometry: false });
        },

        async uploadFont(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;
            const family = this.fontFamily.trim() || file.name.replace(/\.[a-z0-9]+$/i, '').replace(/[^A-Za-z0-9 -]/g, ' ').trim().slice(0, 60);
            this.uploading = true;
            this.error = null;
            try {
                const form = new FormData();
                form.append('file', file);
                form.append('family', family);
                const font = await request(this.urls.uploadFont, { method: 'POST', form });
                this.fonts.unshift(font);
                const style = document.createElement('style');
                style.textContent = `@font-face{font-family:'${font.family.replace(/'/g, '')}';src:url('${font.src}');font-display:swap;}`;
                document.head.appendChild(style);
                if (!this.fontOptions.some((f) => f.name === font.family)) this.fontOptions.unshift({ name: font.family, category: 'custom', weights: [400, 700] });
                if (this.isText) { this.selected.fontFamily = font.family; this.changed(); }
                this.fontFamily = '';
                this.flash(`Font "${font.family}" added.`);
            } catch (e) {
                this.error = e.message;
            } finally {
                this.uploading = false;
            }
        },

        async deleteAsset(asset) {
            if (!confirm('Delete this upload? Designs that use it will show an empty image.')) return;
            try {
                await request(asset.deleteUrl ?? `${this.urls.assets}/${asset.id}`, { method: 'DELETE' });
                this.assets = this.assets.filter((a) => a.id !== asset.id);
                this.fonts = this.fonts.filter((a) => a.id !== asset.id);
            } catch (e) {
                this.error = e.message;
            }
        },

        async searchIcons() {
            try {
                this.iconResults = await request(this.urls.icons + '?q=' + encodeURIComponent(this.iconQuery));
            } catch (e) {
                this.error = e.message;
            }
        },

        // ------------------------------------------------------------ save & preview

        async save(silent = false) {
            if (this.saving) return;
            this.pushHistory();
            this.saving = true;
            this.error = null;
            const sentSnapshot = this.snapshot();
            try {
                const res = await request(this.urls.save, {
                    method: 'PUT',
                    body: {
                        version: this.design.version,
                        name: this.design.name,
                        status: this.design.status,
                        front: clone(this.design.front),
                        back: clone(this.design.back),
                        palette: clone(this.design.palette),
                        width_mm: this.design.width,
                        height_mm: this.design.height,
                        bleed_mm: this.design.bleed,
                        corner_radius_mm: this.design.radius,
                        settings: { show_guides: this.design.showGuides },
                    },
                });
                this.design.version = res.version;
                this.savedAt = res.saved_at;
                if (this.snapshot() === sentSnapshot) this.dirty = false;
                if (!silent) this.flash(res.message);
            } catch (e) {
                this.error = e.status === 409 ? e.message : 'Not saved: ' + e.message;
            } finally {
                this.saving = false;
            }
        },

        async openPreview() {
            this.previewOpen = true;
            this.previewSide = this.side;
            for (const side of ['front', 'back']) {
                try {
                    const { svg } = await request(this.urls.render, {
                        method: 'POST',
                        body: { side: clone(this.design[side]), palette: clone(this.design.palette), fields: this.fields, guides: false, bleed: false, width_mm: this.design.width, height_mm: this.design.height, corner_radius_mm: this.design.radius },
                    });
                    this.previewSvgs[side] = svg;
                } catch (e) {
                    this.error = e.message;
                }
            }
        },

        flash(message) {
            this.notice = message;
            setTimeout(() => { if (this.notice === message) this.notice = null; }, 2500);
        },

        // ------------------------------------------------------------ keyboard

        onKey(e) {
            const t = e.target;
            const typing = t && (['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) || t.isContentEditable);
            const mod = e.ctrlKey || e.metaKey;
            const key = e.key.toLowerCase();

            if (mod && key === 's') { e.preventDefault(); this.save(); return; }
            if (typing) return;
            if (mod && key === 'z') { e.preventDefault(); e.shiftKey ? this.redo() : this.undo(); return; }
            if (mod && key === 'y') { e.preventDefault(); this.redo(); return; }
            if (mod && key === 'd') { e.preventDefault(); this.duplicate(); return; }
            if (mod && key === 'c') { this.copy(); return; }
            if (mod && key === 'v') { e.preventDefault(); this.paste(); return; }
            if (mod && key === 'a') { e.preventDefault(); this.selectAll(); return; }
            if (mod && e.key === ']') { e.preventDefault(); this.arrange(e.shiftKey ? 'front' : 'forward'); return; }
            if (mod && e.key === '[') { e.preventDefault(); this.arrange(e.shiftKey ? 'back' : 'backward'); return; }
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedIds.length) {
                e.preventDefault();
                this.remove(this.selection.filter((el) => !el.locked).map((el) => el.id));
                return;
            }
            if (e.key === 'Escape') { this.deselect(); this.previewOpen = false; this.settingsOpen = false; return; }
            const step = e.shiftKey ? 5 : 0.5;
            const arrows = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (arrows[e.key] && this.selectedIds.length) { e.preventDefault(); this.nudge(...arrows[e.key]); }
        },
    };
}

export { PT_TO_MM };

document.addEventListener('alpine:init', () => {
    window.Alpine.data('cardEditor', cardEditor);
});
