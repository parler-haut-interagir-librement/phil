/*
 * The effects of the celebrations: each makes the pieces the painter of `celebration_worker.js` moves and draws, for a
 * box of a width and a height, in the colours of the theme, as many as the scale allows, for a duration.
 */

/** The effects drawn, the first by default. */
export const EFFECTS = ['confetti', 'fireworks', 'lasers', 'balloons', 'shooting-stars', 'streamers', 'coins', 'bubbles', 'aurora', 'spotlight', 'trophy'];

/** The fewest milliseconds between two bursts of fireworks: two a second at most, under the three flashes of WCAG 2.3.1. */
export const BURST_GAP = 500;

/** How long a burst of fireworks shines, in milliseconds, before it has faded. */
export const BURST_LIFE = 1200;

/** The fewest milliseconds between two waves of laser beams: two a second at most, under the three flashes of WCAG 2.3.1. */
export const BEAM_GAP = 500;

/** How long a laser beam takes to rise to its full light, in milliseconds: a fade, never a flash. */
export const BEAM_RISE = 300;

/** How many times a second a laser beam swings at most, far under the three flashes of WCAG 2.3.1. */
export const BEAM_SWING = 0.5;

/** How long the spotlight takes to dim the page, in milliseconds. */
export const SPOT_RISE = 400;

/** The fewest pieces an effect makes at any scale. */
const FEWEST = 1;

const between = (low, high, value) => Math.max(low, Math.min(high, value));

/** A count of pieces at a scale: `count` times `scale`, rounded, one at least. */
export function scaled(count, scale = 1) {
    return Math.max(FEWEST, Math.round(count * scale));
}

/** Confetti falling from above the top of a box, across its width: as many as its area allows, between 40 and 200, at a scale. */
export function confetti(width, height, colors, random = Math.random, scale = 1) {
    const count = scaled(between(40, 200, Math.round((width * height) / 8000)), scale);

    return Array.from({ length: count }, (_, index) => ({
        shape: 'piece',
        x: random() * width,
        y: -random() * height * 0.3 - 12,
        vx: (random() - 0.5) * 0.2,
        vy: 0.08 + random() * 0.18,
        size: 6 + random() * 6,
        angle: random() * Math.PI * 2,
        spin: (random() - 0.5) * 0.02,
        color: colors[index % colors.length],
        delay: 0,
        life: Infinity,
    }));
}

/**
 * Bursts of fireworks in the upper half of a box, `BURST_GAP` apart, as many as end within `duration`, four at most;
 * each a ring of sparks of one colour, 36 at full scale.
 */
export function fireworks(width, height, colors, duration, random = Math.random, scale = 1) {
    const life = Math.min(BURST_LIFE, duration);
    const bursts = Math.max(1, Math.min(4, 1 + Math.floor((duration - life) / BURST_GAP)));
    const count = scaled(36, scale);
    const sparks = [];

    for (let burst = 0; burst < bursts; burst++) {
        const x = width * (0.2 + 0.6 * random());
        const y = height * (0.15 + 0.35 * random());

        for (let spark = 0; spark < count; spark++) {
            const direction = (Math.PI * 2 * spark) / count;
            const speed = 0.12 + random() * 0.14;

            sparks.push({
                shape: 'spark',
                x,
                y,
                vx: Math.cos(direction) * speed,
                vy: Math.sin(direction) * speed,
                size: 2 + random() * 1.5,
                angle: 0,
                spin: 0,
                color: colors[burst % colors.length],
                delay: burst * BURST_GAP,
                life,
            });
        }
    }

    return sparks;
}

/**
 * A laser show: beams rising from feet spread along the bottom of the box, in waves `BEAM_GAP` apart, each fading in
 * over `BEAM_RISE` and swinging slowly from side to side; thin lines of light, never a flash of the whole screen.
 */
