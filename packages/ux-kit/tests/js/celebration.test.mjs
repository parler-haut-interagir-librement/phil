// The celebration controller, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { setTimeout as sleep } from 'node:timers/promises';

import CelebrationController, {
    advance,
    ANNOUNCE_DELAY,
    bounded,
    budget,
    BURST_GAP,
    confetti,
    DISCREET,
    DOM_EFFECTS,
    draw,
    DURATION,
    effect,
    EFFECTS,
    EVENT,
    FATIGUE,
    fireworks,
    focusOf,
    intensity,
    LONGEST,
    LOWEST_SCALE,
    onStream,
    opacity,
    optionsOf,
    perform,
    POPPING,
    preference,
    PREFERENCES,
    remember,
    addresses,
    images,
    localOf,
    rollOf,
    SOUND_KEY,
    SOUND_ON,
    VIBRATION,
    VIBRATIONS,
    vibration,
    WAVE_GAP,
    WAVE_LIMIT,
    WAVE_RISE,
    WAVE_STEP,
    waveGap,
    waveTargets,
    SHAPES_LIMIT,
    SPRITE_SIZE,
    seen,
    SHORTEST,
    STAGE,
    storageOf,
    STORAGE_KEY,
    STREAM_ACTION,
    streamOptions,
    THEME_ATTRIBUTES,
    tokens,
    varied,
} from '../../celebration/assets/controllers/celebration_controller.js';
import { browser, FakeElement, stimulate } from './fake.mjs';

/** A 2D context that records what is drawn. */
class FakeContext {
    constructor() {
        this.calls = [];
        this.globalAlpha = 1;
        this.fillStyle = '';
    }
}

for (const method of ['clearRect', 'beginPath', 'arc', 'fill', 'save', 'translate', 'rotate', 'fillRect', 'restore', 'scale']) {
    FakeContext.prototype[method] = function (...values) {
        this.calls.push([method, ...values]);
    };
}

/** A canvas: its style, its bitmap's size, its context, and whether it is in the page. */
class FakeCanvas extends FakeElement {
    constructor({ context = new FakeContext() } = {}) {
        super({ tagName: 'CANVAS', attributes: { class: 'isCelebrationCanvas' } });
        this.context = context;
        this.width = 300;
        this.height = 150;
        this.parent = null;
        const style = new Map();
        this.style = {
            setProperty: (name, value) => style.set(name, value),
            removeProperty: (name) => style.delete(name),
            getPropertyValue: (name) => style.get(name) ?? '',
        };
    }

    cloneNode() {
        return new FakeCanvas({ context: this.context });
    }

    getContext(type) {
        return '2d' === type ? this.context : null;
    }

    remove() {
        this.parent?.children.splice(this.parent.children.indexOf(this), 1);
        this.parent = null;
    }
}

let frames;
let now;
let listeners;
let colours;

beforeEach(() => {
    listeners = browser();
    frames = new Map();
    now = 1000;
    let next = 1;
    globalThis.requestAnimationFrame = (callback) => {
        frames.set(next, callback);

        return next++;
    };
    globalThis.cancelAnimationFrame = (id) => frames.delete(id);
    globalThis.window.devicePixelRatio = 3;
    globalThis.window.sessionStorage = memory();
    Object.defineProperty(globalThis, 'navigator', { value: { hardwareConcurrency: 8 }, configurable: true, writable: true });
    delete globalThis.Worker;
    delete globalThis.MutationObserver;
    // The tokens of the theme, as the CSSOM resolves them on the canvas; any other colour is that of the text.
    colours = { '--brand': 'oklch(0.5 0.1 195)', '--success': 'oklch(0.6 0.1 150)' };
    globalThis.getComputedStyle = (element) => {
        const asked = element.style?.getPropertyValue?.('color')?.match(/^var\((.+)\)$/)?.[1];

        return { color: colours[asked] ?? 'rgb(20, 20, 20)', direction: 'ltr' };
    };
});

/** A session storage of the browser, in memory. */
function memory(entries = {}) {
    const items = new Map(Object.entries(entries));

    return { getItem: (key) => items.get(key) ?? null, setItem: (key, value) => items.set(key, String(value)), items };
}

/** Runs the frames asked for, `count` of them or until none is left, each `step` milliseconds after the last. */
function run(count = Infinity, step = 16) {
    for (let i = 0; i < count && frames.size > 0; i++) {
        const [id, callback] = frames.entries().next().value;
        frames.delete(id);
        now += step;
        callback(now);
    }
}

function celebration(values = {}, { stage = true, reducedAncestor = false, choice = null } = {}) {
    const message = new FakeElement();
    const model = new FakeCanvas();
    const template = { content: { firstElementChild: stage ? model : null } };
    const element = new FakeElement();
    element.append = (child) => {
        child.parent = element;
        element.children.push(child);
    };
    element.closest = (selector) => {
        if ('[data-celebrations]' === selector) {
            return null === choice ? null : { getAttribute: (name) => ('data-celebrations' === name ? choice : null) };
        }

        return reducedAncestor && '[data-motion="reduce"]' === selector ? element : null;
    };
    globalThis.document.createElement = () => new FakeCanvas();
    const controller = stimulate(CelebrationController, {
        element,
        targets: { message: [message], stage: [template] },
        values: { message: 'You won', effect: 'confetti', autoplay: false, duration: 1000, colors: '--brand --success --missing', ...values },
    });

    return { controller, element, message, model };
}

const canvases = (element) => element.children.filter((child) => child instanceof FakeCanvas);

test('celebration: a duration kept within its bounds, an effect among the known ones, tokens alone', () => {
    assert.equal(bounded(100), SHORTEST);
    assert.equal(bounded(99999), LONGEST);
    assert.equal(bounded('1500.4'), 1500);
    assert.equal(bounded('soon'), DURATION);
    assert.equal(bounded(Infinity), DURATION);
    assert.ok(LONGEST < 5000, 'under the five seconds of WCAG 2.2.2');
    assert.equal(effect('fireworks'), 'fireworks');
    assert.equal(effect('confetti'), 'confetti');
    assert.equal(effect('lasers'), 'lasers');
    assert.equal(effect('disco'), 'confetti');
    assert.equal(effect(undefined), 'confetti');
    assert.deepEqual(tokens(' --a  --b-2 red var(--c) --d_e '), ['--a', '--b-2', '--d_e']);
    assert.deepEqual(tokens(undefined), []);
});

test('celebration: the message and the effect of an action params first, then of an event detail', () => {
    const none = { message: undefined, effect: undefined, focus: undefined, reaction: undefined };
    assert.deepEqual(optionsOf(undefined), none);
    assert.deepEqual(optionsOf({ detail: 1, params: {} }), none);
    assert.deepEqual(optionsOf({ detail: null, params: null }), none);
    assert.deepEqual(optionsOf({ detail: { message: 'Bingo', effect: 'fireworks' } }), { ...none, message: 'Bingo', effect: 'fireworks' });
    assert.deepEqual(optionsOf({ detail: { message: 'Bingo' }, params: { message: 'Yes', effect: '' } }), { ...none, message: 'Yes' });
    assert.deepEqual(optionsOf({ detail: { message: 3, effect: ['x'], reaction: 4 } }), none);
    assert.equal(optionsOf({ detail: { reaction: '👍' }, params: { reaction: '' } }).reaction, '👍');
    assert.equal(optionsOf({ detail: { reaction: '👍' }, params: { reaction: '❤️' } }).reaction, '❤️');
});

