import { Controller } from '@hotwired/stimulus';
import { advance, DRAWN, draw, pieces } from './seasonal_decor_shapes.js';

export { advance, DECORS, DRAWN, draw, FLAP, pieces, SPIN } from './seasonal_decor_shapes.js';

/** The shortest and the longest a decor moves, in milliseconds, whatever `duration` asks. */
export const SHORTEST = 2000;
export const LONGEST = 15000;

/** How long the pieces take to fade at the end of their run, in milliseconds. */
export const FADE = 1500;

/** The longest step of the motion between two frames, in seconds: a tab shown again takes up where it was. */
export const LONGEST_STEP = 0.05;

/** The prefix of the keys of the local storage of the browser under which closings and eggs found are remembered. */
export const STORAGE_PREFIX = 'phil-seasonal-decor:';

/** What the controller sets on its canvas through the CSSOM, whatever the stylesheet: over the page, never in the way. */
export const STAGE = { position: 'fixed', inset: '0', width: '100%', height: '100%', 'pointer-events': 'none' };

/** The DOM event that plays the `Celebration` of the page. */
export const CELEBRATE = 'celebrate';

/** A duration in milliseconds, kept between `SHORTEST` and `LONGEST`; `LONGEST` when it is no number. */
export function bounded(duration) {
    const milliseconds = Number(duration);

    return Number.isFinite(milliseconds) ? Math.min(LONGEST, Math.max(SHORTEST, Math.round(milliseconds))) : LONGEST;
}

/**
 * The keys of a secret code, from a list separated by spaces: a single character in lower case, so that a code is
 * typed with or without the shift key, a named key as it is.
 */
export function sequence(code) {
    return String(code ?? '')
        .split(/\s+/)
        .filter((key) => '' !== key)
        .map(normal);
}

/** A key as `sequence` keeps it. */
export function normal(key) {
    const text = String(key ?? '');

    return 1 === [...text].length ? text.toLowerCase() : text;
}

/**
 * For each length of a code typed so far, the longest part of it that is also its start, so that a key that breaks
 * the code keeps what may still begin it: "up up up down…" still types "up up down…".
 */
export function fallbacks(keys) {
    const table = new Array(keys.length).fill(0);
    let length = 0;

    for (let index = 1; index < keys.length; index++) {
        while (length > 0 && keys[index] !== keys[length]) {
            length = table[length - 1];
        }

        if (keys[index] === keys[length]) {
            length++;
        }

        table[index] = length;
    }

    return table;
}

/** How much of a code is typed after one more key, from how much was before; its length when it is all typed. */
export function progress(keys, table, typed, key) {
    let length = typed >= keys.length ? table[keys.length - 1] : typed;

    while (length > 0 && keys[length] !== key) {
        length = table[length - 1];
    }

    return keys[length] === key ? length + 1 : 0;
}

/** Whether a key goes to a field, or comes with a modifier: never a key of the code. */
export function typing(event) {
    const target = event?.target;
    const editable = true === target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName);

    return editable || true === event?.ctrlKey || true === event?.metaKey || true === event?.altKey;
}

/** The text of the counter of the hunt, `%found%` and `%total%` replaced by the counts. */
export function counted(text, found, total) {
    return String(text ?? '')
        .replaceAll('%found%', String(found))
        .replaceAll('%total%', String(total));
}

/**
 * Where each egg hides among the nests of the page: `count` of them, each in a nest drawn at random, the nests used in
 * turn before one holds two.
 */
export function hiding(count, nests, random = Math.random) {
    if (0 === nests.length || count < 1) {
        return [];
    }

    const order = nests.map((nest) => [random(), nest]).sort((first, second) => first[0] - second[0]).map(([, nest]) => nest);

    return Array.from({ length: count }, (_, index) => order[index % order.length]);
}

/** The local storage of a window, or null where it cannot be read: a private window, blocked site data. */
export function storageOf(scope) {
    try {
        return scope?.localStorage ?? null;
    } catch {
        return null;
    }
}

/** What is remembered under a key, or null where nothing is, or nothing can be read. */
export function recall(storage, key) {
    try {
        return storage?.getItem(STORAGE_PREFIX + key) ?? null;
    } catch {
        return null;
    }
}

/** Remembers a value under a key, where the storage can be written; the page alone remembers otherwise. */
export function keep(storage, key, value) {
    try {
        storage?.setItem(STORAGE_PREFIX + key, String(value));
    } catch {
        // Without storage, the decor is closed, or the eggs counted, for this page alone.
    }
}

/** The share of its pieces a decor draws on a device: fewer on few cores, little memory, or when saving data. */
export function budget(device) {
    const cores = Number(device?.hardwareConcurrency);
    let scale = Number.isFinite(cores) && cores > 0 && cores <= 4 ? 0.5 : 1;

    if (true === device?.connection?.saveData) {
        scale *= 0.5;
    }

    return scale;
}

