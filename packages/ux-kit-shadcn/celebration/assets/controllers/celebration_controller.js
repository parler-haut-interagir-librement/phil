import { Controller } from '@hotwired/stimulus';
import { EFFECTS, pieces as piecesOf } from './celebration_effects.js';
import { Painter, SOURCE } from './celebration_worker.js';

export { BEAM_GAP, BEAM_RISE, BEAM_SWING, BURST_GAP, BURST_LIFE, confetti, EFFECTS, fireworks, pieces } from './celebration_effects.js';
export { advance, BEAM_ALPHA, draw, opacity } from './celebration_worker.js';

/** The DOM event that plays the celebrations of the page, dispatched on any of its elements or on the window. */
export const EVENT = 'celebrate';

/** The action of a `<turbo-stream>` that plays the celebrations of the page, which `celebrate()` writes in Twig. */
export const STREAM_ACTION = 'celebrate';

/** The run of an animation when `duration` is no number, in milliseconds. */
export const DURATION = 2500;

/** The shortest run of an animation, in milliseconds. */
export const SHORTEST = 600;

/**
 * The longest run of an animation, in milliseconds, whatever `duration` asks: under the five seconds beyond which
 * WCAG 2.2.2 (Pause, Stop, Hide) asks for a way to stop a motion that starts by itself.
 */
export const LONGEST = 4000;

/** How long the live region stays empty before it says the message, so that the same words are said again. */
export const ANNOUNCE_DELAY = 100;

/** What the controller sets on its canvas through the CSSOM, whatever the stylesheet: over the page, never in the way. */
export const STAGE = { position: 'fixed', inset: '0', width: '100%', height: '100%', 'pointer-events': 'none' };

/**
 * The choices of a person, read from `data-celebrations` on the element or an ancestor, the root as a rule: every
 * celebration in full, discreet ones, or none drawn; the first by default.
 */
export const PREFERENCES = ['all', 'discreet', 'none'];

/** How much of a celebration a discreet one keeps: a share of its pieces and of its run. */
export const DISCREET = { scale: 0.3, duration: 0.6 };

/** How many celebrations play in full in a session of the browser tab before the next ones get more discreet. */
export const FATIGUE = 3;

/** The key of the session storage of the browser under which the celebrations of the session are counted. */
export const STORAGE_KEY = 'phil-celebrations';

/** The fewest of its pieces an effect keeps, whatever the device and the fatigue. */
export const LOWEST_SCALE = 0.2;

/** The effects in which a click bursts what floats. */
export const POPPING = ['balloons', 'bubbles'];

/** The attributes of the root whose change may change the colours of the theme. */
export const THEME_ATTRIBUTES = ['class', 'data-theme', 'style'];

/** The controller whose animation runs, if any: one animation at a time on the page. */
let current = null;

/** The celebrations of the page counted when the session storage cannot be read. */
let played = 0;

/** The controllers connected, which keep the listener of the Turbo Streams while one at least is on the page. */
const connected = new Set();

/** The `<turbo-stream>` elements already played, so that none plays twice. */
const performed = new WeakSet();

/** A duration in milliseconds, kept between `SHORTEST` and `LONGEST`; `DURATION` when it is no number. */
export function bounded(duration) {
    const milliseconds = Number(duration);

    return Number.isFinite(milliseconds) ? Math.min(LONGEST, Math.max(SHORTEST, Math.round(milliseconds))) : DURATION;
}

/** An effect of `EFFECTS`, the first one for any other name. */
export function effect(name) {
    return EFFECTS.includes(name) ? name : EFFECTS[0];
}

/** A choice of `PREFERENCES`, the first one for any other value. */
export function preference(value) {
    return PREFERENCES.includes(value) ? value : PREFERENCES[0];
}

/** The names of custom properties in a list separated by spaces, anything else left out. */
export function tokens(list) {
    return String(list ?? '')
        .split(/\s+/)
        .filter((token) => /^--[A-Za-z0-9_-]+$/.test(token));
}

/**
 * The message, the effect and the focus an event asks for: the params of a Stimulus action first, then the `detail`
 * of a DOM event; an empty or missing text is left undefined, for the values of the controller to stand in. The focus
 * is a selector, or an element of the `detail`.
 */
export function optionsOf(event) {
    const detail = null !== event?.detail && 'object' === typeof event?.detail ? event.detail : {};
    const params = null !== event?.params && 'object' === typeof event?.params ? event.params : {};
    const text = (value) => ('string' === typeof value && '' !== value ? value : undefined);
    const element = (value) => (null !== value && 'object' === typeof value && 'function' === typeof value.getBoundingClientRect ? value : undefined);

    return {
        message: text(params.message) ?? text(detail.message),
        effect: text(params.effect) ?? text(detail.effect),
        focus: text(params.focus) ?? element(detail.focus) ?? text(detail.focus),
    };
}