test('celebration: confetti across the width, above the top, as many as the area allows', () => {
    const random = () => 0.5;
    const small = confetti(100, 100, ['red'], random);
    assert.equal(small.length, 40);
    assert.equal(confetti(4000, 4000, ['red'], random).length, 200);
    const pieces = confetti(800, 800, ['red', 'blue'], random);
    assert.equal(pieces.length, 80);
    assert.deepEqual([pieces[0].color, pieces[1].color, pieces[2].color], ['red', 'blue', 'red']);
    assert.ok(pieces.every((piece) => piece.y < 0 && piece.x >= 0 && piece.x <= 800 && piece.vy > 0 && 'piece' === piece.shape && 0 === piece.delay));
    assert.equal(Math.round(confetti(800, 800, ['red'], () => 0)[0].y), -12);
    assert.ok(Math.abs(confetti(800, 800, ['red'], () => 1)[0].y + 252) < 1e-9);
});

test('celebration: bursts of fireworks apart, as many as end in time, four at most, each of one colour', () => {
    const random = () => 0.5;
    const bursts = (sparks) => [...new Set(sparks.map((spark) => spark.delay))];
    assert.deepEqual(bursts(fireworks(800, 600, ['red'], 600, random)), [0]);
    assert.deepEqual(bursts(fireworks(800, 600, ['red'], 1200, random)), [0]);
    assert.deepEqual(bursts(fireworks(800, 600, ['red'], 1700, random)), [0, BURST_GAP]);
    assert.deepEqual(bursts(fireworks(800, 600, ['red', 'blue'], 2500, random)), [0, 500, 1000]);
    assert.deepEqual(bursts(fireworks(800, 600, ['red'], 4000, random)), [0, 500, 1000, 1500]);
    const sparks = fireworks(1000, 1000, ['red', 'blue'], 1700, random);
    assert.equal(sparks.length, 72);
    assert.deepEqual([...new Set(sparks.map((spark) => spark.color))], ['red', 'blue']);
    assert.ok(sparks.every((spark) => spark.x === 500 && Math.round(spark.y) === 325 && 'spark' === spark.shape));
    assert.equal(fireworks(1000, 1000, ['red'], 600, random)[0].life, 600);
    assert.equal(fireworks(1000, 1000, ['red'], 3000, random)[0].life, 1200);
    const low = fireworks(1000, 1000, ['red'], 600, () => 0)[0];
    assert.deepEqual([low.x, low.y], [200, 150]);
});

test('celebration: the pieces move once started, pulled down and slowed', () => {
    const started = { x: 0, y: 0, vx: 1, vy: 0, angle: 0, spin: 0.5, delay: 0 };
    const waiting = { x: 0, y: 0, vx: 1, vy: 0, angle: 0, spin: 0.5, delay: 500 };
    advance([started, waiting], 100, 10, { gravity: 0.01, drag: 0.01 });
    assert.deepEqual([started.vx, started.vy, started.x, started.y, started.angle], [0.9, 0.1, 9, 1, 5]);
    assert.deepEqual([waiting.x, waiting.vx], [0, 1]);
    const stopped = { x: 0, y: 0, vx: 1, vy: 1, angle: 0, spin: 0, delay: 0 };
    advance([stopped], 0, 10, { gravity: 0, drag: 1 });
    assert.deepEqual([stopped.vx, stopped.vy], [0, 0]);
});

test('celebration: whole, then fading at the end of the animation or of its own life', () => {
    const piece = { delay: 0, life: Infinity };
    assert.equal(opacity(piece, 0, 1000), 1);
    assert.equal(opacity(piece, 700, 1000), 1);
    assert.equal(opacity(piece, 850, 1000), 0.5);
    assert.equal(opacity(piece, 1000, 1000), 0);
    assert.equal(opacity(piece, 1200, 1000), 0);
    const spark = { delay: 500, life: 1000 };
    assert.equal(opacity(spark, 400, 4000), 0);
    assert.equal(opacity(spark, 500, 4000), 1);
    assert.equal(opacity(spark, 1350, 4000), 0.5);
    assert.equal(opacity(spark, 1600, 4000), 0);
});

test('celebration: draws confetti turned and sparks round, skipping what is not shown', () => {
    const context = new FakeContext();
    draw(context, [
        { shape: 'piece', x: 10, y: 20, size: 8, angle: 1, color: 'red', delay: 0, life: Infinity },
        { shape: 'spark', x: 5, y: 6, size: 2, angle: 0, color: 'blue', delay: 0, life: 1000 },
        { shape: 'spark', x: 5, y: 6, size: 2, angle: 0, color: 'green', delay: 900, life: 1000 },
    ], 100, 1000, 300, 200);
    assert.deepEqual(context.calls, [
        ['clearRect', 0, 0, 300, 200],
        ['save'],
        ['translate', 10, 20],
        ['rotate', 1],
        ['fillRect', -4, -2, 8, 4],
        ['restore'],
        ['beginPath'],
        ['arc', 5, 6, 2, 0, Math.PI * 2],
        ['fill'],
    ]);
    assert.equal(context.globalAlpha, 1);
    assert.equal(context.fillStyle, 'blue');
});

test('celebration: says its message, then draws over the page in the colours of the theme, then goes', async () => {
    const { controller, element, message, model } = celebration();
    controller.connect();
    assert.ok(listeners.window.has(EVENT));
    assert.ok(listeners.document.has('visibilitychange'));
    assert.equal(canvases(element).length, 0, 'nothing plays until asked');

    message.textContent = 'You won';
    controller.play();
    assert.equal(message.textContent, '', 'emptied, so that the same words are said again');
    const [stage] = canvases(element);
    assert.ok(stage, 'the canvas is in the page');
    assert.equal(stage.getAttribute('aria-hidden'), 'true');
    assert.equal(stage.getAttribute('class'), 'isCelebrationCanvas');
    for (const [property, value] of Object.entries(STAGE)) {
        assert.equal(stage.style.getPropertyValue(property), value);
    }
    assert.equal(stage.style.getPropertyValue('color'), '', 'the probe of the colours is taken away');
    assert.deepEqual([stage.width, stage.height], [2000, 1600], 'the device pixel ratio, two at most');
    assert.deepEqual(model.context.calls[0], ['scale', 2, 2]);
    assert.deepEqual([...new Set(controller.pieces.map((piece) => piece.color))], ['oklch(0.5 0.1 195)', 'oklch(0.6 0.1 150)', 'rgb(20, 20, 20)']);
    assert.equal(controller.pieces[0].shape, 'piece');
    assert.deepEqual(controller.dispatched[0], ['start', { detail: { effect: 'confetti' } }]);

    run(3);
    assert.ok(model.context.calls.some(([method]) => 'fillRect' === method), 'drawn');
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'You won');

    run();
    assert.equal(canvases(element).length, 0, 'gone at the end');
    assert.deepEqual([stage.width, stage.height], [0, 0], 'its bitmap freed');
    assert.equal(controller.dispatched.at(-1)[0], 'end');
    assert.equal(frames.size, 0);
    controller.disconnect();
    assert.ok(!listeners.window.has(EVENT));
    assert.ok(!listeners.document.has('visibilitychange'));
});

