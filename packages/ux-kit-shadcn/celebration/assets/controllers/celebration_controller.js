import { Controller } from '@hotwired/stimulus';

/** The DOM event that plays the celebrations of the page, dispatched on any of its elements or on the window. */
export const EVENT = 'celebrate';

/** The effects drawn, the first by default. */
export const EFFECTS = ['confetti', 'fireworks'];

/** The run of an animation when `duration` is no number, in milliseconds. */
export const DURATION = 2500;

/** The shortest run of an animation, in milliseconds. */
export const SHORTEST = 600;

/**
 * The longest run of an animation, in milliseconds, whatever `duration` asks: under the five seconds beyond which
 * WCAG 2.2.2 (Pause, Stop, Hide) asks for a way to stop a motion that starts by itself.
 */
export const LONGEST = 4000;

/** The fewest milliseconds between two bursts of fireworks: two a second at most, under the three flashes of WCAG 2.3.1. */
export const BURST_GAP = 500;

/** How long a burst of fireworks shines, in milliseconds, before it has faded. */
export const BURST_LIFE = 1200;

/** How long the live region stays empty before it says the message, so that the same words are said again. */
export const ANNOUNCE_DELAY = 100;

/** What the controller sets on its canvas through the CSSOM, whatever the stylesheet: over the page, never in the way. */
export const STAGE = { position: 'fixed', inset: '0', width: '100%', height: '100%', 'pointer-events': 'none' };

/** The pull of each effect, in pixels per square millisecond, and the share of their speed the pieces keep per millisecond. */
const PHYSICS = { confetti: { gravity: 0.00012, drag: 0.0006 }, fireworks: { gravity: 0.00008, drag: 0.0018 } };

/** The controller whose animation runs, if any: one animation at a time on the page. */
let current = null;

/** A duration in milliseconds, kept between `SHORTEST` and `LONGEST`; `DURATION` when it is no number. */
export function bounded(duration) {
    const milliseconds = Number(duration);

    return Number.isFinite(milliseconds) ? Math.min(LONGEST, Math.max(SHORTEST, Math.round(milliseconds))) : DURATION;
}

/** An effect of `EFFECTS`, the first one for any other name. */
export function effect(name) {
    return EFFECTS.includes(name) ? name : EFFECTS[0];
}

/** The names of custom properties in a list separated by spaces, anything else left out. */
export function tokens(list) {
    return String(list ?? '')
        .split(/\s+/)
        .filter((token) => /^--[A-Za-z0-9_-]+$/.test(token));
}

/**
 * The message and the effect an event asks for: the params of a Stimulus action first, then the `detail` of a DOM
 * event; an empty or missing text is left undefined, for the values of the controller to stand in.
 */
export function optionsOf(event) {
    const detail = null !== event?.detail && 'object' === typeof event?.detail ? event.detail : {};
    const params = null !== event?.params && 'object' === typeof event?.params ? event.params : {};
    const text = (value) => ('string' === typeof value && '' !== value ? value : undefined);

    return { message: text(params.message) ?? text(detail.message), effect: text(params.effect) ?? text(detail.effect) };
}

