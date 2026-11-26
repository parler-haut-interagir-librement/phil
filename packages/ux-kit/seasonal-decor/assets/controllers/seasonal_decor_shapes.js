// The decors of the seasonal decor: how their pieces move, in which colours, and how each is drawn on a canvas.

/**
 * How the pieces of each decor move, how many there are on a large screen, their size in pixels, their speed in
 * pixels a second, how far they sway, and their colours where the site names no tokens of its own. The poppy is never
 * drawn here: the template shows it, fixed.
 */
export const DECORS = {
    snow: { motion: 'fall', count: 60, size: [3, 9], speed: [30, 70], sway: 24, colors: ['#dbe7f5', '#c4d6ec', '#ffffff'] },
    hearts: { motion: 'rise', count: 24, size: [10, 20], speed: [30, 60], sway: 18, colors: ['#e0245e', '#f06292', '#c2185b'] },
    clovers: { motion: 'fall', count: 24, size: [10, 18], speed: [30, 60], sway: 20, colors: ['#2e7d32', '#43a047', '#66bb6a'] },
    eggs: { motion: 'fall', count: 18, size: [10, 16], speed: [25, 45], sway: 12, colors: ['#f8bbd0', '#b3e5fc', '#fff59d', '#c8e6c9'] },
    bats: { motion: 'cross', count: 8, size: [16, 28], speed: [70, 120], sway: 30, colors: ['#26232e', '#3b3446'] },
    leaves: { motion: 'fall', count: 30, size: [10, 18], speed: [35, 70], sway: 40, colors: ['#d84315', '#ef6c00', '#f9a825', '#8d6e63'] },
    petals: { motion: 'fall', count: 36, size: [6, 11], speed: [25, 50], sway: 36, colors: ['#f8bbd0', '#fce4ec', '#f48fb1'] },
    lanterns: { motion: 'rise', count: 12, size: [14, 24], speed: [20, 40], sway: 10, colors: ['#d32f2f', '#e53935', '#ffb300'] },
    'maple-leaves': { motion: 'fall', count: 26, size: [12, 20], speed: [35, 65], sway: 36, colors: ['#d50000', '#e53935', '#ff7043'] },
    'fleurs-de-lis': { motion: 'fall', count: 22, size: [12, 20], speed: [30, 55], sway: 24, colors: ['#0d47a1', '#1565c0', '#ffffff'] },
};

/** The decors drawn on the canvas, in the order of `DECORS`. */
export const DRAWN = Object.keys(DECORS);

/** The fastest a piece turns, in turns a second, and the fastest a bat beats its wings: slow, never a flicker. */
export const SPIN = 0.25;
export const FLAP = 2;

/** A number between two bounds, from a draw of `random` in [0, 1). */
function between([low, high], random) {
    return low + (high - low) * random();
}

/**
 * The pieces of a decor on a screen: each its place, size, speed, colour, angle and phase; `scale`, from 0 to 1,
 * keeps a share of them, one at least. The pieces start spread over the screen, so that a still picture shows them
 * all; none for a decor that is not drawn.
 */
export function pieces(name, { width, height, colors = [], scale = 1, random = Math.random }) {
    const decor = DECORS[name];

    if (undefined === decor) {
        return [];
    }

    const palette = colors.length > 0 ? colors : decor.colors;
    const count = Math.max(1, Math.round(decor.count * Math.min(1, Math.max(0, scale)) * Math.min(1, (width * height) / (1280 * 800))));

    return Array.from({ length: count }, () => ({
        x: random() * width,
        y: random() * height,
        size: between(decor.size, random),
        speed: between(decor.speed, random),
        color: palette[Math.floor(random() * palette.length) % palette.length],
        angle: random() * Math.PI * 2,
        spin: (random() * 2 - 1) * SPIN * Math.PI * 2,
        phase: random() * Math.PI * 2,
    }));
}

/**
 * The pieces moved by `seconds`: falling, rising or crossing by their speed and swaying as they go, back on the other
 * side of the screen when they leave it.
 */
export function advance(name, list, seconds, { width, height }) {
    const decor = DECORS[name];

    if (undefined === decor) {
        return;
    }

    for (const piece of list) {
        piece.phase += seconds * Math.PI;
        piece.angle += piece.spin * seconds;
        const sway = Math.sin(piece.phase) * decor.sway * seconds;

        if ('cross' === decor.motion) {
            piece.x += piece.speed * seconds;
            piece.y += sway;

            if (piece.x - piece.size > width) {
                piece.x = -piece.size;
            }

            continue;
        }

        piece.x += sway;
        piece.y += ('rise' === decor.motion ? -1 : 1) * piece.speed * seconds;

        if ('rise' === decor.motion && piece.y + piece.size < 0) {
            piece.y = height + piece.size;
        } else if ('fall' === decor.motion && piece.y - piece.size > height) {
            piece.y = -piece.size;
        }
    }
}

