// The celebration controller, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { setTimeout as sleep } from 'node:timers/promises';

import CelebrationController, {
    advance,
    ANNOUNCE_DELAY,
    bounded,
    BURST_GAP,
    confetti,
    draw,
    DURATION,
    effect,
    EVENT,
    fireworks,
    LONGEST,
    opacity,
    optionsOf,
    SHORTEST,
    STAGE,
    tokens,
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
    // The tokens of the theme, as the CSSOM resolves them on the canvas; any other colour is that of the text.
    colours = { '--brand': 'oklch(0.5 0.1 195)', '--success': 'oklch(0.6 0.1 150)' };
    globalThis.getComputedStyle = (element) => {
        const asked = element.style?.getPropertyValue?.('color')?.match(/^var\((.+)\)$/)?.[1];

        return { color: colours[asked] ?? 'rgb(20, 20, 20)', direction: 'ltr' };
    };
});

/** Runs the frames asked for, `count` of them or until none is left, each `step` milliseconds after the last. */
function run(count = Infinity, step = 16) {
    for (let i = 0; i < count && frames.size > 0; i++) {
        const [id, callback] = frames.entries().next().value;
        frames.delete(id);
        now += step;
        callback(now);
    }
}

function celebration(values = {}, { stage = true, reducedAncestor = false } = {}) {
    const message = new FakeElement();
    const model = new FakeCanvas();
    const template = { content: { firstElementChild: stage ? model : null } };
    const element = new FakeElement();
    element.append = (child) => {
        child.parent = element;
        element.children.push(child);
    };
    element.closest = (selector) => (reducedAncestor && '[data-motion="reduce"]' === selector ? element : null);
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
    assert.equal(effect('lasers'), 'confetti');
    assert.equal(effect(undefined), 'confetti');
    assert.deepEqual(tokens(' --a  --b-2 red var(--c) --d_e '), ['--a', '--b-2', '--d_e']);
    assert.deepEqual(tokens(undefined), []);
});

test('celebration: the message and the effect of an action params first, then of an event detail', () => {
    assert.deepEqual(optionsOf(undefined), { message: undefined, effect: undefined });
    assert.deepEqual(optionsOf({ detail: 1, params: {} }), { message: undefined, effect: undefined });
    assert.deepEqual(optionsOf({ detail: null, params: null }), { message: undefined, effect: undefined });
    assert.deepEqual(optionsOf({ detail: { message: 'Bingo', effect: 'fireworks' } }), { message: 'Bingo', effect: 'fireworks' });
    assert.deepEqual(optionsOf({ detail: { message: 'Bingo' }, params: { message: 'Yes', effect: '' } }), { message: 'Yes', effect: undefined });
    assert.deepEqual(optionsOf({ detail: { message: 3, effect: ['x'] } }), { message: undefined, effect: undefined });
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
    const elapsed = controller.elapsed;
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
    assert.equal(controller.elapsed, elapsed + 16, 'the first frame after the pause counts as one');
    run(1, 5000);
    assert.equal(controller.elapsed, elapsed + 16 + 64, 'a late frame counts as 64 milliseconds at most');
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