test('celebration: the run lasts the duration asked, kept within its bounds', () => {
    const { controller, element } = celebration({ duration: 99999 });
    controller.connect();
    controller.play();
    run(Math.floor(LONGEST / 16) - 1);
    assert.equal(canvases(element).length, 1);
    run(2);
    assert.equal(canvases(element).length, 0);
    controller.disconnect();
});

test('celebration: under reduced motion, the message alone', async () => {
    browser({ reduced: true });
    const { controller, element, message } = celebration();
    controller.connect();
    controller.play();
    assert.equal(canvases(element).length, 0);
    assert.equal(frames.size, 0);
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'You won');
    controller.disconnect();
});

test('celebration: under data-motion="reduce" on an ancestor, the message alone', () => {
    const { controller, element } = celebration({}, { reducedAncestor: true });
    controller.connect();
    controller.play();
    assert.equal(canvases(element).length, 0);
    controller.disconnect();
});

test('celebration: motion reduced while it plays ends it at the next frame', () => {
    const motion = { matches: false };
    globalThis.window.matchMedia = () => motion;
    const { controller, element } = celebration();
    controller.connect();
    controller.play();
    run(1);
    motion.matches = true;
    run(1);
    assert.equal(canvases(element).length, 0);
    assert.equal(frames.size, 0);
    controller.disconnect();
});

test('celebration: plays at once with autoplay, fireworks from its value', () => {
    const { controller, element } = celebration({ autoplay: true, effect: 'fireworks' });
    controller.connect();
    assert.equal(canvases(element).length, 1);
    assert.equal(controller.pieces[0].shape, 'spark');
    controller.disconnect();
});

test('celebration: an event or an action gives its message and its effect', async () => {
    const { controller, element, message } = celebration();
    controller.connect();
    listeners.window.get(EVENT)({ detail: { message: 'Bingo!', effect: 'fireworks' } });
    assert.equal(controller.pieces[0].shape, 'spark');
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'Bingo!');
    controller.stop();
    controller.play({ params: { effect: 'unknown' }, detail: 1 });
    assert.equal(controller.pieces[0].shape, 'piece');
    assert.equal(canvases(element).length, 1);
    controller.disconnect();
});

test('celebration: one animation at a time on the page', () => {
    const first = celebration();
    const second = celebration();
    first.controller.connect();
    second.controller.connect();
    first.controller.play();
    first.controller.play();
    second.controller.play();
    assert.equal(canvases(first.element).length, 1);
    assert.equal(canvases(second.element).length, 0);
    first.controller.disconnect();
    second.controller.play();
    assert.equal(canvases(second.element).length, 1);
    second.controller.disconnect();
});

test('celebration: removed from the page, it stops at once and says nothing more', async () => {
    const { controller, element, message } = celebration();
    controller.connect();
    controller.play();
    run(2);
    controller.disconnect();
    assert.equal(canvases(element).length, 0);
    assert.equal(frames.size, 0);
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, '');
    controller.disconnect();
});

test('celebration: holds while the tab is hidden, then takes up where it was', () => {
    const { controller, element } = celebration();
    controller.connect();
    controller.play();
    run(10);
    const elapsed = controller.painter.elapsed;
    globalThis.document.hidden = true;
    listeners.document.get('visibilitychange')();
    assert.equal(frames.size, 0);
    listeners.document.get('visibilitychange')();
    assert.equal(frames.size, 0);
    globalThis.document.hidden = false;
    listeners.document.get('visibilitychange')();
    listeners.document.get('visibilitychange')();
    assert.equal(frames.size, 1);
    run(1, 5000);
    assert.equal(controller.painter.elapsed, elapsed + 16, 'the first frame after the pause counts as one');
    run(1, 5000);
    assert.equal(controller.painter.elapsed, elapsed + 16 + 64, 'a late frame counts as 64 milliseconds at most');
    assert.equal(canvases(element).length, 1);
    controller.disconnect();
});

test('celebration: a page hidden when it plays waits to be shown', () => {
    browser({ hidden: true });
    globalThis.window.devicePixelRatio = undefined;
    const { controller, element, model } = celebration();
    controller.connect();
    controller.play();
    assert.equal(canvases(element).length, 1);
    assert.deepEqual(model.context.calls[0], ['scale', 1, 1]);
    assert.equal(frames.size, 0);
    controller.disconnect();
});

test('celebration: without its template it makes its canvas, without a context it draws nothing', () => {
    const made = celebration({}, { stage: false });
    made.controller.connect();
    made.controller.play();
    assert.equal(canvases(made.element).length, 1);
    made.controller.disconnect();

    const blind = celebration();
    blind.model.getContext = () => null;
    blind.model.cloneNode = function () {
        return this;
    };
    blind.controller.connect();
    blind.controller.play();
    assert.equal(canvases(blind.element).length, 0);
    assert.equal(blind.controller.dispatched.length, 0);
    blind.controller.disconnect();

    const again = celebration();
    again.controller.connect();
    again.controller.play();
    assert.equal(canvases(again.element).length, 1, 'a canvas that failed does not hold the page');
    again.controller.disconnect();
});

test('celebration: without a token that resolves, the colour of the text; without a message, nothing said', () => {
    const { controller, message } = celebration({ colors: '', message: '' });
    controller.connect();
    message.textContent = 'kept';
    controller.play();
    assert.equal(message.textContent, 'kept');
    assert.deepEqual([...new Set(controller.pieces.map((piece) => piece.color))], ['rgb(20, 20, 20)']);
    controller.disconnect();
    globalThis.getComputedStyle = () => ({ color: '' });
    const plain = celebration({ colors: '' });
    plain.controller.connect();
    plain.controller.play();
    assert.deepEqual([...new Set(plain.controller.pieces.map((piece) => piece.color))], ['black']);
    plain.controller.disconnect();
});

test('celebration: the choice of a person, all by default', () => {
    assert.deepEqual(PREFERENCES, ['all', 'discreet', 'none']);
    assert.equal(preference('none'), 'none');
    assert.equal(preference('discreet'), 'discreet');
    assert.equal(preference('NONE'), 'all');
    assert.equal(preference(null), 'all');
});

test('celebration: in full at first, then more discreet as the session goes on, none when refused', () => {
    assert.equal(intensity('none', 0), null);
    assert.equal(intensity('discreet', 0), DISCREET);
    assert.deepEqual(intensity('all', 0), { scale: 1, duration: 1 });
    assert.deepEqual(intensity(undefined, FATIGUE - 1), { scale: 1, duration: 1 });
    assert.deepEqual(intensity('all', FATIGUE), { scale: 0.5, duration: 0.8 });
    assert.deepEqual(intensity('all', 2 * FATIGUE - 1), { scale: 0.5, duration: 0.8 });
    assert.equal(intensity('all', 2 * FATIGUE), DISCREET);
    assert.ok(DISCREET.scale < 0.5 && DISCREET.duration < 0.8);
});

