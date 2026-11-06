/** Defaults for elements added from the editor's Elements panel (mm / pt). */

export const PT_TO_MM = 0.352778;

export function uid() {
    return 'el_' + Math.random().toString(36).slice(2, 10);
}

/** Height of a text box that fits all its lines. */
export function textHeight(el) {
    const lines = String(el.text ?? '').split(/\r\n|\r|\n/).length;
    return Math.round(lines * el.fontSize * PT_TO_MM * (el.lineHeight || 1.2) * 100) / 100;
}

function base(type, name, width, height, card) {
    return {
        id: uid(),
        type,
        name,
        x: Math.round(((card.width - width) / 2) * 10) / 10,
        y: Math.round(((card.height - height) / 2) * 10) / 10,
        width,
        height,
        rotation: 0,
        opacity: 1,
        locked: false,
        hidden: false,
    };
}

export function makeElement(kind, card, opts = {}) {
    switch (kind) {
        case 'heading':
        case 'text': {
            const heading = kind === 'heading';
            const el = {
                ...base(kind, heading ? 'Heading' : 'Text', heading ? 50 : 40, 0, card),
                text: opts.text ?? (heading ? 'Your heading' : 'Your text here'),
                fontFamily: opts.fontFamily ?? 'Inter',
                fontSize: heading ? 14 : 8,
                fontWeight: heading ? 700 : 400,
                fontStyle: 'normal',
                color: 'palette.text',
                align: 'left',
                letterSpacing: 0,
                lineHeight: 1.2,
                textTransform: 'none',
                underline: false,
            };
            el.height = textHeight(el);
            el.y = Math.round(((card.height - el.height) / 2) * 10) / 10;
            return el;
        }
        case 'shape': {
            const shape = opts.shape ?? 'rect';
            const square = shape === 'circle';
            return {
                ...base('shape', shape === 'rect' && opts.radius ? 'Rounded rectangle' : shape[0].toUpperCase() + shape.slice(1), square ? 18 : 28, 18, card),
                shape,
                fill: 'palette.accent',
                stroke: null,
                strokeWidth: 0,
                radius: opts.radius ?? 0,
            };
        }
        case 'line':
            return { ...base('line', 'Line', 40, 1, card), stroke: 'palette.accent', strokeWidth: 0.4, dash: opts.dash ?? 'solid', cap: 'butt' };
        case 'icon':
            return { ...base('icon', opts.icon ?? 'Icon', 8, 8, card), icon: opts.icon ?? 'star', color: 'palette.accent', strokeWidth: 2 };
        case 'social':
            return { ...base('social', opts.network ?? 'Social', 6, 6, card), network: opts.network ?? 'linkedin', color: '#ffffff', style: 'circle', background: 'palette.accent' };
        case 'qr':
            return { ...base('qr', 'QR code', 20, 20, card), data: '{{profile_url}}', color: '#000000', background: '#ffffff', margin: 1 };
        case 'logo':
            return { ...base('logo', 'Logo', 20, 12, card), src: '{{logo}}', fit: 'contain', mask: 'none', radius: 0, border: 0, borderColor: 'palette.background', placeholderColor: 'palette.muted' };
        case 'photo':
            return { ...base('photo', 'Photo', 18, 18, card), src: '{{photo}}', fit: 'cover', mask: 'circle', radius: 0, border: 0, borderColor: 'palette.background', placeholderColor: 'palette.muted' };
        case 'image': {
            const max = 30;
            const ratio = opts.width && opts.height ? opts.width / opts.height : 1.5;
            const w = ratio >= 1 ? max : max * ratio;
            const h = ratio >= 1 ? max / ratio : max;
            return { ...base('image', opts.name ?? 'Image', Math.round(w * 10) / 10, Math.round(h * 10) / 10, card), src: opts.src, fit: 'contain', mask: 'none', radius: 0, border: 0, borderColor: 'palette.background', placeholderColor: 'palette.muted' };
        }
        case 'pattern': {
            const el = { ...base('pattern', 'Pattern', card.width, card.height, card), pattern: opts.pattern ?? 'dots', color: 'palette.accent', spacing: 3, size: 0.4, opacity: 0.25 };
            el.x = 0; el.y = 0;
            return el;
        }
        default:
            throw new Error('Unknown element kind ' + kind);
    }
}

/**
 * Move a newly added element to the free spot nearest the card centre, so it
 * doesn't land on top of existing content. Large elements (backgrounds, frames)
 * don't count as occupied space. When the card is full, the spot that covers
 * the least existing content wins.
 */
export function placeInFreeSpot(el, elements, card) {
    const margin = Math.min(card.safe ?? 3, (card.width - el.width) / 2, (card.height - el.height) / 2);
    if (margin < 0) return el;
    const cardArea = card.width * card.height;
    const gap = 1.5;
    const boxes = elements
        .filter((e) => e.id !== el.id && !e.hidden && e.type !== 'pattern' && e.width * e.height < cardArea * 0.4)
        .map((e) => ({ x1: e.x - gap, y1: e.y - gap, x2: e.x + e.width + gap, y2: e.y + e.height + gap }));
    const covered = (x, y) => boxes.reduce((sum, b) => {
        const w = Math.min(x + el.width, b.x2) - Math.max(x, b.x1);
        const h = Math.min(y + el.height, b.y2) - Math.max(y, b.y1);
        return sum + (w > 0 && h > 0 ? w * h : 0);
    }, 0);

    if (covered(el.x, el.y) === 0) return el;

    const cx = el.x;
    const cy = el.y;
    let best = null;
    for (let x = margin; x + el.width <= card.width - margin + 0.01; x += 1) {
        for (let y = margin; y + el.height <= card.height - margin + 0.01; y += 1) {
            // Prefer spots below the centre, so new text follows what is already there.
            const score = covered(x, y) * 1000 + (x - cx) ** 2 + (y - cy) ** 2 * (y < cy ? 3 : 1);
            if (!best || score < best.score) best = { x, y, score };
        }
    }
    if (best) {
        el.x = Math.round(best.x * 10) / 10;
        el.y = Math.round(best.y * 10) / 10;
    }

    return el;
}