export function lasers({ width, height, colors, duration, random = Math.random, scale = 1 }) {
    const count = scaled(between(3, 8, Math.round(width / 160)), scale);
    const waves = Math.max(1, Math.min(3, Math.floor(duration / (2 * BEAM_GAP))));

    return Array.from({ length: count }, (_, index) => {
        const delay = (index % waves) * BEAM_GAP;

        return {
            shape: 'beam',
            x: (width * (index + 0.5)) / count,
            y: height,
            vx: 0,
            vy: 0,
            size: 3 + random() * 2,
            length: Math.hypot(width, height),
            angle: 0,
            spin: 0,
            sweep: {
                base: (index / Math.max(1, count - 1) - 0.5) * 0.8,
                amplitude: 0.25 + random() * 0.25,
                speed: (Math.PI * 2 * BEAM_SWING * (0.6 + random() * 0.4)) / 1000,
                phase: random() * Math.PI * 2,
            },
            fadeIn: BEAM_RISE,
            color: colors[index % colors.length],
            delay,
            life: Math.max(BEAM_RISE, duration - delay),
        };
    });
}

/** Balloons rising from below the bottom of the box, swaying, each with its string; a click bursts one. */
export function balloons({ width, height, colors, random = Math.random, scale = 1 }) {
    const count = scaled(between(6, 18, Math.round(width / 90)), scale);

    return Array.from({ length: count }, (_, index) => {
        const size = 18 + random() * 10;

        return {
            shape: 'balloon',
            x: random() * width,
            y: height + size + random() * height * 0.3,
            vx: 0,
            vy: -(0.12 + random() * 0.08),
            size,
            angle: 0,
            spin: 0,
            sway: { amplitude: 6 + random() * 8, speed: 0.002, phase: random() * Math.PI * 2 },
            color: colors[index % colors.length],
            delay: 0,
            life: Infinity,
        };
    });
}

/** A few shooting stars crossing the upper part of the box in a diagonal, one after the other. */
export function shootingStars({ width, height, colors, random = Math.random, scale = 1 }) {
    const count = scaled(between(3, 6, Math.round(width / 300)), scale);

    return Array.from({ length: count }, (_, index) => ({
        shape: 'star',
        x: random() * width * 0.6,
        y: random() * height * 0.3,
        vx: 0.6 + random() * 0.3,
        vy: 0.25 + random() * 0.15,
        size: 2,
        length: 90,
        angle: 0,
        spin: 0,
        color: colors[index % colors.length],
        delay: index * 300,
        life: 900,
    }));
}

/** Streamers unrolling from the two bottom corners of the box towards its middle, then falling. */
export function streamers({ width, height, colors, random = Math.random, scale = 1 }) {
    const count = scaled(between(8, 20, Math.round(width / 100)), scale);

    return Array.from({ length: count }, (_, index) => {
        const side = 0 === index % 2 ? 1 : -1;

        return {
            shape: 'streamer',
            x: 1 === side ? 0 : width,
            y: height,
            vx: side * (0.25 + random() * 0.25),
            vy: -(0.5 + random() * 0.3),
            size: 3,
            length: 60 + random() * 40,
            angle: random() * Math.PI * 2,
            spin: 0.01,
            color: colors[index % colors.length],
            delay: Math.floor(index / 2) * 60,
            life: Infinity,
        };
    });
}

/** Coins falling from above the top of the box, spinning, bouncing on its bottom. */
export function coins({ width, height, colors, random = Math.random, scale = 1 }) {
    const count = scaled(between(15, 60, Math.round((width * height) / 20000)), scale);

    return Array.from({ length: count }, (_, index) => {
        const size = 8 + random() * 4;

        return {
            shape: 'coin',
            x: random() * width,
            y: -size - random() * height * 0.2,
            vx: (random() - 0.5) * 0.1,
            vy: 0.1 + random() * 0.2,
            size,
            angle: random() * Math.PI,
            spin: 0.01 + random() * 0.01,
            floor: height - size,
            bounce: 0.5,
            color: colors[index % colors.length],
            delay: random() * 600,
            life: Infinity,
        };
    });
}

