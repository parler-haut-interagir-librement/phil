// The podium and scratch card controllers, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import PodiumController, { CELEBRATE as PODIUM_CELEBRATE, lasting, ORDER, RISE, STAGGER } from '../../podium/assets/controllers/podium_controller.js';
import ScratchCardController, {
    CELEBRATE,
    cellsUnder,
    COLUMNS,
    FADE,
    HIGHEST,
    LOWEST,
    RADIUS,
    ROWS,
    STAGE,
    share,
    spoken,
    THRESHOLD,
} from '../../scratch-card/assets/controllers/scratch_card_controller.js';
import { browser, FakeElement, stimulate } from './fake.mjs';

let timers;

beforeEach(() => {
    browser();
    timers = [];
    globalThis.setTimeout = (callback, delay) => timers.push([callback, delay]) && timers.length;
    globalThis.clearTimeout = (id) => {
        if (undefined !== id && null !== id && timers[id - 1]) {
            timers[id - 1][0] = () => {};
        }
    };
    globalThis.IntersectionObserver = undefined;
    globalThis.lastWindowEvent = undefined;
    globalThis.getComputedStyle = () => ({ color: 'rgb(9, 9, 9)' });
    window.devicePixelRatio = 2;
});

/** A podium connected, its places and its live region. */
function podium(values = {}, { closest = {}, places = 3 } = {}) {
    const element = new FakeElement();
    element.closest = (selector) => closest[selector] ?? null;
    element.querySelectorAll = (selector) => ('[data-place]' === selector ? Array.from({ length: places }) : []);
    const message = new FakeElement();
    const controller = stimulate(PodiumController, {
        element,
        targets: { message: [message] },
        values: { message: 'Ada wins', effect: '', autoplay: true, ...values },
    });

    return { controller, element, message };
}

test('the places rise from the third to the first, staggered', () => {
    assert.deepEqual(ORDER, [3, 2, 1]);
    assert.equal(RISE, 600);
    assert.equal(STAGGER, 300);
    assert.equal(lasting(3), 1200);
    assert.equal(lasting(2), 900);
    assert.equal(lasting(1), 600);
    assert.equal(lasting(0), 600);
    assert.equal(lasting(9), 1200);
    assert.equal(lasting('x'), 600);
});

test('without an observer, it rises at once, then stands and says its message', () => {
    const { controller, element, message } = podium();
    controller.connect();

    assert.equal(element.getAttribute('data-state'), 'rising');
    assert.equal(message.textContent, '');
    assert.equal(timers.length, 1);
    assert.equal(timers[0][1], 1200);
    timers[0][0]();
    assert.equal(element.getAttribute('data-state'), 'risen');
    assert.equal(message.textContent, 'Ada wins');
    assert.deepEqual(controller.dispatched, [['risen', undefined]]);
    assert.equal(globalThis.lastWindowEvent, undefined, 'no effect, no celebration');

    controller.rise();
    assert.equal(timers.length, 1, 'once');
});

test('it waits until the podium comes into view', () => {
    const observed = [];
    let callback;
    globalThis.IntersectionObserver = class {
        constructor(onChange) {
            callback = onChange;
        }

        observe(element) {
            observed.push(element);
        }

        disconnect() {
            observed.length = 0;
        }
    };
    const { controller, element } = podium({}, { places: 2 });
    controller.connect();

    assert.equal(element.getAttribute('data-state'), 'waiting');
    assert.deepEqual(observed, [element]);
    callback([{ isIntersecting: false }]);
    assert.equal(element.getAttribute('data-state'), 'waiting');
    callback([{ isIntersecting: true }]);
    assert.equal(element.getAttribute('data-state'), 'rising');
    assert.equal(timers[0][1], 900);
    assert.deepEqual(observed, [], 'no longer observed');
});

test('without autoplay, it waits for its action', () => {
    const { controller, element } = podium({ autoplay: false });
    controller.connect();

    assert.equal(element.getAttribute('data-state'), 'waiting');
    assert.equal(timers.length, 0);
    controller.rise();
    assert.equal(element.getAttribute('data-state'), 'rising');
});

test('with an effect, the celebration of the page draws once they stand, the podium saying the words', () => {
    const { controller } = podium({ effect: 'confetti' });
    controller.connect();
    timers[0][0]();

    assert.equal(globalThis.lastWindowEvent.type, PODIUM_CELEBRATE);
    assert.deepEqual(globalThis.lastWindowEvent.detail, { effect: 'confetti' });
});

test('where motion is reduced, the places stand from the start and rising only says the message', () => {
    browser({ reduced: true });
    const { controller, element, message } = podium({ autoplay: false });
    controller.connect();

    assert.equal(element.getAttribute('data-state'), 'risen');
    controller.rise();
    assert.equal(message.textContent, 'Ada wins');
    assert.equal(timers.length, 0);

    browser();
    const marked = podium({}, { closest: { '[data-motion="reduce"]': {} } });
    marked.controller.connect();
    assert.equal(marked.element.getAttribute('data-state'), 'risen');
    assert.equal(marked.message.textContent, 'Ada wins');
});

