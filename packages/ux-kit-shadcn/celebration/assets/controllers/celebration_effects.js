/*
 * The effects of the celebrations: each makes the pieces the painter of `celebration_worker.js` moves and draws, for a
 * box of a width and a height, in the colours of the theme, as many as the scale allows, for a duration.
 */

/** The effects drawn, the first by default. */
export const EFFECTS = [
    'confetti',
    'fireworks',
    'lasers',
    'balloons',
    'shooting-stars',
    'streamers',
    'coins',
    'bubbles',
    'aurora',
    'spotlight',
    'trophy',
    'wave',
    'rocket',
    'rainbow',
    'drum-roll',
    'applause',
    'reactions',
    'shapes',
    'mascot',
];

/** The effects that move the elements of the page rather than draw on a canvas. */
export const DOM_EFFECTS = ['wave'];

/** The reaction that rains when none is given. */
export const REACTION = '🎉';

/** The most characters of a reaction that rains, an emoji or a short word. */
export const REACTION_LENGTH = 8;

/** The share of the run of a drum roll before what it reveals: the rest bursts into sparks. */
export const ROLL_SHARE = 0.7;

/** How many times a second a pair of hands claps at most, a motion and never a flash. */
export const CLAP_RATE = 2.5;

/** How many times a second a stick of the drum roll strikes, a motion and never a flash. */
export const STRIKE_RATE = 8;

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

/** How far above the bottom of the box the mascot stands, in pixels. */
const FEET = 8;

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

/** A reaction kept to `REACTION_LENGTH` characters, whole ones, without spaces at its ends; `REACTION` when empty. */
export function reaction(text) {
    const kept = Array.from(String(text ?? '').trim())
        .slice(0, REACTION_LENGTH)
        .join('')
        .trim();

    return '' === kept ? REACTION : kept;
}

/** How long a drum roll lasts before what it reveals, in milliseconds, for the run of the celebration. */
export function rollOf(duration) {
    return Math.round(duration * ROLL_SHARE);
}

/**
 * A rocket taking off from the bottom of the box, or from under what `focus` names, a progress bar at 100 % as a rule,
 * faster and faster up and out by the top, a trail of sparks behind it.
 */
export function rocket({ width, height, colors, duration, random = Math.random, scale = 1, focus }) {
    const size = between(24, 48, Math.min(width, height) * 0.06);
    const x = focus?.x ?? width / 2;
    const from = Math.min(height + size, (focus?.y ?? height) + size);
    const to = -size * 3;
    const travel = Math.max(400, duration * 0.8);
    const count = scaled(30, scale);
    const body = {
        shape: 'rocket',
        x,
        y: from,
        from,
        to,
        travel,
        ease: 'in',
        vx: 0,
        vy: 0,
        size,
        angle: 0,
        spin: 0,
        color: colors[0],
        delay: 0,
        life: Infinity,
    };
    const trail = Array.from({ length: count }, (_, index) => {
        const delay = (travel * index) / count;
        const done = delay / travel;

        return {
            shape: 'spark',
            x: x + (random() - 0.5) * size * 0.3,
            y: from + (to - from) * done ** 3 + size,
            vx: (random() - 0.5) * 0.05,
            vy: 0.04 + random() * 0.06,
            size: 1.5 + random() * 2,
            angle: 0,
            spin: 0,
            color: colors[(index % Math.max(1, colors.length - 1)) + (colors.length > 1 ? 1 : 0)],
            delay,
            life: 500,
        };
    });

    return [body, ...trail];
}

/** A rainbow drawn from one edge of the box to the other, one band of each colour after the other, around its bottom. */
export function rainbow({ width, height, colors, duration }) {
    const radius = Math.min(width * 0.45, height * 0.8);
    const band = between(6, 18, radius / 14);
    const bands = Math.max(3, colors.length);
    const draw = Math.max(300, duration * 0.5);

    return Array.from({ length: bands }, (_, index) => ({
        shape: 'arc',
        x: width / 2,
        y: height,
        vx: 0,
        vy: 0,
        size: band,
        radius: radius - index * band,
        draw,
        angle: 0,
        spin: 0,
        color: colors[index % colors.length],
        delay: index * 80,
        life: Infinity,
    }));
}

/**
 * A drum roll: a drum under what `focus` names, or at the bottom of the box, its two sticks striking in turn for
 * `rollOf(duration)`, then gone, and a burst of sparks on what is revealed when the roll ends.
 */
export function drumRoll({ width, height, colors, duration, random = Math.random, scale = 1, focus }) {
    const roll = rollOf(duration);
    const size = between(40, 90, Math.min(width, height) * 0.1);
    const x = focus?.x ?? width / 2;
    const y = Math.min(height - size, (focus?.y ?? height * 0.4) + (focus?.radius ?? 0) + size);
    const count = scaled(32, scale);
    const drum = {
        shape: 'drum',
        x,
        y,
        vx: 0,
        vy: 0,
        size,
        strike: STRIKE_RATE,
        angle: 0,
        spin: 0,
        color: colors[0],
        delay: 0,
        life: roll + 200,
    };
    const life = Math.max(1, Math.min(BURST_LIFE, duration - roll));
    const sparks = Array.from({ length: count }, (_, index) => {
        const direction = (Math.PI * 2 * index) / count;
        const speed = 0.1 + random() * 0.12;

        return {
            shape: 'spark',
            x,
            y: focus?.y ?? height * 0.4,
            vx: Math.cos(direction) * speed,
            vy: Math.sin(direction) * speed,
            size: 2 + random(),
            angle: 0,
            spin: 0,
            color: colors[(index % Math.max(1, colors.length - 1)) + (colors.length > 1 ? 1 : 0)],
            delay: roll,
            life,
        };
    });

    return [drum, ...sparks];
}