test('celebration: fewer pieces on a modest device, LOWEST_SCALE at least', () => {
    assert.equal(budget(undefined), 1);
    assert.equal(budget({}), 1);
    assert.equal(budget({ hardwareConcurrency: 8, deviceMemory: 8 }), 1);
    assert.equal(budget({ hardwareConcurrency: 5 }), 1);
    assert.equal(budget({ hardwareConcurrency: 4 }), 0.7);
    assert.equal(budget({ hardwareConcurrency: 3 }), 0.7);
    assert.equal(budget({ hardwareConcurrency: 2 }), 0.4);
    assert.equal(budget({ hardwareConcurrency: 0, deviceMemory: 0 }), 1);
    assert.equal(budget({ deviceMemory: 4 }), 1);
    assert.equal(budget({ deviceMemory: 2 }), 0.7);
    assert.equal(budget({ deviceMemory: 1.5 }), 0.7);
    assert.equal(budget({ deviceMemory: 1 }), 0.5);
    assert.equal(budget({ deviceMemory: 0.5 }), 0.5);
    assert.equal(budget({ connection: { saveData: true } }), 0.5);
    assert.equal(budget({ connection: { saveData: 'yes' } }), 1);
    assert.equal(budget({ hardwareConcurrency: 4, deviceMemory: 2 }), 0.49);
    assert.equal(budget({ hardwareConcurrency: 2, deviceMemory: 1, connection: { saveData: true } }), LOWEST_SCALE);
});

test('celebration: varied words, another than the last when there are several', () => {
    assert.equal(varied([], 'a'), undefined);
    assert.equal(varied(undefined), undefined);
    assert.equal(varied(['', 3, null]), undefined);
    assert.equal(varied(['only'], 'only'), 'only');
    assert.equal(varied(['a', 'b', 'c'], 'a', () => 0), 'b');
    assert.equal(varied(['a', 'b', 'c'], 'a', () => 0.99), 'c');
    assert.equal(varied(['a', 'b', 'c'], 'b', () => 0.5), 'c');
    assert.equal(varied(['a', 'b'], undefined, () => 1), 'b', 'a random of one stays in the list');
    for (let i = 0; i < 20; i++) {
        assert.notEqual(varied(['a', 'b'], 'a'), 'a');
    }
});

test('celebration: what it lights, from an element or a selector', () => {
    const element = new FakeElement({ rect: { left: 10, top: 20, right: 110, bottom: 60 } });
    assert.deepEqual(focusOf(element), { x: 60, y: 40, radius: 66 });
    assert.deepEqual(focusOf('#winner', { querySelector: (selector) => ('#winner' === selector ? element : null) }), { x: 60, y: 40, radius: 66 });
    assert.equal(focusOf('#nobody', { querySelector: () => null }), undefined);
    assert.equal(focusOf('[[', { querySelector: () => {
        throw new Error('not a selector');
    } }), undefined);
    assert.equal(focusOf(undefined), undefined);
    assert.equal(focusOf({}), undefined);
    assert.equal(focusOf('#winner', undefined), undefined);
    assert.equal(optionsOf({ detail: { focus: element } }).focus, element);
    assert.equal(optionsOf({ detail: { focus: '#a' }, params: { focus: '#b' } }).focus, '#b');
    assert.equal(optionsOf({ detail: { focus: {} } }).focus, undefined);
});

test('celebration: counted in the session storage, or by the page without one', () => {
    const storage = memory();
    assert.equal(seen(storage), 0);
    remember(storage);
    remember(storage);
    assert.equal(seen(storage), 2);
    assert.equal(storage.items.get(STORAGE_KEY), '2');
    assert.equal(seen(memory({ [STORAGE_KEY]: 'many' })), 0);
    assert.equal(seen(memory({ [STORAGE_KEY]: '-3' })), 0);
    const blocked = {
        getItem() {
            throw new Error('blocked');
        },
        setItem() {
            throw new Error('blocked');
        },
    };
    remember(storage);
    assert.equal(seen(blocked), 3, 'the count of the page');
    remember(blocked);
    assert.equal(seen(blocked), 4);
    assert.equal(seen(null), 4);
    assert.equal(storageOf({ sessionStorage: storage }), storage);
    assert.equal(storageOf(undefined), null);
    assert.equal(storageOf({ get sessionStorage() {
        throw new Error('SecurityError');
    } }), null);
});

/** A `<turbo-stream>` element and its attributes, removed from the page when asked. */
function stream(attributes) {
    const element = new FakeElement({ tagName: 'TURBO-STREAM', attributes });
    element.remove = () => (element.removed = true);

    return element;
}

test('celebration: a Turbo Stream of the action celebrate plays the celebrations, once', () => {
    assert.equal(STREAM_ACTION, 'celebrate');
    assert.deepEqual(streamOptions(stream({ action: 'celebrate', message: 'First', effect: 'trophy', focus: '#a', reaction: undefined })), { message: 'First', effect: 'trophy', focus: '#a', reaction: undefined });
    assert.deepEqual(streamOptions(stream({ message: '' })), { message: undefined, effect: undefined, focus: undefined, reaction: undefined });
    assert.equal(streamOptions(stream({ action: 'celebrate', effect: 'reactions', reaction: '👏' })).reaction, '👏');
    assert.deepEqual(streamOptions(undefined), { message: undefined, effect: undefined, focus: undefined, reaction: undefined });

    const other = { detail: { newStream: stream({ action: 'append' }), render: 'kept' } };
    onStream(other);
    assert.equal(other.detail.render, 'kept', 'the actions of Turbo are left to Turbo');
    onStream(undefined);
    onStream({ detail: {} });

    const element = stream({ action: 'celebrate', message: 'Bingo', effect: 'lasers' });
    const event = { detail: { newStream: element, render: 'turbo' } };
    onStream(event);
    globalThis.lastWindowEvent = undefined;
    event.detail.render(element);
    assert.equal(globalThis.lastWindowEvent.type, EVENT);
    assert.deepEqual(globalThis.lastWindowEvent.detail, { message: 'Bingo', effect: 'lasers', focus: undefined, reaction: undefined });
    globalThis.lastWindowEvent = undefined;
    perform(element);
    assert.equal(globalThis.lastWindowEvent, undefined, 'played once');
});

test('celebration: listens to the Turbo Streams while one celebration at least is on the page', () => {
    const first = celebration();
    const second = celebration();
    first.controller.connect();
    assert.equal(listeners.document.get('turbo:before-stream-render'), onStream);
    second.controller.connect();
    first.controller.disconnect();
    assert.equal(listeners.document.get('turbo:before-stream-render'), onStream, 'kept for the other');
    second.controller.disconnect();
    assert.ok(!listeners.document.has('turbo:before-stream-render'));
});

test('celebration: without Turbo, the streams already in the page are played and removed', async () => {
    const element = stream({ action: 'celebrate', message: 'From the server', effect: 'fireworks' });
    let asked;
    globalThis.document.querySelectorAll = (selector) => {
        asked = selector;

        return [element];
    };
    const { controller, message } = celebration();
    controller.connect();
    assert.equal(asked, 'turbo-stream[action="celebrate"]');
    assert.ok(element.removed);
    listeners.window.get(EVENT)(globalThis.lastWindowEvent);
    assert.equal(controller.pieces[0].shape, 'spark');
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'From the server');
    controller.disconnect();

    const later = stream({ action: 'celebrate' });
    globalThis.document.querySelectorAll = () => [later];
    globalThis.customElements = { get: (name) => ('turbo-stream' === name ? class {} : undefined) };
    try {
        const turbo = celebration();
        turbo.controller.connect();
        assert.ok(!later.removed, 'left to Turbo');
        turbo.controller.disconnect();
    } finally {
        delete globalThis.customElements;
    }
});

