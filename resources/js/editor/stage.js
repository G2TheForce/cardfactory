import { ActiveSelection, Canvas, Rect, util } from 'fabric';

/**
 * The editor stage: a server-rendered SVG of the card side (the exact output
 * that is printed) with a transparent Fabric.js canvas on top that provides
 * selection, drag, resize, rotate, multi-select and snapping.
 *
 * Fabric's scene plane is measured in millimetres (origin = trimmed card's
 * top-left corner); zoom converts mm to screen pixels.
 */

const ACCENT = '#4f46e5';
const SQUARE_TYPES = ['qr', 'icon', 'social'];
const SNAP_PX = 6;

export class Stage {
    constructor({ host, svgHost, canvasEl, onTransform, onCommit, onSelect, onDblClick }) {
        this.host = host;
        this.svgHost = svgHost;
        this.onTransform = onTransform;
        this.onCommit = onCommit;
        this.onSelect = onSelect;
        this.onDblClick = onDblClick;
        this.objects = new Map();
        this.snapLines = [];
        this.snapEnabled = true;
        this.size = { width: 85.6, height: 54, bleed: 3, safe: 3 };
        this.zoom = 6;

        this.canvas = new Canvas(canvasEl, {
            selection: true,
            preserveObjectStacking: true,
            uniformScaling: true,
            uniScaleKey: 'shiftKey',
            selectionColor: 'rgba(79,70,229,0.08)',
            selectionBorderColor: ACCENT,
            selectionLineWidth: 1,
            fireRightClick: false,
            stopContextMenu: true,
            targetFindTolerance: 4,
        });

        const live = (e) => this.handleLive(e);
        this.canvas.on('object:moving', (e) => { this.snap(e.target); live(e); });
        this.canvas.on('object:scaling', live);
        this.canvas.on('object:rotating', live);
        this.canvas.on('object:resizing', live);
        this.canvas.on('object:modified', (e) => {
            if (this.loading) return;
            this.snapLines = [];
            const list = this.decomposeTargets(e.target);
            // Commit after Fabric has finished its own transform bookkeeping.
            setTimeout(() => this.onCommit(list), 0);
        });
        this.canvas.on('mouse:up', () => { if (this.snapLines.length) { this.snapLines = []; this.canvas.requestRenderAll(); } });
        const select = () => !this.loading && this.onSelect(this.canvas.getActiveObjects().map((o) => o.elId));
        this.canvas.on('selection:created', select);
        this.canvas.on('selection:updated', select);
        this.canvas.on('selection:cleared', () => { if (!this.loading) this.onSelect([]); });
        this.canvas.on('mouse:dblclick', (e) => { if (e.target?.elId) this.onDblClick(e.target.elId); });
        this.canvas.on('after:render', ({ ctx }) => this.drawSnapLines(ctx));
    }

    // ------------------------------------------------------------ layout

    setSize(size) {
        this.size = { ...this.size, ...size };
        this.applyZoom(this.zoom);
    }

    applyZoom(zoom) {
        this.zoom = Math.max(1, Math.min(40, zoom));
        const { width, height, bleed } = this.size;
        const pxW = Math.round((width + 2 * bleed) * this.zoom);
        const pxH = Math.round((height + 2 * bleed) * this.zoom);
        this.canvas.setDimensions({ width: pxW, height: pxH });
        this.canvas.setViewportTransform([this.zoom, 0, 0, this.zoom, bleed * this.zoom, bleed * this.zoom]);
        this.host.style.width = pxW + 'px';
        this.host.style.height = pxH + 'px';
        this.canvas.requestRenderAll();
        return this.zoom;
    }

    fitZoom(container) {
        const pad = window.innerWidth < 640 ? 24 : 72;
        const { width, height, bleed } = this.size;
        const zx = (container.clientWidth - pad) / (width + 2 * bleed);
        const zy = (container.clientHeight - pad) / (height + 2 * bleed);
        return this.applyZoom(Math.max(1, Math.min(zx, zy)));
    }

    setSvg(svg) {
        this.svgHost.innerHTML = svg;
    }

    // ------------------------------------------------------------ objects

    /** Rebuild Fabric proxies for every element of the side (bottom → top). */
    load(elements, selectedIds = []) {
        this.loading = true;
        this.canvas.discardActiveObject();
        this.canvas.remove(...this.canvas.getObjects());
        this.objects.clear();
        elements.forEach((el) => {
            if (el.hidden) return;
            const obj = new Rect({ originX: 'center', originY: 'center', fill: 'rgba(0,0,0,0)', strokeWidth: 0, objectCaching: false });
            obj.elId = el.id;
            obj.elType = el.type;
            this.style(obj);
            this.objects.set(el.id, obj);
            this.sync(el);
            this.canvas.add(obj);
        });
        this.select(selectedIds, false);
        this.canvas.requestRenderAll();
        this.loading = false;
    }

    style(obj) {
        obj.set({
            borderColor: ACCENT,
            cornerColor: '#ffffff',
            cornerStrokeColor: ACCENT,
            cornerStyle: 'circle',
            cornerSize: 10,
            touchCornerSize: 28,
            transparentCorners: false,
            borderScaleFactor: 1.5,
            padding: 0,
            lockScalingFlip: true,
        });
    }