/**
 * How much of a celebration plays for a choice and the celebrations already played in the session: a share of its
 * pieces and of its run; null when none is drawn. In full for the first `FATIGUE`, half as many pieces for the next
 * `FATIGUE`, then as discreet as a discreet one.
 */
export function intensity(choice, count) {
    switch (preference(choice)) {
        case 'none':
            return null;
        case 'discreet':
            return DISCREET;
        default:
            if (count < FATIGUE) {
                return { scale: 1, duration: 1 };
            }

            return count < 2 * FATIGUE ? { scale: 0.5, duration: 0.8 } : DISCREET;
    }
}

/**
 * The share of its pieces an effect draws on a device, from what its `navigator` tells: fewer on two or four cores, on
 * a memory of one or two gigabytes, and when the reader asks to save data; `LOWEST_SCALE` at least.
 */
export function budget(device) {
    const cores = Number(device?.hardwareConcurrency);
    const memory = Number(device?.deviceMemory);
    let scale = 1;

    if (Number.isFinite(cores) && cores > 0 && cores <= 4) {
        scale *= cores <= 2 ? 0.4 : 0.7;
    }

    if (Number.isFinite(memory) && memory > 0 && memory <= 2) {
        scale *= memory <= 1 ? 0.5 : 0.7;
    }

    if (true === device?.connection?.saveData) {
        scale *= 0.5;
    }

    return Math.max(LOWEST_SCALE, Math.round(scale * 100) / 100);
}

/**
 * The words said this time among `messages`, texts that are not empty: another one than `last` when there are several,
 * at random, so that a reader does not hear the same words each time; undefined when there is none.
 */
export function varied(messages, last, random = Math.random) {
    const texts = (Array.isArray(messages) ? messages : []).filter((message) => 'string' === typeof message && '' !== message);
    const others = texts.length > 1 ? texts.filter((message) => message !== last) : texts;

    return 0 === others.length ? undefined : others[Math.min(others.length - 1, Math.floor(random() * others.length))];
}

/**
 * The centre and the radius of what a celebration lights, an element or a selector of the page, from the box it
 * takes on the screen; undefined when nothing is found, or when the selector is not one.
 */
export function focusOf(target, root = globalThis.document) {
    let element = target;

    if ('string' === typeof target) {
        try {
            element = root?.querySelector?.(target) ?? null;
        } catch {
            return undefined;
        }
    }

    if (null === element || 'object' !== typeof element || 'function' !== typeof element.getBoundingClientRect) {
        return undefined;
    }

    const box = element.getBoundingClientRect();
    const width = box.right - box.left;
    const height = box.bottom - box.top;

    return { x: box.left + width / 2, y: box.top + height / 2, radius: Math.max(width, height) / 2 + 16 };
}

/** The session storage of a window, or null where it cannot be read: a private window, blocked site data. */
export function storageOf(scope) {
    try {
        return scope?.sessionStorage ?? null;
    } catch {
        return null;
    }
}

/** How many celebrations have played in the session of the tab, counted in its storage, or by the page without one. */
export function seen(storage) {
    try {
        const count = Number.parseInt(storage.getItem(STORAGE_KEY) ?? '0', 10);

        return Number.isFinite(count) && count >= 0 ? count : 0;
    } catch {
        return played;
    }
}

/** Counts one more celebration in the session of the tab, and on the page. */
export function remember(storage) {
    const count = seen(storage) + 1;
    played = count;

    try {
        storage.setItem(STORAGE_KEY, String(count));
    } catch {
        // Without storage, the page counts alone.
    }
}

/** What a `<turbo-stream action="celebrate">` asks: its message, its effect and its focus, from its attributes. */
export function streamOptions(stream) {
    const text = (name) => {
        const value = stream?.getAttribute?.(name);

        return 'string' === typeof value && '' !== value ? value : undefined;
    };

    return { message: text('message'), effect: text('effect'), focus: text('focus') };
}

/** Plays a stream once: the DOM event `celebrate` on the window, with what the stream asks. */
export function perform(stream) {
    if (performed.has(stream)) {
        return;
    }

    performed.add(stream);
    window.dispatchEvent(new CustomEvent(EVENT, { detail: streamOptions(stream) }));
}

/** The listener of `turbo:before-stream-render`: the action `celebrate` rendered as the DOM event `celebrate`. */
export function onStream(event) {
    const stream = event?.detail?.newStream;

    if (STREAM_ACTION === stream?.getAttribute?.('action')) {
        event.detail.render = (element) => perform(element);
    }
}