test('celebration: data-celebrations="none" says the message and draws nothing', async () => {
    const { controller, element, message } = celebration({}, { choice: 'none' });
    controller.connect();
    controller.play();
    assert.equal(canvases(element).length, 0);
    assert.equal(seen(globalThis.window.sessionStorage), 0, 'not counted');
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'You won');
    controller.disconnect();
});

test('celebration: data-celebrations="discreet" draws fewer pieces for a shorter run', () => {
    const full = celebration({ duration: 2500 });
    full.controller.connect();
    full.controller.play();
    const count = full.controller.pieces.length;
    assert.equal(full.controller.painter.duration, 2500);
    full.controller.disconnect();
    globalThis.window.sessionStorage = memory();

    const discreet = celebration({ duration: 2500 }, { choice: 'discreet' });
    discreet.controller.connect();
    discreet.controller.play();
    assert.equal(discreet.controller.pieces.length, Math.round(count * DISCREET.scale));
    assert.equal(discreet.controller.painter.duration, 2500 * DISCREET.duration);
    discreet.controller.disconnect();
});

test('celebration: a choice changed to none while it plays ends it at the next frame', () => {
    let choice = 'all';
    const { controller, element } = celebration();
    element.closest = (selector) => ('[data-celebrations]' === selector ? { getAttribute: () => choice } : null);
    controller.connect();
    controller.play();
    run(1);
    choice = 'none';
    run(1);
    assert.equal(canvases(element).length, 0);
    controller.disconnect();
});

test('celebration: after FATIGUE celebrations in the session, half as many pieces, then discreet ones', () => {
    const counts = [];
    const { controller } = celebration();
    controller.connect();
    for (let i = 0; i < 2 * FATIGUE + 1; i++) {
        controller.play();
        counts.push(controller.pieces.length);
        controller.stop();
    }
    assert.equal(seen(globalThis.window.sessionStorage), 2 * FATIGUE + 1);
    assert.deepEqual(counts, [100, 100, 100, 50, 50, 50, 30]);
    controller.disconnect();
});

test('celebration: a modest device draws fewer pieces', () => {
    globalThis.navigator = { hardwareConcurrency: 2, deviceMemory: 1 };
    const { controller } = celebration();
    controller.connect();
    controller.play();
    assert.equal(controller.pieces.length, Math.round(100 * LOWEST_SCALE));
    controller.disconnect();
});

test('celebration: its messages said in turn when it has no message of its own', async () => {
    const { controller, message } = celebration({ message: '', messages: ['Well done', 'Bravo'] });
    controller.connect();
    const said = [];
    for (let i = 0; i < 4; i++) {
        controller.play();
        controller.stop();
        await sleep(ANNOUNCE_DELAY + 20);
        said.push(message.textContent);
    }
    assert.ok(said.every((words, index) => 0 === index || words !== said[index - 1]), 'never the same twice in a row');
    assert.deepEqual([...new Set(said)].sort(), ['Bravo', 'Well done']);
    controller.messageValue = 'Kept';
    controller.play();
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'Kept', 'its message first');
    controller.disconnect();
    const silent = celebration({ message: '', messages: ['', ''] });
    silent.controller.connect();
    silent.message.textContent = 'unchanged';
    silent.controller.play();
    assert.equal(silent.message.textContent, 'unchanged');
    silent.controller.disconnect();
});

test('celebration: the spotlight lights the element of its focus', () => {
    const winner = new FakeElement({ rect: { left: 100, top: 200, right: 300, bottom: 260 } });
    globalThis.document.querySelector = (selector) => ('#winner' === selector ? winner : null);
    const { controller } = celebration({ effect: 'spotlight', focus: '#winner' });
    controller.connect();
    controller.play();
    assert.deepEqual([controller.pieces[0].x, controller.pieces[0].y, controller.pieces[0].size], [200, 230, 116]);
    controller.stop();
    controller.play({ detail: { effect: 'trophy', focus: winner } });
    assert.equal(controller.pieces[0].to, 230);
    controller.disconnect();
});

test('celebration: reduced-motion parity, every effect says the same message and draws nothing', async () => {
    for (const name of EFFECTS.filter((known) => !DOM_EFFECTS.includes(known))) {
        const shown = celebration({ effect: name, message: `Won with ${name}` });
        shown.controller.connect();
        shown.controller.play();
        assert.equal(canvases(shown.element).length, 1, `${name} is drawn`);
        assert.equal(shown.controller.dispatched[0][1].detail.effect, name);
        await sleep(ANNOUNCE_DELAY + 20 + ('drum-roll' === name ? rollOf(1000) : 0));
        const said = shown.message.textContent;
        shown.controller.disconnect();

        browser({ reduced: true });
        globalThis.window.sessionStorage = memory();
        const reduced = celebration({ effect: name, message: `Won with ${name}` });
        reduced.controller.connect();
        reduced.controller.play();
        assert.equal(canvases(reduced.element).length, 0, `${name} is not drawn under reduced motion`);
        assert.equal(frames.size, 0);
        await sleep(ANNOUNCE_DELAY + 20);
        assert.equal(reduced.message.textContent, said, `${name} says the same words`);
        reduced.controller.disconnect();
        listeners = browser();
        globalThis.window.sessionStorage = memory();
        globalThis.window.devicePixelRatio = 3;
        globalThis.getComputedStyle = () => ({ color: 'rgb(20, 20, 20)' });
    }
});

test('celebration: motion reduced by the system while it plays ends it at once', () => {
    let changed;
    const motion = {
        matches: false,
        addEventListener: (type, listener) => (changed = listener),
        removeEventListener: () => (changed = undefined),
    };
    globalThis.window.matchMedia = (query) => (query.includes('reduced-motion') ? motion : { matches: false });
    const { controller, element } = celebration();
    controller.connect();
    controller.play();
    changed();
    assert.equal(canvases(element).length, 1, 'still allowed');
    motion.matches = true;
    changed();
    assert.equal(canvases(element).length, 0);
    controller.disconnect();
    assert.equal(changed, undefined);
});

test('celebration: the colours read again when the theme or the colour scheme changes', () => {
    let scheme;
    let observed;
    globalThis.window.matchMedia = (query) => ({
        matches: false,
        addEventListener: (type, listener) => query.includes('color-scheme') && (scheme = listener),
        removeEventListener: () => query.includes('color-scheme') && (scheme = undefined),
    });
    globalThis.MutationObserver = class {
        constructor(callback) {
            this.callback = callback;
            observed = this;
        }

        observe(target, options) {
            this.target = target;
            this.options = options;
        }

        disconnect() {
            this.disconnected = true;
        }
    };
    const { controller } = celebration({ colors: '--brand' });
    controller.connect();
    controller.play();
    assert.equal(observed.target, globalThis.document.documentElement);
    assert.deepEqual(observed.options, { attributes: true, attributeFilter: THEME_ATTRIBUTES });
    assert.ok(controller.pieces.every((piece) => 'oklch(0.5 0.1 195)' === piece.color));
    observed.callback();
    assert.ok(controller.pieces.every((piece) => 'oklch(0.5 0.1 195)' === piece.color), 'nothing changed');
    colours['--brand'] = 'oklch(0.8 0.1 195)';
    scheme();
    assert.ok(controller.pieces.every((piece) => 'oklch(0.8 0.1 195)' === piece.color), 'the dark colours');
    colours['--brand'] = 'oklch(0.3 0.1 195)';
    observed.callback();
    assert.ok(controller.pieces.every((piece) => 'oklch(0.3 0.1 195)' === piece.color), 'the theme of the root');
    controller.stop();
    assert.ok(observed.disconnected);
    assert.equal(scheme, undefined);
    controller.recolor();
    controller.disconnect();
});

