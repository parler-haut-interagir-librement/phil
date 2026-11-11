// The animated-headline controller, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import AnimatedHeadlineController, { BUDGET, next, timing } from '../../animated-headline/assets/controllers/animated_headline_controller.js';

class ClassList {
    constructor(...names) { this.names = new Set(names); }
    add(...names) { names.forEach((name) => this.names.add(name)); }
    remove(...names) { names.forEach((name) => this.names.delete(name)); }
    contains(name) { return this.names.has(name); }
    toggle(name, force) { force ? this.add(name) : this.remove(name); }
}

class Target {
    constructor() { this.listeners = new Map(); }
    addEventListener(type, listener) { (this.listeners.get(type) ?? this.listeners.set(type, new Set()).get(type)).add(listener); }
    removeEventListener(type, listener) { this.listeners.get(type)?.delete(listener); }
    dispatch(type, event = {}) { [...(this.listeners.get(type) ?? [])].forEach((listener) => listener({ target: this, ...event })); }
    count() { return [...this.listeners.values()].reduce((sum, set) => sum + set.size, 0); }
}

const element = (width = 0, ...classes) => {
    const properties = new Map();

    return Object.assign(new Target(), {
        offsetWidth: width,
        classList: new ClassList(...classes),
        style: {
            properties,
            setProperty: (name, value) => properties.set(name, value),
            removeProperty: (name) => properties.delete(name),
        },
    });
};

const media = (reduced) => {
    const listeners = new Set();

    return {
        matches: reduced,
        addEventListener: (type, listener) => listeners.add(listener),
        removeEventListener: (type, listener) => listeners.delete(listener),
        set(value) { this.matches = value; listeners.forEach((listener) => listener()); },
        listeners,
    };
};

const setUp = ({ widths = [100, 60, 80], reduced = false, loop = false, current = 0, toggle = loop, hidden = false } = {}) => {
    const query = media(reduced);
    const windowListeners = new Map();
    globalThis.window = {
        matchMedia: () => query,
        addEventListener: (type, listener) => windowListeners.set(type, listener),
        removeEventListener: (type) => windowListeners.delete(type),
    };
    const doc = Object.assign(new Target(), { hidden });
    globalThis.document = doc;

    const controller = new AnimatedHeadlineController();
    const button = Object.assign(element(), { hidden: true, textContent: 'Pause animation' });
    const root = Object.assign(element(), { contains: (node) => node === root || node === button });
    controller.element = root;
    controller.revealTarget = element(0);
    controller.hasRevealTarget = true;
    controller.wordTargets = widths.map((width, index) => element(width, ...(index === current ? ['isCurrent'] : [])));
    controller.toggleTarget = button;
    controller.hasToggleTarget = toggle;
    controller.pauseValue = 1000;
    controller.durationValue = 200;
    controller.loopValue = loop;
    controller.pauseLabelValue = 'Pause animation';
    controller.playLabelValue = 'Play animation';

    return { controller, query, windowListeners, doc, root, button, reveal: controller.revealTarget, words: controller.wordTargets };
};

const shown = (words) => words.findIndex((word) => word.classList.contains('isCurrent'));
const size = (reveal) => reveal.style.properties.get('--phil-reveal-size');
const open = (width) => `calc(${width}px + var(--phil-reveal-caret, 0px))`;
// Mocked time, in steps of 10ms, so that a timer set by another timer fires within the same wait.
const wait = (context, ms) => {
    for (let elapsed = 0; elapsed < ms; elapsed += 10) {
        context.mock.timers.tick(10);
    }
};
// One step of a cycling headline under the test timings, from a word resting to the next one resting: the rest, the
// clip closing, then opening on the next word.
const step = (context) => wait(context, 1000 + 200 + 200);

test('the next word wraps around, and does not exist for one word', () => {
    assert.equal(next(0, 3), 1);
    assert.equal(next(2, 3), 0);
    assert.equal(next(0, 1), null);
    assert.equal(next(0, 0), null);
});

test('a run played through once fits within the budget; a cycling one keeps its timings', () => {
    assert.ok(BUDGET <= 5000, 'WCAG 2.2.2: no more than five seconds without a pause control');
    assert.deepEqual(timing(3, 2500, 600, true), { pause: 2500, duration: 600 });
    assert.deepEqual(timing(1, 2500, 600, false), { pause: 2500, duration: 600 }, 'one word: the entrance alone');
    assert.deepEqual(timing(2, 500, 200, false), { pause: 500, duration: 200 }, 'short enough already');

    [[2, 2500, 600], [3, 2500, 600], [5, 4000, 1000]].forEach(([count, pause, duration]) => {
        const scaled = timing(count, pause, duration, false);
        assert.ok((1 + 2 * count) * scaled.duration + count * scaled.pause <= BUDGET, `${count} words fit`);
        assert.ok(scaled.pause < pause && scaled.duration < duration);
    });
});