/*
 * A seasonal decor: pieces drawn over the page on a canvas for a few seconds, then gone; a still picture of them where
 * motion is reduced, by the system or by `data-motion="reduce"` on an ancestor, or when the decor is sober; nothing at
 * all where the person chose `data-celebrations="none"`, but a sober decor, the poppy, which the template shows. The
 * canvas is hidden from screen readers and lets every click through; the motion holds while the tab is hidden.
 *
 * Its button closes it, remembered for the holiday in the local storage of the browser. With `eggs`, eggs hide in the
 * elements marked `data-egg-nest`, or in the decor without one: each is a button that listens to its own clicks, the
 * nests lying outside the controller, found by a click or by the keyboard,
 * the count said in a polite live region and remembered for the holiday; when the last is found, the `Celebration` of
 * the page plays. Typed anywhere but in a field, the keys of `code` play it too.
 *
 * @value decor A decor of `DECORS`, or the poppy.
 * @value holiday What the closing and the eggs found are remembered under; nothing is remembered without it.
 * @value sober Whether it is a day of remembrance: a still picture, no hunt, no code.
 * @value eggs How many eggs hide for the hunt.
 * @value eggLabel The name of each egg.
 * @value counter What the hunt says, `%found%` and `%total%` replaced by the counts.
 * @value hunted What is said and celebrated when the last egg is found.
 * @value code The keys of the secret code, separated by spaces.
 * @value codeMessage What the `Celebration` of the page says when the code is typed.
 * @value codeEffect The effect it plays.
 * @value duration How long the decor moves, kept between `SHORTEST` and `LONGEST`.
 * @value colors The custom properties whose colours it draws in, separated by spaces; those of the decor without.
 * @target stage The template of the canvas.
 * @target egg The template of an egg.
 * @target counter The live region of the hunt.
 * @target close The button that closes it.
 */
export default class extends Controller {
    static targets = ['stage', 'egg', 'counter', 'close'];

    static values = {
        decor: String,
        holiday: String,
        sober: Boolean,
        eggs: Number,
        eggLabel: String,
        counter: String,
        hunted: String,
        code: String,
        codeMessage: String,
        codeEffect: String,
        duration: { type: Number, default: 12000 },
        colors: String,
    };

    connect() {
        this.stage = null;
        this.list = [];
        this.frame = null;
        this.elapsed = 0;
        this.last = null;
        this.hidden = [];
        this.keys = sequence(this.codeValue);
        this.table = fallbacks(this.keys);
        this.typed = 0;
        this.storage = storageOf(window);
        this.count = this.remembered();
        this.onKey = (event) => this.key(event);
        this.onFind = (event) => this.find(event);

        if (this.closed() || (!this.soberValue && 'none' === this.preference())) {
            this.element.hidden = true;

            return;
        }

        if (this.hasCloseTarget) {
            this.closeTarget.hidden = false;
        }

        this.paint();
        this.hide();

        if (this.keys.length > 0 && !this.soberValue) {
            document.addEventListener('keydown', this.onKey);
        }
    }

    disconnect() {
        this.stop();
        document.removeEventListener('keydown', this.onKey);

        for (const egg of this.hidden) {
            egg.remove();
        }

        this.hidden = [];
    }

    /** The action of its button: the decor goes, remembered for the holiday. */
    close() {
        if ('' !== this.holidayValue) {
            keep(this.storage, `closed:${this.holidayValue}`, '1');
        }

        this.disconnect();
        this.element.hidden = true;
        this.dispatch('closed');
    }

    /** A click on an egg, by the pointer or the keyboard: it is found, counted, and the last one plays the `Celebration` of the page. */
    find(event) {
        const egg = event?.currentTarget;
        const found = Math.min(this.eggsValue, this.count + 1);
        this.count = found;

        egg?.remove?.();
        this.hidden = this.hidden.filter((hidden) => hidden !== egg);

        if ('' !== this.holidayValue) {
            keep(this.storage, `eggs:${this.holidayValue}`, found);
        }

        const done = found >= this.eggsValue;
        this.say([counted(this.counterValue, found, this.eggsValue), done ? this.huntedValue : ''].join(' ').trim());
        this.dispatch('found', { detail: { found, total: this.eggsValue } });

        if (done) {
            this.celebrate(this.huntedValue);
            this.dispatch('hunted');
        }
    }

    /** Ends the motion now; the canvas and its bitmap go. */
    stop() {
        if (null !== this.frame) {
            cancelAnimationFrame(this.frame);
            this.frame = null;
        }

        if (null !== this.stage) {
            this.stage.width = 0;
            this.stage.height = 0;
            this.stage.remove();
            this.stage = null;
        }
    }