test('celebration: a click bursts a balloon or a bubble, and only while they float', () => {
    assert.deepEqual(POPPING, ['balloons', 'bubbles']);
    const { controller } = celebration({ effect: 'balloons' });
    controller.connect();
    controller.play();
    const pointer = listeners.window.get('pointerdown');
    assert.ok(pointer);
    const [balloon] = controller.pieces;
    const count = controller.painter.pieces.length;
    pointer({ clientX: balloon.x, clientY: balloon.y });
    assert.equal(controller.painter.pieces.length, count + 8);
    controller.pop({});
    controller.pop(undefined);
    assert.equal(controller.painter.pieces.length, count + 8);
    controller.stop();
    assert.ok(!listeners.window.has('pointerdown'));
    controller.play({ detail: { effect: 'confetti' } });
    assert.ok(!listeners.window.has('pointerdown'), 'confetti cannot be burst');
    controller.disconnect();
});

/** A worker as the browser makes it: what it is posted, what it listens to, and whether it was ended. */
class FakeWorker {
    static made = [];

    static refuse = false;

    constructor(url, options) {
        if (FakeWorker.refuse) {
            throw new Error('refused');
        }

        this.url = url;
        this.options = options;
        this.posted = [];
        this.listeners = new Map();
        this.terminated = false;
        FakeWorker.made.push(this);
    }

    postMessage(message, transfer) {
        this.posted.push([message, transfer]);
    }

    addEventListener(type, listener) {
        this.listeners.set(type, listener);
    }

    terminate() {
        this.terminated = true;
    }
}

test('celebration: where it can, the canvas is handed to a worker and painted off the main thread', () => {
    FakeWorker.made = [];
    FakeWorker.refuse = false;
    globalThis.Worker = FakeWorker;
    const { controller, element, model } = celebration({ effect: 'balloons' });
    const offscreen = { offscreen: true };
    model.transferControlToOffscreen = () => offscreen;
    model.cloneNode = function () {
        return this;
    };
    controller.connect();
    controller.play();
    const [worker] = FakeWorker.made;
    assert.ok(worker.url.endsWith('/celebration_worker.js'));
    assert.deepEqual(worker.options, { type: 'module', name: 'celebration' });
    const [[order, transfer]] = worker.posted;
    assert.equal(order.type, 'start');
    assert.equal(order.canvas, offscreen);
    assert.deepEqual(transfer, [offscreen]);
    assert.deepEqual([order.width, order.height, order.ratio, order.duration, order.effect, order.hidden], [1000, 800, 2, 1000, 'balloons', false]);
    assert.equal(order.pieces, controller.pieces);
    assert.equal(controller.painter, null);
    assert.equal(canvases(element).length, 1);
    assert.equal(frames.size, 0, 'no frame on the page');
    assert.deepEqual(controller.dispatched[0], ['start', { detail: { effect: 'balloons' } }]);

    globalThis.document.hidden = true;
    listeners.document.get('visibilitychange')();
    globalThis.document.hidden = false;
    listeners.document.get('visibilitychange')();
    listeners.window.get('pointerdown')({ clientX: 5, clientY: 6 });
    assert.deepEqual(worker.posted.slice(1).map(([message]) => message), [{ type: 'hold' }, { type: 'resume' }, { type: 'pop', x: 5, y: 6 }]);

    worker.listeners.get('message')({ data: { type: 'end', id: order.id - 1 } });
    assert.equal(canvases(element).length, 1, 'the end of another celebration');
    worker.listeners.get('message')({ data: { type: 'end', id: order.id } });
    assert.equal(canvases(element).length, 0);
    assert.ok(worker.terminated);
    assert.deepEqual([model.width, model.height], [300, 150], 'the canvas handed over is not sized by the page');
    assert.equal(controller.dispatched.at(-1)[0], 'end');

    controller.play();
    FakeWorker.made[1].listeners.get('error')();
    assert.equal(canvases(element).length, 0, 'a worker that fails ends it');
    controller.visibility();
    controller.disconnect();
    delete model.transferControlToOffscreen;
});

test('celebration: a worker refused, or a canvas that cannot be handed over, is painted on the page', () => {
    FakeWorker.made = [];
    globalThis.Worker = FakeWorker;
    FakeWorker.refuse = true;
    const refused = celebration();
    refused.model.transferControlToOffscreen = () => ({});
    refused.model.cloneNode = function () {
        return this;
    };
    refused.controller.connect();
    refused.controller.play();
    assert.ok(refused.controller.painter, 'painted on the page');
    assert.equal(frames.size, 1);
    refused.controller.disconnect();

    FakeWorker.refuse = false;
    const kept = celebration();
    kept.model.transferControlToOffscreen = () => {
        throw new Error('already handed');
    };
    kept.model.cloneNode = function () {
        return this;
    };
    kept.controller.connect();
    kept.controller.play();
    assert.ok(FakeWorker.made[0].terminated, 'the worker made for nothing ended');
    assert.ok(kept.controller.painter);
    kept.controller.disconnect();
    FakeWorker.refuse = false;
});

/** An element the wave can lift: what it is asked to animate, and an animation whose end the test decides. */
function animatable() {
    const element = new FakeElement();
    element.animate = (keyframes, options) => {
        let finish;
        let fail;
        const animation = {
            keyframes,
            options,
            state: 'running',
            finished: new Promise((resolve, reject) => {
                finish = resolve;
                fail = reject;
            }),
            cancel() {
                this.state = 'cancelled';
                fail(new Error('cancelled'));
            },
            pause() {
                this.state = 'paused';
            },
            play() {
                this.state = 'running';
            },
            finish() {
                finish(this);
            },
        };
        element.animation = animation;

        return animation;
    };

    return element;
}

test('celebration: the wave lifts the children of its focus, or of main, at most WAVE_LIMIT, apart in time', () => {
    const children = Array.from({ length: 30 }, animatable);
    const main = new FakeElement({ children: [new FakeElement(), ...children] });
    const list = new FakeElement({ children: children.slice(0, 3) });
    const root = { querySelector: (selector) => ({ main, '#list': list })[selector] ?? (selector.startsWith('[') ? (() => { throw new Error('bad'); })() : null) };
    assert.equal(waveTargets(undefined, root).length, WAVE_LIMIT, 'those that can be animated, WAVE_LIMIT at most');
    assert.equal(waveTargets(undefined, root)[0], children[0]);
    assert.deepEqual(waveTargets('#list', root), children.slice(0, 3));
    assert.deepEqual(waveTargets(list, root), children.slice(0, 3));
    assert.deepEqual(waveTargets('#none', root), []);
    assert.deepEqual(waveTargets('[bad', root), [], 'not a selector');
    assert.deepEqual(waveTargets(undefined, { querySelector: () => null }), []);
    assert.deepEqual(waveTargets(undefined, undefined), []);
    assert.equal(waveGap(1, 2500), 0);
    assert.equal(waveGap(3, 2500), WAVE_GAP, 'WAVE_GAP at most');
    assert.equal(waveGap(21, 2600), 100, 'all set down within the run');
    assert.equal(waveGap(5, 400), 0, 'a run shorter than a step');
    assert.deepEqual([WAVE_LIMIT, WAVE_RISE, WAVE_STEP, WAVE_GAP], [24, 12, 600, 120]);
});

