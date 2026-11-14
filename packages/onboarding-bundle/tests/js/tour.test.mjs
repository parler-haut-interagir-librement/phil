// The tour controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.

import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import TourController from '../../assets/src/tour_controller.js';

const element = (extra = {}) => {
    const classes = new Set();
    const attributes = {};

    return {
        hidden: false,
        disabled: false,
        textContent: '',
        dataset: {},
        focused: [],
        scrolled: [],
        children: [],
        classList: {
            add: (name) => classes.add(name),
            remove: (name) => classes.delete(name),
            contains: (name) => classes.has(name),
        },
        getAttribute: (name) => attributes[name] ?? null,
        setAttribute: (name, value) => {
            attributes[name] = value;
        },
        focus(options) {
            this.focused.push(options);
        },
        scrollIntoView(options) {
            this.scrolled.push(options);
        },
        ...extra,
    };
};

const step = (id, state, { selector = '', done = '', skip = '' } = {}) => ({
    dataset: { id, state, selector, doneUrl: done, skipUrl: skip },
    content: { cloneNode: () => ({ heading: { id: null, textContent: ` Title ${id} ` } }) },
});

let requests;
let targets;
let original;

beforeEach(() => {
    requests = [];
    targets = { '#profile': element(), '#page': element() };
    original = { fetch: globalThis.fetch, document: globalThis.document, matchMedia: globalThis.matchMedia };
    globalThis.fetch = (url, options) => {
        requests.push([url, options]);

        return Promise.resolve({ ok: url !== '/fails', status: url === '/fails' ? 500 : 200 });
    };
    globalThis.document = {
        activeElement: element(),
        documentElement: { dataset: {} },
        querySelector: (selector) => {
            if (selector === '[bad') {
                throw new SyntaxError('bad selector');
            }

            return targets[selector] ?? null;
        },
    };
    globalThis.matchMedia = () => ({ matches: false });
});

afterEach(() => {
    globalThis.fetch = original.fetch;
    globalThis.document = original.document;
    globalThis.matchMedia = original.matchMedia;
});

const controller = (steps, autostart = false) => {
    const instance = new TourController();
    const content = element();
    content.replaceChildren = (node) => {
        content.node = node;
    };
    content.querySelector = () => content.node?.heading ?? null;
    const panel = element({ hidden: true });
    panel.setAttribute('aria-labelledby', 'tour-title');

    Object.assign(instance, {
        stepTargets: steps,
        panelTarget: panel,
        contentTarget: content,
        progressTarget: element(),
        previousTarget: element(),
        nextTarget: element(),
        skipTarget: element(),
        statusTarget: element(),
        flowValue: 'author',
        tokenValue: 'good',
        headerValue: 'X-Phil-Onboarding-Token',
        dismissUrlValue: '/dismiss',
        autostartValue: autostart,
        progressValue: 'Step %current% of %total%',
        nextValue: 'Next',
        finishValue: 'Finish',
    });

    return instance;
};

test('the tour starts at the first pending step when the page loads', () => {
    const instance = controller(
        [step('a', 'completed'), step('b', 'pending', { selector: '#profile' }), step('c', 'pending')],
        true,
    );
    instance.connect();

    assert.equal(instance.index, 1);
    assert.equal(instance.panelTarget.hidden, false);
    assert.equal(instance.progressTarget.textContent, 'Step 2 of 3');
    assert.equal(instance.statusTarget.textContent, 'Step 2 of 3: Title b');
    assert.equal(instance.contentTarget.node.heading.id, 'tour-title');
    assert.equal(instance.previousTarget.disabled, false);
    assert.equal(instance.nextTarget.textContent, 'Next');
    assert.equal(instance.skipTarget.hidden, true);
    assert.equal(targets['#profile'].classList.contains('phil-onboarding-highlight'), true);
    assert.deepEqual(targets['#profile'].scrolled, [{ block: 'center', behavior: 'smooth' }]);
    assert.deepEqual(instance.panelTarget.focused, [{ preventScroll: true }]);
    assert.deepEqual(instance.dispatched, [['shown', { flow: 'author', step: 'b' }]]);
});

test('without autostart, or without a pending step, the tour waits', () => {
    const waiting = controller([step('a', 'pending')]);
    waiting.connect();
    assert.equal(waiting.panelTarget.hidden, true);

    const done = controller([step('a', 'completed')], true);
    done.connect();
    assert.equal(done.panelTarget.hidden, true);
    assert.equal(done.index, 0);

    const empty = controller([], true);
    empty.connect();
    empty.start();
    assert.equal(empty.panelTarget.hidden, true);
});

test('next marks a step done by hand and moves on, then finishes on the last step', async () => {
    const instance = controller([step('a', 'pending', { done: '/a/done' }), step('b', 'pending')]);
    instance.connect();
    instance.start();
    let prevented = 0;
    instance.next({ preventDefault: () => (prevented += 1) });

    assert.equal(prevented, 1);
    assert.deepEqual(requests, [
        [
            '/a/done',
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Phil-Onboarding-Token': 'good' },
            },
        ],
    ]);
    assert.equal(instance.stepTargets[0].dataset.state, 'completed');
    assert.equal(instance.stepTargets[0].dataset.doneUrl, '');
    assert.equal(instance.index, 1);
    assert.equal(instance.nextTarget.textContent, 'Finish');

    instance.next();
    assert.equal(requests.length, 1, 'a step without a URL sends nothing');
    assert.equal(instance.panelTarget.hidden, true);
    assert.deepEqual(instance.dispatched.slice(-2), [
        ['closed', { flow: 'author' }],
        ['finished', { flow: 'author' }],
    ]);
    assert.deepEqual(globalThis.document.activeElement.focused, [{ preventScroll: true }]);
});