/** Confetti falling from above the top of a box, across its width: as many as its area allows, between 40 and 200. */
export function confetti(width, height, colors, random = Math.random) {
    const count = Math.max(40, Math.min(200, Math.round((width * height) / 8000)));

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
 * each a ring of sparks of one colour.
 */
export function fireworks(width, height, colors, duration, random = Math.random) {
    const life = Math.min(BURST_LIFE, duration);
    const bursts = Math.max(1, Math.min(4, 1 + Math.floor((duration - life) / BURST_GAP)));
    const sparks = [];

    for (let burst = 0; burst < bursts; burst++) {
        const x = width * (0.2 + 0.6 * random());
        const y = height * (0.15 + 0.35 * random());

        for (let spark = 0; spark < 36; spark++) {
            const direction = (Math.PI * 2 * spark) / 36;
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

/** Moves the pieces that have started by `step` milliseconds, at `time` into the animation. */
export function advance(pieces, time, step, { gravity, drag }) {
    const kept = Math.max(0, 1 - drag * step);

    for (const piece of pieces) {
        if (time < piece.delay) {
            continue;
        }

        piece.vx *= kept;
        piece.vy = piece.vy * kept + gravity * step;
        piece.x += piece.vx * step;
        piece.y += piece.vy * step;
        piece.angle += piece.spin * step;
    }
}

/** The opacity of a piece at `time`: whole, then fading over the last 30 % of the animation or of its own life. */
export function opacity(piece, time, duration) {
    const fade = (left, length) => Math.max(0, Math.min(1, left / (length * 0.3)));
    const own = Number.isFinite(piece.life) ? fade(piece.life - (time - piece.delay), piece.life) : 1;

    return time < piece.delay ? 0 : Math.min(own, fade(duration - time, duration));
}

/** Draws the pieces at `time` on a 2D context, cleared first. */
export function draw(context, pieces, time, duration, width, height) {
    context.clearRect(0, 0, width, height);

    for (const piece of pieces) {
        const alpha = opacity(piece, time, duration);

        if (0 === alpha) {
            continue;
        }

        context.globalAlpha = alpha;
        context.fillStyle = piece.color;

        if ('spark' === piece.shape) {
            context.beginPath();
            context.arc(piece.x, piece.y, piece.size, 0, Math.PI * 2);
            context.fill();
        } else {
            context.save();
            context.translate(piece.x, piece.y);
            context.rotate(piece.angle);
            context.fillRect(-piece.size / 2, -piece.size / 4, piece.size, piece.size / 2);
            context.restore();
        }
    }

    context.globalAlpha = 1;
}

/*
 * A celebration: says its message in its live region, then draws confetti or fireworks over the page on a canvas, in
 * the colours of the tokens it names, read through the CSSOM so that they follow the theme of the site.
 *
 * It plays with `autoplay` as soon as it connects, on the DOM event `celebrate` dispatched anywhere on the page, and by
 * the action `celebration#play`; the `message` and `effect` params of an action, or the `detail` of the event, stand in
 * for its values. Where motion is reduced, by the system or by `data-motion="reduce"` on an ancestor, it says its message
 * and draws nothing. One animation runs at a time on the page; a short one, `LONGEST` at most, that holds while the
 * document is hidden. The canvas is cloned from the `stage` template, set over the page and kept out of the way of the
 * pointer through the CSSOM, which a strict Content Security Policy allows, and removed with its bitmap at the end, or
 * at once when the element leaves the page.
 *
 * @value message The words said when it plays.
 * @value effect `confetti` or `fireworks`.
 * @value autoplay Whether it plays as soon as it connects.
 * @value duration The run of the animation in milliseconds, kept between `SHORTEST` and `LONGEST`.
 * @value colors The custom properties whose colours it draws in, separated by spaces.
 * @target message The live region that says the message.
 * @target stage The template of the canvas.
 */
export default class extends Controller {
    static targets = ['message', 'stage'];

    static values = {
        message: String,
        effect: { type: String, default: 'confetti' },
        autoplay: Boolean,
        duration: { type: Number, default: DURATION },
        colors: String,
    };

    connect() {
        this.request = null;
        this.announcing = null;
        this.stage = null;
        this.pieces = null;
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.onEvent = (event) => this.play(event);
        this.onVisibility = () => this.visibility();
        window.addEventListener(EVENT, this.onEvent);
        document.addEventListener('visibilitychange', this.onVisibility);

        if (this.autoplayValue) {
            this.play();
        }
    }

    disconnect() {
        this.stop();
        clearTimeout(this.announcing);
        this.announcing = null;
        window.removeEventListener(EVENT, this.onEvent);
        document.removeEventListener('visibilitychange', this.onVisibility);
    }

    /** The action, and the listener of `celebrate`: says the message, then draws, unless motion is reduced. */
    play(event) {
        const options = optionsOf(event);
        this.announce(options.message ?? this.messageValue);

        if (this.reduced() || null !== current) {
            return;
        }

        this.start(effect(options.effect ?? this.effectValue));
    }

    /** Ends the animation now: the canvas and its bitmap go, and another celebration may play. */
    stop() {
        if (null !== this.request) {
            cancelAnimationFrame(this.request);
            this.request = null;
        }

        this.pieces = null;
        this.brush = null;

        if (null !== this.stage) {
            this.stage.width = 0;
            this.stage.height = 0;
            this.stage.remove();
            this.stage = null;
            this.dispatch('end');
        }

        if (this === current) {
            current = null;
        }
    }

    /** Whether motion is reduced, by the system or by `data-motion="reduce"` on the element or an ancestor. */
    reduced() {
        return this.motion.matches || null !== this.element.closest('[data-motion="reduce"]');
    }

    announce(message) {
        if (!this.hasMessageTarget || '' === message) {
            return;
        }

        clearTimeout(this.announcing);
        this.messageTarget.textContent = '';
        this.announcing = setTimeout(() => {
            this.announcing = null;
            this.messageTarget.textContent = message;
        }, ANNOUNCE_DELAY);
    }

    start(name) {
        const stage = this.canvas();
        const context = stage.getContext('2d');

        if (null === context) {
            stage.remove();

            return;
        }

        current = this;
        this.stage = stage;
        this.brush = context;
        this.effect = name;
        this.duration = bounded(this.durationValue);
        this.width = document.documentElement.clientWidth;
        this.height = document.documentElement.clientHeight;

        const ratio = Math.min(2, window.devicePixelRatio || 1);
        stage.width = Math.round(this.width * ratio);
        stage.height = Math.round(this.height * ratio);
        context.scale(ratio, ratio);

        const colors = this.colors(stage);
        this.pieces = 'fireworks' === name ? fireworks(this.width, this.height, colors, this.duration) : confetti(this.width, this.height, colors);
        this.elapsed = 0;
        this.last = null;
        this.dispatch('start', { detail: { effect: name } });

        if (!document.hidden) {
            this.schedule();
        }
    }

    /** The canvas, cloned from the `stage` template or made anew, set over the page and added to the element. */
    canvas() {
        const model = this.hasStageTarget ? this.stageTarget.content.firstElementChild : null;
        const stage = null === model ? document.createElement('canvas') : model.cloneNode(true);
        stage.setAttribute('aria-hidden', 'true');

        for (const [property, value] of Object.entries(STAGE)) {
            stage.style.setProperty(property, value);
        }

        this.element.append(stage);

        return stage;
    }

    /**
     * The colours of the tokens, each resolved on the canvas through the CSSOM, `light-dark()` and every `var()`
     * included; the colour of the text where none resolves.
     */
    colors(stage) {
        const found = [];

        for (const token of tokens(this.colorsValue)) {
            stage.style.setProperty('color', `var(${token})`);
            const colour = getComputedStyle(stage).color;

            if (colour && !found.includes(colour)) {
                found.push(colour);
            }
        }

        stage.style.removeProperty('color');

        return found.length > 0 ? found : [getComputedStyle(this.element).color || 'black'];
    }

    schedule() {
        this.request = requestAnimationFrame((now) => this.tick(now));
    }

    tick(now) {
        this.request = null;
        const step = null === this.last ? 16 : Math.min(64, Math.max(0, now - this.last));
        this.last = now;
        this.elapsed += step;

        if (this.reduced() || this.elapsed >= this.duration) {
            this.stop();

            return;
        }

        advance(this.pieces, this.elapsed, step, PHYSICS[this.effect]);
        draw(this.brush, this.pieces, this.elapsed, this.duration, this.width, this.height);
        this.schedule();
    }

    /** Holds the animation while the document is hidden, and takes it up where it was when it shows again. */
    visibility() {
        if (null === this.pieces) {
            return;
        }

        if (document.hidden) {
            if (null !== this.request) {
                cancelAnimationFrame(this.request);
                this.request = null;
            }

            this.last = null;
        } else if (null === this.request) {
            this.schedule();
        }
    }
}