test('one word is revealed once, then stands still', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words, button } = setUp({ widths: [90], loop: true, toggle: false });

    controller.connect();
    assert.ok(reveal.classList.contains('isRevealing'));
    assert.equal(size(reveal), open(90), 'the entrance opens the clip on the word');

    wait(context, 200);
    assert.ok(!reveal.classList.contains('isRevealing'), 'the reveal ends after the entrance, even with loop');
    assert.ok(!reveal.classList.contains('isPulsing'));
    assert.equal(reveal.style.properties.size, 0, 'the clip is back to its natural width');
    assert.ok(button.hidden, 'nothing cycles, so no button');

    wait(context, 60000);
    assert.equal(reveal.style.properties.size, 0, 'it never closes again');
    assert.equal(shown(words), 0);
    controller.disconnect();
});

test('by default, the words play through once and stop on the first one within the budget', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words, button } = setUp({ widths: [100, 60] });
    const { pause, duration } = timing(2, 1000, 200, false);
    const seen = [];

    controller.connect();
    assert.equal(reveal.style.properties.get('--phil-reveal-duration'), `${duration}ms`);
    assert.equal(size(reveal), open(100));
    assert.ok(button.hidden, 'no button without loop');

    for (let elapsed = 0; elapsed < BUDGET; elapsed += 10) {
        wait(context, 10);
        if (shown(words) !== seen.at(-1)) {
            seen.push(shown(words));
        }
    }

    assert.deepEqual(seen, [0, 1, 0], 'every word shown, back to the first');
    assert.ok(!reveal.classList.contains('isRevealing'), 'stopped within the budget');
    assert.equal(reveal.style.properties.size, 0);
    assert.ok(pause > 0);

    wait(context, 60000);
    assert.equal(shown(words), 0);
    assert.equal(reveal.style.properties.size, 0, 'still');
    controller.disconnect();
});

test('it starts from, and comes back to, the word the server marked as current', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, words } = setUp({ current: 2 });

    controller.connect();
    assert.equal(shown(words), 2);
    wait(context, BUDGET);
    assert.equal(shown(words), 2);
    controller.disconnect();
});

test('with loop, the words keep cycling and the button pauses and plays them', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words, button } = setUp({ loop: true });

    controller.connect();
    assert.equal(button.hidden, false, 'the controller shows the button');
    assert.equal(button.textContent, 'Pause animation');
    assert.equal(reveal.style.properties.get('--phil-reveal-duration'), '200ms', 'the timings are kept');

    wait(context, 200);
    assert.ok(reveal.classList.contains('isPulsing'), 'the caret blinks while the word rests');
    wait(context, 1000);
    assert.equal(size(reveal), '0px', 'the clip closes to its caret');
    assert.equal(shown(words), 0, 'the word changes only once the clip is closed');
    wait(context, 200);
    assert.equal(shown(words), 1);
    assert.equal(size(reveal), open(60));
    wait(context, 200);
    step(context);
    assert.equal(shown(words), 2);
    step(context);
    assert.equal(shown(words), 0, 'back to the first word, and on');
    step(context);
    assert.equal(shown(words), 1);

    wait(context, 200);
    controller.toggle();
    assert.equal(button.textContent, 'Play animation');
    assert.equal(size(reveal), open(60), 'the paused word is fully open');
    assert.ok(!reveal.classList.contains('isPulsing'));
    wait(context, 60000);
    assert.equal(shown(words), 1, 'nothing moves while paused');

    controller.toggle();
    assert.equal(button.textContent, 'Pause animation');
    wait(context, 1000 + 200 + 200);
    assert.equal(shown(words), 2, 'it plays on from the word it held');

    controller.disconnect();
    assert.ok(button.hidden, 'hidden again once the controller lets go');
});

test('hover and focus hold the current word open; the button plays on even under them', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words, root, button } = setUp({ loop: true });

    controller.connect();
    wait(context, 200 + 1000);
    assert.equal(size(reveal), '0px', 'closing');

    root.dispatch('mouseenter');
    assert.equal(size(reveal), open(100), 'hovering reopens the current word');
    wait(context, 60000);
    assert.equal(shown(words), 0);

    root.dispatch('focusin');
    root.dispatch('mouseleave');
    wait(context, 60000);
    assert.equal(shown(words), 0, 'the focus still holds it');

    root.dispatch('focusout', { relatedTarget: button });
    wait(context, 60000);
    assert.equal(shown(words), 0, 'the focus moved within the headline');

    root.dispatch('focusout', { relatedTarget: null });
    wait(context, 1000 + 200 + 200);
    assert.equal(shown(words), 1, 'it goes on once neither holds it');

    root.dispatch('mouseenter');
    root.dispatch('focusin');
    controller.toggle();
    assert.equal(button.textContent, 'Play animation');
    controller.toggle();
    wait(context, 1000 + 200 + 200);
    assert.equal(shown(words), 2, 'pressing play under the pointer and the focus plays at once');

    controller.disconnect();
    assert.equal(root.count(), 0);
});