    /** Position a proxy from element data (mm). */
    sync(el) {
        const obj = this.objects.get(el.id);
        if (!obj) return;
        obj.set({
            left: el.x + el.width / 2,
            top: el.y + el.height / 2,
            width: Math.max(el.width, 0.5),
            height: Math.max(el.height, 0.5),
            scaleX: 1,
            scaleY: 1,
            angle: el.rotation || 0,
            lockMovementX: !!el.locked,
            lockMovementY: !!el.locked,
            lockRotation: !!el.locked,
            lockScalingX: !!el.locked,
            lockScalingY: !!el.locked,
            hasControls: !el.locked,
            hoverCursor: el.locked ? 'default' : 'move',
        });
        const textLike = el.type === 'text' || el.type === 'heading';
        const square = SQUARE_TYPES.includes(el.type);
        obj.setControlsVisibility({
            mt: !textLike && !square && el.type !== 'line',
            mb: !textLike && !square && el.type !== 'line',
            ml: !square,
            mr: !square,
            tl: el.type !== 'line',
            tr: el.type !== 'line',
            bl: el.type !== 'line',
            br: el.type !== 'line',
        });
        obj.setCoords();
    }

    select(ids, notify = true) {
        const objs = ids.map((id) => this.objects.get(id)).filter(Boolean);
        this.canvas.discardActiveObject();
        if (objs.length === 1) {
            this.canvas.setActiveObject(objs[0]);
        } else if (objs.length > 1) {
            this.canvas.setActiveObject(new ActiveSelection(objs, { canvas: this.canvas }));
        }
        this.canvas.requestRenderAll();
        if (notify) this.onSelect(objs.map((o) => o.elId));
    }

    // ------------------------------------------------------------ transforms

    decomposeTargets(target) {
        const objs = target instanceof ActiveSelection ? target.getObjects() : [target];
        return objs.filter((o) => o.elId).map((o) => {
            const d = util.qrDecompose(o.calcTransformMatrix());
            const width = o.width * Math.abs(d.scaleX);
            const height = o.height * Math.abs(d.scaleY);
            return {
                id: o.elId,
                x: d.translateX - width / 2,
                y: d.translateY - height / 2,
                width,
                height,
                rotation: Math.round(d.angle * 100) / 100,
                scaleX: width / o.width,
                scaleY: height / o.height,
            };
        });
    }

    /** While dragging: move the rendered SVG group directly so feedback is instant. */
    handleLive(e) {
        const objs = e.target instanceof ActiveSelection ? e.target.getObjects() : [e.target];
        for (const t of this.decomposeTargets(e.target)) {
            const textLike = ['text', 'heading'].includes(objs.find((o) => o.elId === t.id)?.elType);
            if (textLike && Math.abs(t.scaleY - 1) < 0.001) t.scaleX = 1; // width-only resize: don't squash glyphs
            const g = this.svgHost.querySelector(`g[data-id="${CSS.escape(t.id)}"]`);
            if (!g) continue;
            const cx = t.x + t.width / 2;
            const cy = t.y + t.height / 2;
            g.setAttribute('transform', `rotate(${t.rotation} ${cx} ${cy}) translate(${t.x} ${t.y}) scale(${t.scaleX} ${t.scaleY})`);
        }
        this.onTransform?.();
    }

    // ------------------------------------------------------------ snapping

    snap(target) {
        this.snapLines = [];
        if (!this.snapEnabled || !target) return;
        const tol = SNAP_PX / this.zoom;
        const { width, height, safe } = this.size;
        const c = target.getCenterPoint();
        const rotated = Math.abs((target.angle || 0) % 90) > 0.01;
        const w = target.getScaledWidth();
        const h = target.getScaledHeight();

        const xs = [0, safe, width / 2, width - safe, width];
        const ys = [0, safe, height / 2, height - safe, height];
        const candidatesX = rotated ? [[c.x, 0]] : [[c.x, 0], [c.x - w / 2, w / 2], [c.x + w / 2, -w / 2]];
        const candidatesY = rotated ? [[c.y, 0]] : [[c.y, 0], [c.y - h / 2, h / 2], [c.y + h / 2, -h / 2]];

        let nx = c.x;
        let ny = c.y;
        outerX: for (const [v, offset] of candidatesX) {
            for (const line of xs) {
                if (Math.abs(v - line) < tol) { nx = line + offset; this.snapLines.push({ x: line }); break outerX; }
            }
        }
        outerY: for (const [v, offset] of candidatesY) {
            for (const line of ys) {
                if (Math.abs(v - line) < tol) { ny = line + offset; this.snapLines.push({ y: line }); break outerY; }
            }
        }
        if (nx !== c.x || ny !== c.y) {
            target.setPositionByOrigin({ x: nx, y: ny }, 'center', 'center');
            target.setCoords();
        }
    }

    drawSnapLines(ctx) {
        if (!this.snapLines.length) return;
        const vpt = this.canvas.viewportTransform;
        const { width, height, bleed } = this.size;
        ctx.save();
        ctx.strokeStyle = '#ec4899';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 3]);
        for (const l of this.snapLines) {
            ctx.beginPath();
            if (l.x !== undefined) {
                const px = l.x * vpt[0] + vpt[4];
                ctx.moveTo(px, 0); ctx.lineTo(px, (height + 2 * bleed) * vpt[3]);
            } else {
                const py = l.y * vpt[3] + vpt[5];
                ctx.moveTo(0, py); ctx.lineTo((width + 2 * bleed) * vpt[0], py);
            }
            ctx.stroke();
        }
        ctx.restore();
    }

    dispose() {
        this.canvas.dispose();
    }
}