test('celebration: the wave draws nothing, lifts each element by an animation added to its transform, then ends', async () => {
    const children = [animatable(), animatable(), animatable()];
    const main = new FakeElement({ children });
    globalThis.document.querySelector = (selector) => ('main' === selector ? main : null);
    const { controller, element, message } = celebration({ effect: 'wave', duration: 2500 });
    controller.connect();
    controller.play();
    assert.equal(canvases(element).length, 0, 'no canvas');
    assert.equal(frames.size, 0);
    assert.deepEqual(controller.dispatched[0], ['start', { detail: { effect: 'wave' } }]);
    assert.deepEqual(
        children.map((child) => child.animation.options),
        [0, 1, 2].map((index) => ({ duration: WAVE_STEP, delay: index * WAVE_GAP, easing: 'ease-in-out', composite: 'add' })),
    );
    assert.deepEqual(children[0].animation.keyframes, [{ transform: 'translateY(0)' }, { transform: 'translateY(-12px)', offset: 0.5 }, { transform: 'translateY(0)' }]);

    const other = celebration({ effect: 'wave' });
    other.controller.connect();
    other.controller.play();
    assert.equal(other.controller.dispatched.length, 0, 'one at a time');
    other.controller.disconnect();

    globalThis.document.hidden = true;
    controller.visibility();
    assert.ok(children.every((child) => 'paused' === child.animation.state), 'held while hidden');
    globalThis.document.hidden = false;
    controller.visibility();
    assert.ok(children.every((child) => 'running' === child.animation.state));

    for (const child of children) {
        child.animation.finish();
    }
    await sleep(0);
    assert.equal(controller.dispatched.at(-1)[0], 'end');
    assert.ok(children.every((child) => 'cancelled' === child.animation.state), 'set down, their own transforms kept');
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'You won');

    controller.play();
    assert.equal(controller.dispatched.at(-1)[1].detail.effect, 'wave', 'another may play');
    controller.disconnect();
    assert.equal(controller.dispatched.at(-1)[0], 'end', 'stopped when it leaves the page');
    controller.disconnect();
    assert.equal(controller.dispatched.filter(([name]) => 'end' === name).length, 2, 'once');
});

test('celebration: a wave with nothing to lift, or under reduced motion, says its message alone', async () => {
    globalThis.document.querySelector = () => null;
    const { controller, message } = celebration({ effect: 'wave' });
    controller.connect();
    controller.play();
    assert.deepEqual(controller.dispatched, []);
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, 'You won');
    const next = celebration({ effect: 'confetti' });
    next.controller.connect();
    next.controller.play();
    assert.equal(canvases(next.element).length, 1, 'nothing held the page');
    next.controller.disconnect();
    controller.disconnect();

    const child = animatable();
    globalThis.document.querySelector = () => new FakeElement({ children: [child] });
    const reduced = celebration({ effect: 'wave' }, { reducedAncestor: true });
    reduced.controller.connect();
    reduced.controller.play();
    assert.equal(child.animation, undefined, 'nothing lifted');
    reduced.controller.disconnect();
});

test('celebration: a drum roll says its message when its roll ends, at once when nothing is drawn', async () => {
    const { controller, message } = celebration({ effect: 'drum-roll', duration: 1000 });
    controller.connect();
    controller.play();
    assert.equal(controller.pieces[0].shape, 'drum');
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(message.textContent, '', 'suspense');
    await sleep(rollOf(1000));
    assert.equal(message.textContent, 'You won', 'revealed');
    controller.disconnect();

    const reduced = celebration({ effect: 'drum-roll', duration: 1000 }, { reducedAncestor: true });
    reduced.controller.connect();
    reduced.controller.play();
    await sleep(ANNOUNCE_DELAY + 20);
    assert.equal(reduced.message.textContent, 'You won');
    reduced.controller.disconnect();
});

test('celebration: the reactions rain the reaction of the action, of the event, or of its value', () => {
    const { controller } = celebration({ effect: 'reactions', reaction: '❤️' });
    controller.connect();
    controller.play();
    assert.ok(controller.pieces.every((piece) => 'glyph' === piece.shape && '❤️' === piece.text));
    controller.stop();
    controller.play({ params: { reaction: '👍' } });
    assert.equal(controller.pieces[0].text, '👍');
    controller.stop();
    listeners.window.get(EVENT)({ detail: { effect: 'reactions', reaction: '👏' } });
    assert.equal(controller.pieces[0].text, '👏');
    controller.disconnect();
    const plain = celebration({ effect: 'reactions' });
    plain.controller.connect();
    plain.controller.play();
    assert.equal(plain.controller.pieces[0].text, '🎉');
    plain.controller.disconnect();
});

test('celebration: a vibration only with vibrate, on a device that can, when something is drawn', () => {
    assert.deepEqual(vibration('applause'), VIBRATIONS.applause);
    assert.deepEqual(vibration('confetti'), VIBRATION);
    assert.deepEqual(vibration('drum-roll', 1000), [20, 60, 120]);
    assert.deepEqual(vibration('drum-roll', 4000), [20, 60, 20, 60, 20, 60, 20, 60, 20, 60, 20, 60, 120]);
    assert.deepEqual(vibration('drum-roll'), [20, 60, 20, 60, 20, 60, 20, 60, 120]);
    for (const name of ['applause', 'fireworks', 'rocket', 'confetti', 'drum-roll']) {
        assert.ok(vibration(name, 4000).reduce((sum, value) => sum + value, 0) <= 600, `${name} is short`);
    }

    const felt = [];
    globalThis.navigator.vibrate = (pattern) => felt.push(pattern);
    const off = celebration({ effect: 'applause' });
    off.controller.connect();
    off.controller.play();
    assert.deepEqual(felt, [], 'off by default');
    off.controller.disconnect();

    const on = celebration({ effect: 'applause', vibrate: true });
    on.controller.connect();
    on.controller.play();
    assert.deepEqual(felt, [VIBRATIONS.applause]);
    on.controller.disconnect();

    const reduced = celebration({ effect: 'rocket', vibrate: true }, { reducedAncestor: true });
    reduced.controller.connect();
    reduced.controller.play();
    assert.equal(felt.length, 1, 'nothing drawn, nothing felt');
    reduced.controller.disconnect();

    globalThis.navigator.vibrate = () => {
        throw new Error('refused');
    };
    const refused = celebration({ vibrate: true });
    refused.controller.connect();
    assert.doesNotThrow(() => refused.controller.play());
    refused.controller.disconnect();
    delete globalThis.navigator.vibrate;
    const without = celebration({ vibrate: true });
    without.controller.connect();
    assert.doesNotThrow(() => without.controller.play());
    without.controller.disconnect();
});

/** An audio context that records the sounds asked of it. */
class FakeAudio {
    static made = [];

    constructor() {
        this.state = 'suspended';
        this.currentTime = 0;
        this.sampleRate = 8;
        this.destination = {};
        this.started = [];
        this.resumed = 0;
        this.closed = false;
        FakeAudio.made.push(this);
    }