test('a move ends on transitionend, or after its duration when the event never fires', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words } = setUp({ loop: true });

    controller.connect();
    reveal.dispatch('transitionend');
    assert.ok(reveal.classList.contains('isPulsing'), 'the entrance ended on its event');
    assert.equal(reveal.count(), 0, 'the listener is gone');

    wait(context, 1000);
    assert.equal(size(reveal), '0px');
    reveal.dispatch('transitionend', { target: words[0] });
    assert.equal(shown(words), 0, 'an event from a word is not the clip\'s');
    reveal.dispatch('transitionend');
    assert.equal(shown(words), 1, 'the clip closed, the next word opens');
    assert.equal(size(reveal), open(60));

    wait(context, 200);
    wait(context, 1000);
    assert.equal(size(reveal), '0px', 'closing, and no event will come');
    wait(context, 200);
    assert.equal(shown(words), 2, 'the fallback opens the next word');
    assert.equal(size(reveal), open(80), 'never stuck closed');

    controller.disconnect();
    assert.equal(reveal.count(), 0);
});

test('a hidden document holds the current word fully open, and the cycle goes on when it shows again', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words, doc } = setUp({ loop: true });

    controller.connect();
    wait(context, 200 + 1000);
    assert.equal(size(reveal), '0px');

    doc.hidden = true;
    doc.dispatch('visibilitychange');
    assert.equal(size(reveal), open(100), 'restored fully open');
    assert.ok(!reveal.classList.contains('isPulsing'));
    wait(context, 60000);
    assert.equal(shown(words), 0);

    doc.hidden = false;
    doc.dispatch('visibilitychange');
    wait(context, 1000 + 200 + 200);
    assert.equal(shown(words), 1);

    controller.disconnect();
    assert.equal(doc.count(), 0);
});

test('a document hidden from the start shows the first word open, and plays once it shows', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words, doc } = setUp({ hidden: true });

    controller.connect();
    assert.equal(size(reveal), open(100));
    wait(context, 60000);
    assert.equal(shown(words), 0);

    doc.hidden = false;
    doc.dispatch('visibilitychange');
    wait(context, BUDGET);
    assert.ok(!reveal.classList.contains('isRevealing'), 'played through and stopped');
    assert.equal(shown(words), 0);
    controller.disconnect();
});

test('under prefers-reduced-motion nothing moves, and turning it on stops the cycle', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const still = setUp({ reduced: true, loop: true });

    still.controller.connect();
    assert.ok(!still.reveal.classList.contains('isRevealing'));
    assert.equal(still.reveal.style.properties.size, 0);
    assert.ok(still.button.hidden, 'no button when nothing moves');
    wait(context, 10000);
    assert.equal(shown(still.words), 0);

    still.query.set(false);
    assert.ok(still.reveal.classList.contains('isRevealing'), 'turning it off starts the reveal');
    assert.equal(still.button.hidden, false);

    still.query.set(true);
    assert.ok(!still.reveal.classList.contains('isRevealing'));
    assert.equal(still.reveal.style.properties.size, 0, 'the clip is back to its natural width');
    assert.ok(still.button.hidden);
    wait(context, 10000);
    assert.equal(shown(still.words), 0);

    still.controller.disconnect();
    assert.equal(still.query.listeners.size, 0);
});

test('a resize while a word is open measures it again; disconnecting cleans everything up', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, reveal, words, windowListeners } = setUp({ loop: true });

    controller.connect();
    words[0].offsetWidth = 150;
    windowListeners.get('resize')();
    assert.equal(size(reveal), open(100), 'not while the clip moves');

    wait(context, 200);
    windowListeners.get('resize')();
    assert.equal(size(reveal), open(150));

    controller.disconnect();
    assert.equal(windowListeners.size, 0);
    assert.ok(!reveal.classList.contains('isPulsing'));
    wait(context, 10000);
    assert.equal(shown(words), 0, 'no timer survives');
});

test('disconnecting in the middle of a run puts the server\'s word back, and a reconnection starts from it', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const { controller, words } = setUp({ loop: true });

    controller.connect();
    wait(context, 200);
    step(context);
    assert.equal(shown(words), 1);

    controller.disconnect();
    assert.equal(shown(words), 0);

    controller.connect();
    assert.equal(shown(words), 0);
    wait(context, 200);
    step(context);
    assert.equal(shown(words), 1);
    controller.disconnect();
});

test('without a clip or words, it does nothing', () => {
    const { controller } = setUp();
    controller.hasRevealTarget = false;
    controller.connect();
    controller.disconnect();

    const empty = setUp({ widths: [] });
    empty.controller.connect();
    assert.equal(empty.reveal.style.properties.size, 0);
    empty.controller.disconnect();
});