/** Soap bubbles floating up from the bottom of the box, swaying, each bursting at the end of its own life. */
export function bubbles({ width, height, colors, duration, random = Math.random, scale = 1 }) {
    const count = scaled(between(10, 40, Math.round((width * height) / 30000)), scale);

    return Array.from({ length: count }, (_, index) => ({
        shape: 'bubble',
        x: random() * width,
        y: height + random() * height * 0.1,
        vx: 0,
        vy: -(0.04 + random() * 0.06),
        size: 8 + random() * 14,
        angle: 0,
        spin: 0,
        sway: { amplitude: 10 + random() * 10, speed: 0.0015, phase: random() * Math.PI * 2 },
        color: colors[index % colors.length],
        delay: random() * 400,
        life: Math.max(600, duration * (0.5 + random() * 0.5)),
    }));
}

/** An aurora: three slow bands of colour waving across the top of the box, a calm veil. */
export function aurora({ height, colors, random = Math.random }) {
    return Array.from({ length: 3 }, (_, index) => ({
        shape: 'veil',
        x: 0,
        y: height * (0.08 + 0.1 * index),
        vx: 0,
        vy: 0,
        size: height * 0.18,
        amplitude: 20 + random() * 20,
        wave: 0.004 + random() * 0.004,
        angle: random() * Math.PI * 2,
        spin: 0.0008,
        fadeIn: 600,
        color: colors[index % colors.length],
        delay: 0,
        life: Infinity,
    }));
}

/**
 * A spotlight: the page dimmed over `SPOT_RISE` around a disc of light on what it lights, `focus` (its centre and its
 * radius), or on the middle of the box.
 */
export function spotlight({ width, height, colors, focus }) {
    const lit = focus ?? { x: width / 2, y: height / 2, radius: Math.min(width, height) * 0.18 };

    return [
        {
            shape: 'spot',
            x: lit.x,
            y: lit.y,
            vx: 0,
            vy: 0,
            size: Math.max(24, lit.radius),
            angle: 0,
            spin: 0,
            fadeIn: SPOT_RISE,
            color: colors[0],
            delay: 0,
            life: Infinity,
        },
    ];
}

/** A trophy coming down from above the box to its place, then a burst of sparks when it lands. */
export function trophy({ width, height, colors, duration, random = Math.random, scale = 1, focus }) {
    const size = between(48, 120, Math.min(width, height) * 0.12);
    const travel = Math.min(900, duration * 0.4);
    const x = focus?.x ?? width / 2;
    const y = focus?.y ?? height * 0.4;
    const count = scaled(24, scale);
    const life = Math.max(1, Math.min(BURST_LIFE, duration - travel));
    const cup = {
        shape: 'trophy',
        x,
        y: -size,
        from: -size,
        to: y,
        travel,
        vx: 0,
        vy: 0,
        size,
        angle: 0,
        spin: 0,
        color: colors[0],
        delay: 0,
        life: Infinity,
    };
    const sparks = Array.from({ length: count }, (_, index) => {
        const direction = (Math.PI * 2 * index) / count;
        const speed = 0.1 + random() * 0.1;

        return {
            shape: 'spark',
            x,
            y: y - size / 2,
            vx: Math.cos(direction) * speed,
            vy: Math.sin(direction) * speed,
            size: 2,
            angle: 0,
            spin: 0,
            color: colors[(index + 1) % colors.length],
            delay: travel,
            life,
        };
    });

    return [cup, ...sparks];
}

/**
 * The pieces of an effect of `EFFECTS` for a box, in its colours, for a duration, at a scale; `focus`, the centre and the
 * radius of what the spotlight lights and where the trophy lands.
 */
export function pieces(name, { width, height, colors, duration, random = Math.random, scale = 1, focus }) {
    const box = { width, height, colors, duration, random, scale, focus };

    switch (name) {
        case 'fireworks':
            return fireworks(width, height, colors, duration, random, scale);
        case 'lasers':
            return lasers(box);
        case 'balloons':
            return balloons(box);
        case 'shooting-stars':
            return shootingStars(box);
        case 'streamers':
            return streamers(box);
        case 'coins':
            return coins(box);
        case 'bubbles':
            return bubbles(box);
        case 'aurora':
            return aurora(box);
        case 'spotlight':
            return spotlight(box);
        case 'trophy':
            return trophy(box);
        default:
            return confetti(width, height, colors, random, scale);
    }
}