    resume() {
        this.resumed++;
        this.state = 'running';

        return Promise.resolve();
    }

    close() {
        this.closed = true;

        return Promise.resolve();
    }

    createBuffer(channels, length) {
        const data = new Float32Array(length);

        return { getChannelData: () => data };
    }

    node() {
        const param = () => ({ setValueAtTime() {}, linearRampToValueAtTime() {}, exponentialRampToValueAtTime() {} });

        return {
            gain: param(),
            frequency: param(),
            Q: param(),
            connect: (next) => next,
            start: (at) => this.started.push(at),
            stop() {},
        };
    }

    createBufferSource() {
        return this.node();
    }

    createBiquadFilter() {
        return this.node();
    }

    createGain() {
        return this.node();
    }

    createOscillator() {
        return this.node();
    }
}

test('celebration: silent until the person turns the sounds on by its button, kept in the local storage', async () => {
    FakeAudio.made = [];
    globalThis.AudioContext = FakeAudio;
    const local = memory();
    local.removeItem = (key) => local.items.delete(key);
    globalThis.window.localStorage = local;
    const button = new FakeElement();
    const { controller, element } = celebration();
    Object.defineProperty(controller, 'soundTargets', { get: () => [button] });
    controller.connect();
    assert.equal(button.getAttribute('aria-pressed'), 'false');
    controller.play();
    assert.equal(FakeAudio.made.length, 0, 'no sound by default');
    controller.stop();

    controller.toggleSound();
    assert.equal(button.getAttribute('aria-pressed'), 'true');
    assert.equal(local.getItem(SOUND_KEY), SOUND_ON);
    const [audio] = FakeAudio.made;
    assert.equal(audio.resumed, 1, 'its click is the gesture that lets the audio run');
    controller.play();
    assert.equal(audio.started.length, 3, 'a chime of three notes');
    controller.stop();

    element.closest = (selector) => ('[data-celebrations]' === selector ? { getAttribute: () => 'none' } : null);
    controller.play();
    assert.equal(audio.started.length, 3, 'none where the person chose none');
    controller.disconnect();
    assert.ok(audio.closed, 'the audio closed with the page');

    const again = celebration({ effect: 'applause' });
    const pressed = new FakeElement();
    Object.defineProperty(again.controller, 'soundTargets', { get: () => [pressed] });
    again.controller.connect();
    assert.equal(pressed.getAttribute('aria-pressed'), 'true', 'remembered');
    globalThis.navigator.userActivation = { isActive: false };
    again.controller.play();
    assert.equal(FakeAudio.made[1].started.length, 0, 'never by itself on a page that has seen no gesture');
    again.controller.stop();
    globalThis.navigator.userActivation = { isActive: true };
    again.controller.play();
    assert.equal(FakeAudio.made[1].started.length, 40, 'claps, after a gesture');
    again.controller.stop();
    again.controller.toggleSound();
    assert.equal(local.getItem(SOUND_KEY), null);
    assert.equal(pressed.getAttribute('aria-pressed'), 'false');
    again.controller.disconnect();
    delete globalThis.navigator.userActivation;

    delete globalThis.AudioContext;
    const mute = celebration();
    mute.controller.connect();
    mute.controller.toggleSound();
    assert.doesNotThrow(() => mute.controller.play(), 'a browser without audio plays nothing');
    mute.controller.disconnect();
    globalThis.AudioContext = class {
        constructor() {
            throw new Error('refused');
        }
    };
    const refused = celebration();
    refused.controller.connect();
    assert.doesNotThrow(() => refused.controller.play());
    refused.controller.disconnect();
    delete globalThis.AudioContext;
    delete globalThis.window.localStorage;
    assert.equal(localOf({ get localStorage() { throw new Error('blocked'); } }), null);
});

test('celebration: the images of the site, decoded into bitmaps a worker paints, those that fail left out', async () => {
    assert.deepEqual(addresses(['/a.svg', '', '  ', 3, '/b.png']), ['/a.svg', '/b.png']);
    assert.equal(addresses(Array.from({ length: 12 }, (_, index) => `/${index}.svg`)).length, SHAPES_LIMIT);
    assert.deepEqual(addresses('/a.svg'), []);
    assert.deepEqual(addresses(['/a.svg', '/b.svg'], 1), ['/a.svg']);
    assert.deepEqual(await images(['/a.svg'], {}), { images: [], bitmaps: true }, 'nothing without images');

    class FakeImage {
        decode() {
            return this.src.includes('broken') ? Promise.reject(new Error('broken')) : Promise.resolve();
        }

        get naturalWidth() {
            return this.src.includes('wide') ? 200 : 0;
        }

        get naturalHeight() {
            return this.src.includes('wide') ? 100 : 0;
        }
    }
    const made = [];
    const scope = {
        Image: FakeImage,
        createImageBitmap: async (image, options) => {
            made.push([image.src, image.decoding, options]);

            return { bitmap: image.src };
        },
    };
    const { images: found, bitmaps } = await images(['/wide.png', '/broken.svg', '/logo.svg'], scope);
    assert.deepEqual(found, [{ bitmap: '/wide.png' }, { bitmap: '/logo.svg' }]);
    assert.ok(bitmaps);
    assert.deepEqual(made, [
        ['/wide.png', 'async', { resizeWidth: SPRITE_SIZE, resizeHeight: SPRITE_SIZE / 2 }],
        ['/logo.svg', 'async', { resizeWidth: SPRITE_SIZE, resizeHeight: SPRITE_SIZE }],
    ]);
    const plain = await images(['/logo.svg'], { Image: FakeImage });
    assert.equal(plain.images[0].src, '/logo.svg', 'the image itself without bitmaps');
    assert.equal(plain.bitmaps, false);
});

test('celebration: the shapes and the mascot of the site; autoplay waits for their images', async () => {
    class FakeImage {
        decode() {
            return Promise.resolve();
        }
    }
    globalThis.Image = FakeImage;
    const { controller } = celebration({ effect: 'shapes', shapes: ['/logo.svg', '/gnome.svg'], mascot: '/gnome.svg', autoplay: true });
    controller.connect();
    assert.equal(controller.pieces, null, 'it waits for the images');
    await sleep(0);
    assert.equal(controller.sprites.shapes.length, 2);
    assert.equal(controller.sprites.mascot.length, 1);
    assert.equal(controller.sprites.bitmaps, false);
    assert.ok(controller.pieces.every((piece) => 'image' === piece.shape));
    assert.ok(controller.painter, 'images that are not bitmaps are painted on the page');
    controller.stop();
    controller.play({ params: { effect: 'mascot' } });
    assert.equal(controller.pieces[0].image, 0);
    controller.stop();
    controller.play({ params: { effect: 'confetti' } });
    assert.equal(controller.pieces[0].shape, 'piece');
    controller.disconnect();

    const gone = celebration({ effect: 'shapes', shapes: ['/logo.svg'], autoplay: true });
    gone.controller.connect();
    gone.controller.disconnect();
    await sleep(0);
    assert.equal(gone.controller.pieces, null, 'nothing plays once it has left the page');

    const bare = celebration({ effect: 'mascot', mascot: '' });
    bare.controller.connect();
    bare.controller.play();
    assert.equal(bare.controller.pieces[0].image, undefined, 'a figure without an image');
    bare.controller.disconnect();
    delete globalThis.Image;
});