/*
 * A celebration: says its message in its live region, then draws an effect over the page on a canvas, in the colours of
 * the tokens it names, read through the CSSOM so that they follow the theme of the site, and read again when the theme
 * or the colour scheme changes while it plays.
 *
 * It plays with `autoplay` as soon as it connects, on the DOM event `celebrate` dispatched anywhere on the page, on a
 * `<turbo-stream action="celebrate">`, and by the action `celebration#play`; the params of an action, the `detail` of the
 * event or the attributes of the stream stand in for its values. Where motion is reduced, by the system or by
 * `data-motion="reduce"` on an ancestor, or where the person chose `data-celebrations="none"`, it says its message and
 * draws nothing; `discreet` draws less, and so does each celebration after the first `FATIGUE` of the session, and a
 * device with few cores, little memory or saving data. One animation runs at a time on the page; a short one, `LONGEST`
 * at most, that holds while the document is hidden. Where the browser allows it, the canvas is handed to a worker as an
 * `OffscreenCanvas` and painted off the main thread; otherwise it is painted on the page with the same code. The canvas
 * is cloned from the `stage` template, set over the page and kept out of the way of the pointer through the CSSOM, which
 * a strict Content Security Policy allows, and removed with its bitmap at the end, or at once when the element leaves the
 * page.
 *
 * @value message The words said when it plays.
 * @value messages Words said in turn when no message is given, one at random each time, another than the last.
 * @value effect An effect of `EFFECTS`.
 * @value autoplay Whether it plays as soon as it connects.
 * @value duration The run of the animation in milliseconds, kept between `SHORTEST` and `LONGEST`.
 * @value colors The custom properties whose colours it draws in, separated by spaces.
 * @value focus The selector of what the spotlight lights and where the trophy lands.
 * @target message The live region that says the message.
 * @target stage The template of the canvas.
 */
export default class extends Controller {
    static targets = ['message', 'stage'];

    static values = {
        message: String,
        messages: Array,
        effect: { type: String, default: 'confetti' },
        autoplay: Boolean,
        duration: { type: Number, default: DURATION },
        colors: String,
        focus: String,
    };

    connect() {
        this.announcing = null;
        this.stage = null;
        this.pieces = null;
        this.painter = null;
        this.worker = null;
        this.observer = null;
        this.colours = [];
        this.said = undefined;
        this.playing = 0;
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.scheme = window.matchMedia('(prefers-color-scheme: dark)');
        this.onEvent = (event) => this.play(event);
        this.onVisibility = () => this.visibility();
        this.onMotion = () => this.reduced() && this.stop();
        this.onScheme = () => this.recolor();
        this.onPointer = (event) => this.pop(event);
        window.addEventListener(EVENT, this.onEvent);
        document.addEventListener('visibilitychange', this.onVisibility);
        this.motion.addEventListener?.('change', this.onMotion);

        if (0 === connected.size) {
            document.addEventListener('turbo:before-stream-render', onStream);
        }

        connected.add(this);
        this.streams();

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
        this.motion.removeEventListener?.('change', this.onMotion);
        connected.delete(this);

        if (0 === connected.size) {
            document.removeEventListener('turbo:before-stream-render', onStream);
        }
    }

    /** The action, and the listener of `celebrate`: says the message, then draws, unless motion is reduced or refused. */
    play(event) {
        const options = optionsOf(event);
        this.announce(options.message ?? this.words());

        if (this.reduced() || null !== current) {
            return;
        }

        const storage = storageOf(window);
        const level = intensity(this.preference(), seen(storage));

        if (null === level) {
            return;
        }

        remember(storage);
        this.start(effect(options.effect ?? this.effectValue), level, options.focus ?? (this.focusValue || undefined));
    }