    /** Whether the decor was closed for this holiday. */
    closed() {
        return '' !== this.holidayValue && '1' === recall(this.storage, `closed:${this.holidayValue}`);
    }

    /** How many eggs were found for this holiday, remembered; none for the page alone. */
    remembered() {
        const count = '' === this.holidayValue ? 0 : Number.parseInt(recall(this.storage, `eggs:${this.holidayValue}`) ?? '0', 10);

        return Number.isFinite(count) && count > 0 ? count : 0;
    }

    /** Whether motion is reduced, by the system or by `data-motion="reduce"` on the element or an ancestor. */
    reduced() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches || null !== this.element.closest('[data-motion="reduce"]');
    }

    /** The choice of the person, from `data-celebrations` on the element or the nearest ancestor that has it. */
    preference() {
        return this.element.closest('[data-celebrations]')?.getAttribute('data-celebrations') ?? 'all';
    }

    /** The pieces drawn over the page: moving for a few seconds, or still where motion is reduced or the day sober. */
    paint() {
        if (!DRAWN.includes(this.decorValue)) {
            return;
        }

        const stage = this.canvas();
        const width = document.documentElement.clientWidth;
        const height = document.documentElement.clientHeight;
        const ratio = Math.min(2, window.devicePixelRatio || 1);
        const context = stage.getContext?.('2d') ?? null;

        if (null === context) {
            stage.remove();

            return;
        }

        stage.width = Math.round(width * ratio);
        stage.height = Math.round(height * ratio);
        context.scale(ratio, ratio);
        this.stage = stage;
        this.brush = context;
        this.size = { width, height };
        this.list = pieces(this.decorValue, { width, height, colors: this.colors(stage), scale: budget(globalThis.navigator) });
        draw(context, this.decorValue, this.list, this.size);

        if (!this.soberValue && !this.reduced()) {
            this.frame = requestAnimationFrame((time) => this.tick(time));
        }
    }

    /** One frame: the pieces moved by the time gone since the last, fading at the end of the run, then gone. */
    tick(time) {
        const seconds = null === this.last ? 0 : Math.min(LONGEST_STEP, Math.max(0, (time - this.last) / 1000));
        const run = bounded(this.durationValue);
        this.last = time;
        this.elapsed += seconds * 1000;

        if (this.elapsed >= run || this.reduced()) {
            this.frame = null;
            this.stop();

            return;
        }

        advance(this.decorValue, this.list, seconds, this.size);
        draw(this.brush, this.decorValue, this.list, { ...this.size, alpha: Math.min(1, (run - this.elapsed) / FADE) });
        this.frame = requestAnimationFrame((next) => this.tick(next));
    }

    /** The eggs of the hunt not yet found, each a button cloned from its template, hidden in a nest of the page. */
    hide() {
        if (this.soberValue || this.eggsValue < 1 || !this.hasEggTarget) {
            return;
        }

        const left = this.eggsValue - this.count;
        const nests = [...document.querySelectorAll('[data-egg-nest]')];

        for (const nest of hiding(left, nests.length > 0 ? nests : [this.element])) {
            const egg = this.eggTarget.content.firstElementChild.cloneNode(true);
            // The nests are outside the controller, where its actions do not reach: the egg listens itself.
            egg.addEventListener('click', this.onFind);
            nest.append(egg);
            this.hidden.push(egg);
        }
    }

    /** Listens to the keys of the page for the secret code, which plays the `Celebration` of the page once typed. */
    key(event) {
        if (typing(event)) {
            return;
        }

        this.typed = progress(this.keys, this.table, this.typed, normal(event?.key));

        if (this.typed === this.keys.length) {
            this.celebrate(this.codeMessageValue);
            this.dispatch('code');
        }
    }

    celebrate(message) {
        window.dispatchEvent(new CustomEvent(CELEBRATE, { detail: { message, effect: this.codeEffectValue } }));
    }

    say(text) {
        if (this.hasCounterTarget) {
            this.counterTarget.textContent = text;
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

    /** The colours of the tokens of `colors`, each resolved on the canvas through the CSSOM; none without tokens. */
    colors(stage) {
        const found = [];

        for (const token of String(this.colorsValue ?? '').split(/\s+/)) {
            if (!/^--[A-Za-z0-9_-]+$/.test(token)) {
                continue;
            }

            stage.style.setProperty('color', `var(${token})`);
            const colour = getComputedStyle(stage).color;

            if (colour && !found.includes(colour)) {
                found.push(colour);
            }
        }

        stage.style.removeProperty('color');

        return found;
    }
}