/** Pairs of hands clapping along the bottom of the box, rising a little, each at its own pace, `CLAP_RATE` at most. */
export function applause({ width, height, colors, random = Math.random, scale = 1 }) {
    const count = scaled(between(3, 8, Math.round(width / 160)), scale);

    return Array.from({ length: count }, (_, index) => {
        const size = 22 + random() * 10;

        return {
            shape: 'hands',
            x: (width * (index + 0.5)) / count + (random() - 0.5) * 30,
            y: height + size * 2,
            vx: 0,
            vy: -(0.06 + random() * 0.04),
            size,
            clap: CLAP_RATE * (0.7 + random() * 0.3),
            phase: random() * Math.PI * 2,
            angle: (random() - 0.5) * 0.4,
            spin: 0,
            color: colors[index % colors.length],
            delay: random() * 300,
            life: Infinity,
        };
    });
}

/** A reaction raining from above the top of the box, an emoji or a short word, swaying as it falls. */
export function reactions({ width, height, colors, random = Math.random, scale = 1, reaction: text }) {
    const count = scaled(between(15, 50, Math.round((width * height) / 25000)), scale);
    const glyph = reaction(text);

    return Array.from({ length: count }, (_, index) => ({
        shape: 'glyph',
        text: glyph,
        x: random() * width,
        y: -random() * height * 0.4 - 32,
        vx: 0,
        vy: 0.08 + random() * 0.12,
        size: 22 + random() * 16,
        angle: (random() - 0.5) * 0.6,
        spin: (random() - 0.5) * 0.002,
        sway: { amplitude: 8 + random() * 10, speed: 0.002, phase: random() * Math.PI * 2 },
        color: colors[index % colors.length],
        delay: random() * 500,
        life: Infinity,
    }));
}

/**
 * Confetti in the shapes of the site, the images it gives, a logo or a mascot, falling and turning; strips of paper
 * when it gives none.
 */
export function shapes({ width, height, colors, random = Math.random, scale = 1, images = 0 }) {
    if (images < 1) {
        return confetti(width, height, colors, random, scale);
    }

    const count = scaled(between(20, 80, Math.round((width * height) / 15000)), scale);

    return Array.from({ length: count }, (_, index) => ({
        shape: 'image',
        image: index % images,
        x: random() * width,
        y: -random() * height * 0.3 - 40,
        vx: (random() - 0.5) * 0.2,
        vy: 0.08 + random() * 0.16,
        size: 20 + random() * 16,
        angle: random() * Math.PI * 2,
        spin: (random() - 0.5) * 0.004,
        color: colors[index % colors.length],
        delay: 0,
        life: Infinity,
    }));
}

/**
 * The mascot of the site, its image, or a little figure in a pointed hat without one, hopping across the bottom of the
 * box from the side where reading starts to the other.
 */
export function mascot({ width, height, colors, duration, images = 0, direction = 'ltr' }) {
    const size = between(48, 120, Math.round(Math.min(width, height) * 0.14));
    const start = -size;
    const end = width + size;
    const rtl = 'rtl' === direction;

    return [
        {
            shape: 'sprite',
            image: images > 0 ? 0 : undefined,
            x: rtl ? end : start,
            y: height - FEET,
            cross: { from: rtl ? end : start, to: rtl ? start : end, floor: height - FEET, hop: size * 0.4, hops: 6 },
            travel: duration,
            vx: 0,
            vy: 0,
            size,
            angle: 0,
            spin: 0,
            color: colors[0],
            delay: 0,
            life: Infinity,
        },
    ];
}

/**
 * The pieces of an effect of `EFFECTS` for a box, in its colours, for a duration, at a scale; `focus`, the centre and the
 * radius of what the spotlight lights, where the trophy lands, the rocket takes off and the drum rolls; `reaction`, what
 * rains; `images`, how many images of the site the shapes and the mascot have; `direction`, that of the text. An effect
 * of `DOM_EFFECTS` draws nothing.
 */
export function pieces(name, { width, height, colors, duration, random = Math.random, scale = 1, focus, reaction: text, images = 0, direction = 'ltr' }) {
    const box = { width, height, colors, duration, random, scale, focus, reaction: text, images, direction };

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
        case 'wave':
            return [];
        case 'rocket':
            return rocket(box);
        case 'rainbow':
            return rainbow(box);
        case 'drum-roll':
            return drumRoll(box);
        case 'applause':
            return applause(box);
        case 'reactions':
            return reactions(box);
        case 'shapes':
            return shapes(box);
        case 'mascot':
            return mascot(box);
        default:
            return confetti(width, height, colors, random, scale);
    }
}