test('an empty message is not written, and disconnecting stops the rise', () => {
    const { controller, message } = podium({ message: '' });
    controller.connect();
    controller.disconnect();

    timers[0][0]();
    assert.equal(message.textContent, '');
    assert.deepEqual(controller.dispatched, []);
});

/** A 2D context that records every call made on it. */
function recorder() {
    const calls = [];
    const state = { fillStyle: '', globalCompositeOperation: 'source-over' };

    return new Proxy(state, {
        get(target, name) {
            if (name === 'calls') {
                return calls;
            }

            return name in target ? target[name] : (...values) => calls.push([name, ...values]);
        },
        set(target, name, value) {
            target[name] = value;
            calls.push([`=${String(name)}`, value]);

            return true;
        },
    });
}

/** A scratch card connected: its prize, its cover, its button and its live region. */
function card(values = {}, { closest = {}, context = recorder(), focused = false } = {}) {
    const element = new FakeElement();
    element.closest = (selector) => closest[selector] ?? null;
    const prize = new FakeElement({ rect: { top: 10, left: 20, right: 220, bottom: 110 } });
    prize.textContent = '  A free\n coffee ';
    const appended = [];
    prize.append = (child) => appended.push(child);
    prize.appended = appended;
    prize.inert = false;
    const cover = new FakeElement({ tagName: 'CANVAS', rect: { top: 10, left: 20, right: 220, bottom: 110 } });
    cover.style.removeProperty = (name) => delete cover.style[name];
    cover.getContext = () => context;
    cover.remove = () => (cover.removed = true);
    cover.setPointerCapture = (id) => (cover.captured = id);
    const button = new FakeElement({ tagName: 'BUTTON' });
    button.hidden = true;
    const message = new FakeElement();
    document.activeElement = focused ? button : null;
    const controller = stimulate(ScratchCardController, {
        element,
        targets: {
            prize: [prize],
            stage: [{ content: { firstElementChild: { cloneNode: () => cover } } }],
            button: [button],
            message: [message],
        },
        values: { message: '', threshold: 50, effect: '', color: '--phil-color-neutral-fill-loud', ...values },
    });
    controller.connect();

    return { controller, prize, cover, button, message, context };
}

test('the share of the cover that reveals the prize, kept within its bounds', () => {
    assert.equal(share(50), 0.5);
    assert.equal(share(5), LOWEST / 100);
    assert.equal(share(95), HIGHEST / 100);
    assert.equal(share('x'), THRESHOLD / 100);
    assert.equal(share(undefined), 0.5);
    assert.equal(share(30), 0.3);
});

test('the cells of the grid under a stroke', () => {
    assert.deepEqual(cellsUnder(5, 5, 6, 120, 80, 12, 8), [0]);
    assert.deepEqual(cellsUnder(10, 10, 8, 120, 80, 12, 8), [0, 1, 12, 13], 'on the corner of four cells');
    assert.deepEqual(cellsUnder(115, 75, 6, 120, 80, 12, 8), [95]);
    assert.equal(cellsUnder(60, 40, 1000, 120, 80, 12, 8).length, 96);
    assert.deepEqual(cellsUnder(500, 500, 10, 120, 80, 12, 8), []);
    assert.deepEqual(cellsUnder(5, 5, 6, 0, 80), []);
    assert.deepEqual(cellsUnder(5, 5, 6, 120, -1), []);
    assert.deepEqual(cellsUnder(Number.NaN, 5, 6, 120, 80), []);
    assert.deepEqual(cellsUnder(5, Number.POSITIVE_INFINITY, 6, 120, 80), []);
    assert.deepEqual(cellsUnder(5, 5, 6, 120, 80), [0], 'the grid of the cover by default');
    assert.equal(COLUMNS * ROWS, 96);
    assert.equal(RADIUS, 18);
});

test('a text as a screen reader says it', () => {
    assert.equal(spoken('  A free\n\t coffee '), 'A free coffee');
    assert.equal(spoken(null), '');
});

test('its cover is painted over the prize, which is hidden from screen readers and the keyboard', () => {
    const { controller, prize, cover, button, context } = card();

    assert.equal(Object.hasOwn(controller, 'context'), false, 'the context of Stimulus left alone');

    assert.deepEqual(prize.appended, [cover]);
    assert.equal(prize.style.position, 'relative');

    for (const [property, value] of Object.entries(STAGE)) {
        assert.equal(cover.style[property], value);
    }

    assert.equal(cover.getAttribute('aria-hidden'), 'true');
    assert.equal(cover.width, 400);
    assert.equal(cover.height, 200);
    assert.deepEqual(context.calls.slice(0, 3), [['scale', 2, 2], ['=fillStyle', 'rgb(9, 9, 9)'], ['fillRect', 0, 0, 200, 100]]);
    assert.equal(cover.style.color, undefined, 'the colour read, then removed');
    assert.equal(prize.getAttribute('aria-hidden'), 'true');
    assert.equal(prize.inert, true);
    assert.equal(button.hidden, false);
});