/** Draws one piece of a decor, its centre at the origin of the context, already turned. */
const SHAPES = {
    snow(context, size) {
        context.beginPath();
        context.arc(0, 0, size / 2, 0, Math.PI * 2);
        context.fill();
    },
    hearts(context, size) {
        const half = size / 2;
        context.beginPath();
        context.moveTo(0, half);
        context.bezierCurveTo(-size, 0, -half, -size, 0, -half / 2);
        context.bezierCurveTo(half, -size, size, 0, 0, half);
        context.fill();
    },
    clovers(context, size) {
        const leaf = size / 4;

        for (const turn of [0, 1, 2]) {
            const angle = (turn * Math.PI * 2) / 3 - Math.PI / 2;
            context.beginPath();
            context.arc(Math.cos(angle) * leaf, Math.sin(angle) * leaf, leaf, 0, Math.PI * 2);
            context.fill();
        }

        context.fillRect(-1, leaf, 2, size / 2);
    },
    eggs(context, size) {
        context.beginPath();
        context.ellipse(0, 0, size / 2.6, size / 2, 0, 0, Math.PI * 2);
        context.fill();
    },
    bats(context, size, phase) {
        const half = size / 2;
        const beat = Math.sin(phase * FLAP) * half * 0.5;
        context.beginPath();
        context.moveTo(0, 0);
        context.quadraticCurveTo(-half / 2, -half / 2 - beat, -half, -beat);
        context.quadraticCurveTo(-half / 2, half / 4, 0, half / 3);
        context.quadraticCurveTo(half / 2, half / 4, half, -beat);
        context.quadraticCurveTo(half / 2, -half / 2 - beat, 0, 0);
        context.fill();
    },
    leaves(context, size) {
        context.beginPath();
        context.ellipse(0, 0, size / 4, size / 2, 0, 0, Math.PI * 2);
        context.fill();
    },
    petals(context, size) {
        context.beginPath();
        context.ellipse(0, 0, size / 3, size / 2, 0, 0, Math.PI * 2);
        context.fill();
    },
    lanterns(context, size) {
        const half = size / 2;
        context.beginPath();
        context.ellipse(0, 0, half * 0.8, half, 0, 0, Math.PI * 2);
        context.fill();
        context.fillRect(-half / 3, -half - 2, (half * 2) / 3, 3);
        context.fillRect(-half / 3, half - 1, (half * 2) / 3, 3);
    },
    'maple-leaves'(context, size) {
        const points = [0, -1, 0.25, -0.55, 0.6, -0.7, 0.45, -0.25, 1, 0, 0.55, 0.2, 0.6, 0.6, 0.12, 0.4, 0, 1, -0.12, 0.4, -0.6, 0.6, -0.55, 0.2, -1, 0, -0.45, -0.25, -0.6, -0.7, -0.25, -0.55];
        const half = size / 2;
        context.beginPath();
        context.moveTo(points[0] * half, points[1] * half);

        for (let index = 2; index < points.length; index += 2) {
            context.lineTo(points[index] * half, points[index + 1] * half);
        }

        context.closePath();
        context.fill();
    },
    'fleurs-de-lis'(context, size) {
        const half = size / 2;
        context.beginPath();
        context.ellipse(0, -half / 3, half / 4, half * 0.65, 0, 0, Math.PI * 2);
        context.fill();

        for (const side of [-1, 1]) {
            context.beginPath();
            context.ellipse((side * half) / 2, 0, half / 5, half / 2, (side * Math.PI) / 5, 0, Math.PI * 2);
            context.fill();
        }

        context.fillRect(-half / 2, half / 4, half, half / 6);
    },
};

/**
 * Draws a frame of the pieces of a decor on a context of `width` by `height`, at `alpha`, from 0 to 1, as they fade
 * at the end; nothing for a decor that is not drawn.
 */
export function draw(context, name, list, { width, height, alpha = 1 }) {
    const shape = SHAPES[name];
    context.clearRect(0, 0, width, height);

    if (undefined === shape) {
        return;
    }

    context.globalAlpha = Math.min(1, Math.max(0, alpha)) * 0.85;

    for (const piece of list) {
        context.save();
        context.translate(piece.x, piece.y);
        context.rotate('bats' === name ? 0 : piece.angle);
        context.fillStyle = piece.color;
        shape(context, piece.size, piece.phase);
        context.restore();
    }

    context.globalAlpha = 1;
}