    /** Ends the animation now: the canvas and its bitmap go, and another celebration may play. */
    stop() {
        this.painter?.stop(false);
        this.painter = null;
        this.pieces = null;
        this.observer?.disconnect();
        this.observer = null;
        this.scheme.removeEventListener?.('change', this.onScheme);
        window.removeEventListener('pointerdown', this.onPointer);

        if (null !== this.worker) {
            // The worker goes with the bitmap of the canvas it was handed, which the page no longer sizes.
            this.worker.terminate();
        } else if (null !== this.stage) {
            this.stage.width = 0;
            this.stage.height = 0;
        }

        this.worker = null;

        if (null !== this.stage) {
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

    /** The choice of the person, from `data-celebrations` on the element or the nearest ancestor that has it. */
    preference() {
        return preference(this.element.closest('[data-celebrations]')?.getAttribute('data-celebrations'));
    }

    /** The words of this time: the message, or else one of the messages, another than the last. */
    words() {
        if ('' !== this.messageValue || 0 === (this.messagesValue ?? []).length) {
            return this.messageValue;
        }

        this.said = varied(this.messagesValue, this.said);

        return this.said ?? '';
    }

    announce(message) {
        if (!this.hasMessageTarget || '' === message || undefined === message) {
            return;
        }

        clearTimeout(this.announcing);
        this.messageTarget.textContent = '';
        this.announcing = setTimeout(() => {
            this.announcing = null;
            this.messageTarget.textContent = message;
        }, ANNOUNCE_DELAY);
    }

    /** Plays the `<turbo-stream action="celebrate">` already in the page when Turbo is not there to, and removes them. */
    streams() {
        if (undefined !== globalThis.customElements?.get?.('turbo-stream')) {
            return;
        }

        for (const stream of document.querySelectorAll?.(`turbo-stream[action="${STREAM_ACTION}"]`) ?? []) {
            perform(stream);
            stream.remove();
        }
    }

    start(name, level, focus) {
        const stage = this.canvas();
        const width = document.documentElement.clientWidth;
        const height = document.documentElement.clientHeight;
        const ratio = Math.min(2, window.devicePixelRatio || 1);
        const duration = bounded(this.durationValue * level.duration);
        const colors = this.colors(stage);
        const scale = Math.max(LOWEST_SCALE, level.scale * budget(globalThis.navigator));
        const pieces = piecesOf(name, { width, height, colors, duration, scale, focus: focusOf(focus) });
        const order = { id: ++this.playing, pieces, width, height, ratio, duration, effect: name, hidden: document.hidden };

        if (!this.offscreen(stage, order)) {
            const painter = new Painter((message) => this.ended(message), {
                frame: (callback) => requestAnimationFrame(callback),
                cancel: (id) => cancelAnimationFrame(id),
                halt: () => this.reduced() || 'none' === this.preference(),
            });

            if (!painter.start({ ...order, canvas: stage })) {
                stage.remove();

                return;
            }

            this.painter = painter;
        }

        current = this;
        this.stage = stage;
        this.pieces = pieces;
        this.colours = colors;
        this.watch(name);
        this.dispatch('start', { detail: { effect: name } });
    }

    /**
     * Hands the canvas to a worker as an `OffscreenCanvas`, with the order to paint, where the browser can; false when
     * it cannot, the canvas left to the page.
     */
    offscreen(stage, order) {
        if ('function' !== typeof stage.transferControlToOffscreen || 'function' !== typeof globalThis.Worker) {
            return false;
        }

        let worker;

        try {
            worker = new globalThis.Worker(SOURCE, { type: 'module', name: 'celebration' });
        } catch {
            return false;
        }

        let canvas;

        try {
            canvas = stage.transferControlToOffscreen();
        } catch {
            worker.terminate();

            return false;
        }

        worker.addEventListener('message', (event) => this.ended(event.data));
        worker.addEventListener('error', () => this.ended({ type: 'end', id: order.id }));
        worker.postMessage({ type: 'start', ...order, canvas }, [canvas]);
        this.worker = worker;

        return true;
    }

    /** Tells the painter, on the page or in its worker. */
    tell(message) {
        if (null !== this.painter) {
            this.painter.receive(message);
        } else {
            this.worker?.postMessage(message);
        }
    }

    /** What the painter posts: the end of the animation it paints. */
    ended(message) {
        if ('end' === message?.type && message.id === this.playing && this === current) {
            this.stop();
        }
    }

    /** While it plays: the colours read again when the theme changes, and a click bursting what floats. */
    watch(name) {
        this.scheme.addEventListener?.('change', this.onScheme);

        if ('function' === typeof globalThis.MutationObserver) {
            this.observer = new globalThis.MutationObserver(() => this.recolor());
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: THEME_ATTRIBUTES });
        }

        if (POPPING.includes(name)) {
            window.addEventListener('pointerdown', this.onPointer, { passive: true });
        }
    }

    /** The colours of the tokens read again, and the pieces given those of the theme now shown. */
    recolor() {
        if (null === this.stage) {
            return;
        }

        const colours = this.colors(this.stage);

        if (colours.join() !== this.colours.join()) {
            this.tell({ type: 'recolor', from: this.colours, to: colours });
            this.colours = colours;
        }
    }

    /** Bursts what floats under the pointer. */
    pop(event) {
        if (Number.isFinite(event?.clientX) && Number.isFinite(event?.clientY)) {
            this.tell({ type: 'pop', x: event.clientX, y: event.clientY });
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

    /** Holds the animation while the document is hidden, and takes it up where it was when it shows again. */
    visibility() {
        if (null !== this.stage) {
            this.tell({ type: document.hidden ? 'hold' : 'resume' });
        }
    }
}