test('grey where the token resolves to nothing, or is none', () => {
    globalThis.getComputedStyle = () => ({ color: '' });
    assert.ok(card().context.calls.some(([call, value]) => '=fillStyle' === call && 'grey' === value));
    browser();
    assert.ok(card({ color: 'red;x' }).context.calls.some(([call, value]) => '=fillStyle' === call && 'grey' === value));
});

test('without a context, the prize stays shown; without a prize, nothing happens', () => {
    const { prize, button } = card({}, { context: null });
    assert.equal(prize.getAttribute('aria-hidden'), null);
    assert.equal(button.hidden, true);

    const controller = stimulate(ScratchCardController, { element: new FakeElement(), targets: { prize: [] }, values: {} });
    assert.doesNotThrow(() => controller.connect());
    assert.doesNotThrow(() => controller.reveal());
});

test('a pointer pressed and moved scratches the cover away, and enough of it reveals the prize', () => {
    const { controller, prize, cover, message, context } = card({ threshold: 10 });

    controller.scratch({ clientX: 30, clientY: 20 });
    assert.equal(controller.scratched.size, 0, 'not while it is not pressed');

    controller.press({ pointerId: 7, clientX: 30, clientY: 20 });
    assert.equal(cover.captured, 7);
    assert.equal(context.globalCompositeOperation, 'destination-out');
    assert.ok(context.calls.some(([call, x, y, radius]) => 'arc' === call && 10 === x && 10 === y && RADIUS === radius));
    assert.ok(controller.scratched.size > 0);
    assert.equal(controller.revealed, false);

    controller.release();
    controller.scratch({ clientX: 120, clientY: 60 });
    assert.equal(controller.revealed, false, 'released, it scratches no more');

    controller.press({ pointerId: 7, clientX: 50, clientY: 30 });
    controller.scratch({ clientX: 90, clientY: 50 });
    assert.equal(controller.revealed, true);
    assert.equal(prize.getAttribute('aria-hidden'), null);
    assert.equal(prize.inert, false);
    assert.equal(message.textContent, 'A free coffee');
    assert.deepEqual(controller.dispatched, [['revealed', undefined]]);
    assert.equal(cover.style.opacity, '0');
    assert.equal(cover.style.transition, `opacity ${FADE}ms ease-out`);
    assert.equal(cover.removed, undefined, 'it fades first');
    timers.at(-1)[0]();
    assert.equal(cover.removed, true);

    const scratches = controller.scratched.size;
    controller.scratch({ clientX: 200, clientY: 100 });
    assert.equal(controller.scratched.size, scratches, 'nothing more once revealed');
});

test('a pointer the canvas cannot capture still scratches', () => {
    const { controller, cover } = card();
    cover.setPointerCapture = () => {
        throw new Error('NotFoundError');
    };

    assert.doesNotThrow(() => controller.press({ pointerId: 1, clientX: 30, clientY: 20 }));
    assert.ok(controller.scratched.size > 0);
});

test('its button reveals the prize at once, says its message, gives it the focus and plays the celebration', () => {
    const { controller, prize, button, message } = card({ message: 'Ticket 1207 wins', effect: 'coins' }, { focused: true });

    controller.reveal();
    assert.equal(button.hidden, true);
    assert.equal(prize.getAttribute('tabindex'), '-1');
    assert.deepEqual(prize.calls.at(-1), ['focus', undefined]);
    assert.equal(message.textContent, 'Ticket 1207 wins');
    assert.equal(globalThis.lastWindowEvent.type, CELEBRATE);
    assert.deepEqual(globalThis.lastWindowEvent.detail, { effect: 'coins' });

    controller.reveal();
    assert.equal(controller.dispatched.length, 1, 'once');
});

test('revealed by scratching, the focus stays where it is', () => {
    const { controller, prize } = card();

    controller.reveal();
    assert.equal(prize.getAttribute('tabindex'), null);
    assert.equal(globalThis.lastWindowEvent, undefined, 'no effect, no celebration');
});

test('where motion is reduced, the rest of the cover goes at once', () => {
    browser({ reduced: true });
    const { controller, cover } = card();

    controller.reveal();
    assert.equal(cover.removed, true);
    assert.equal(cover.style.opacity, undefined);

    browser();
    const marked = card({}, { closest: { '[data-motion="reduce"]': {} } });
    marked.controller.reveal();
    assert.equal(marked.cover.removed, true);
});

test('disconnected, its cover goes and its fade stops', () => {
    const { controller, cover } = card();

    controller.reveal();
    controller.disconnect();
    assert.equal(controller.fading, null);
    const other = card();
    other.controller.disconnect();
    assert.equal(other.cover.removed, true);
    assert.equal(cover.removed, undefined, 'already left to its fade');
});