test('previous goes back and stops at the first step', () => {
    const instance = controller([step('a', 'pending'), step('b', 'pending')]);
    instance.start();
    instance.show(1);
    instance.previous({ preventDefault: () => {} });
    assert.equal(instance.index, 0);
    assert.equal(instance.previousTarget.disabled, true);
    instance.previous();
    assert.equal(instance.index, 0);
    instance.show(5);
    assert.equal(instance.index, 1);
});

test('skip marks a step skipped when it may be, and moves on', () => {
    const instance = controller([step('a', 'pending', { skip: '/a/skip' }), step('b', 'pending')]);
    instance.connect();
    instance.start();
    assert.equal(instance.skipTarget.hidden, false);
    instance.skip({ preventDefault: () => {} });

    assert.equal(requests[0][0], '/a/skip');
    assert.equal(instance.stepTargets[0].dataset.state, 'skipped');
    assert.equal(instance.index, 1);

    instance.skip();
    assert.equal(requests.length, 1);
    assert.equal(instance.panelTarget.hidden, true);
});

test('end dismisses the flow; Escape only closes the tour', () => {
    const instance = controller([step('a', 'pending', { selector: '#page' })]);
    instance.start();
    instance.end({ preventDefault: () => {} });

    assert.equal(requests[0][0], '/dismiss');
    assert.equal(instance.panelTarget.hidden, true);
    assert.equal(targets['#page'].classList.contains('phil-onboarding-highlight'), false);

    instance.start();
    let prevented = 0;
    instance.keydown({ key: 'Escape', preventDefault: () => (prevented += 1), target: element() });
    assert.equal(prevented, 1);
    assert.equal(instance.panelTarget.hidden, true);
    assert.equal(requests.length, 1);

    instance.keydown({ key: 'Escape', preventDefault: () => (prevented += 1) });
    assert.equal(prevented, 1, 'a closed tour lets the keys be');
});

test('the arrows move between steps, except in a field or once handled', () => {
    const instance = controller([step('a', 'pending'), step('b', 'pending'), step('c', 'pending')]);
    instance.start();
    const key = (name, target = element({ tagName: 'BUTTON' }), defaultPrevented = false) => {
        let prevented = false;
        instance.keydown({ key: name, target, defaultPrevented, preventDefault: () => (prevented = true) });

        return prevented;
    };

    assert.equal(key('ArrowRight'), true);
    assert.equal(instance.index, 1);
    assert.equal(key('ArrowLeft'), true);
    assert.equal(instance.index, 0);
    assert.equal(key('ArrowRight', element({ tagName: 'INPUT' })), false);
    assert.equal(key('ArrowRight', element({ tagName: 'TEXTAREA' })), false);
    assert.equal(key('ArrowLeft', element({ tagName: 'SELECT' })), false);
    assert.equal(key('ArrowRight', element({ isContentEditable: true })), false);
    assert.equal(key('ArrowRight', element(), true), false);
    assert.equal(key('Enter'), false);
    assert.equal(instance.index, 0);
});

test('reduced motion scrolls at once, by the system or the root element', () => {
    globalThis.matchMedia = (query) => ({ matches: query === '(prefers-reduced-motion: reduce)' });
    const instance = controller([step('a', 'pending', { selector: '#profile' })]);
    instance.start();
    assert.deepEqual(targets['#profile'].scrolled.at(-1), { block: 'center', behavior: 'auto' });

    globalThis.matchMedia = undefined;
    globalThis.document.documentElement.dataset.motion = 'reduce';
    instance.show(0);
    assert.deepEqual(targets['#profile'].scrolled.at(-1), { block: 'center', behavior: 'auto' });

    globalThis.document.documentElement.dataset.motion = undefined;
    instance.show(0);
    assert.deepEqual(targets['#profile'].scrolled.at(-1), { block: 'center', behavior: 'smooth' });
});

test('a missing or malformed target highlights nothing', () => {
    const instance = controller([
        step('a', 'pending', { selector: '#missing' }),
        step('b', 'pending', { selector: '[bad' }),
    ]);
    instance.start();
    assert.equal(instance.highlighted, null);
    instance.show(1);
    assert.equal(instance.highlighted, null);
    instance.disconnect();
});

test('a change that fails is announced and the tour goes on', async () => {
    const instance = controller([step('a', 'pending', { done: '/fails' }), step('b', 'pending')]);
    instance.start();
    await instance.send('/fails');
    assert.deepEqual(instance.dispatched.at(-1), ['failed', { url: '/fails', status: 500 }]);

    globalThis.fetch = () => Promise.reject(new TypeError('offline'));
    await instance.send('/a/done');
    assert.deepEqual(instance.dispatched.at(-1), ['failed', { url: '/a/done', status: 0 }]);
});

test('a step without a heading still shows its progress', () => {
    const instance = controller([{ dataset: { id: 'a', state: 'pending' }, content: { cloneNode: () => ({}) } }]);
    instance.start();
    assert.equal(instance.statusTarget.textContent, 'Step 1 of 1: ');
});
