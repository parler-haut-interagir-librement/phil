// The reveal controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { afterEach, test } from 'node:test';
import assert from 'node:assert/strict';

import RevealController from '../../assets/src/reveal_controller.js';

const original = globalThis.matchMedia;

afterEach(() => {
    globalThis.matchMedia = original;
});

const controller = (withContent = true) => {
    const instance = new RevealController();
    const calls = [];
    instance.detailsTarget = { open: false };
    instance.hasContentTarget = withContent;
    instance.contentTarget = {
        focus: (options) => calls.push(['focus', options]),
        scrollIntoView: (options) => calls.push(['scroll', options]),
    };
    instance.calls = calls;

    return instance;
};

test('opening and closing the details dispatch revealed and concealed', () => {
    const instance = controller();
    instance.detailsTarget.open = true;
    instance.toggle();
    instance.detailsTarget.open = false;
    instance.toggle();

    assert.deepEqual(instance.dispatched, [['revealed', undefined], ['concealed', undefined]]);
});

test('reveal opens the content, focuses it and scrolls smoothly', () => {
    globalThis.matchMedia = (query) => ({ matches: query !== '(prefers-reduced-motion: reduce)' });
    const instance = controller();
    let prevented = 0;
    instance.reveal({ preventDefault: () => (prevented += 1) });

    assert.equal(instance.detailsTarget.open, true);
    assert.equal(prevented, 1);
    assert.deepEqual(instance.calls, [
        ['focus', { preventScroll: true }],
        ['scroll', { block: 'nearest', behavior: 'smooth' }],
    ]);
});

test('reveal scrolls at once for reduced motion', () => {
    globalThis.matchMedia = (query) => ({ matches: query === '(prefers-reduced-motion: reduce)' });
    const instance = controller();
    instance.reveal();

    assert.deepEqual(instance.calls[1], ['scroll', { block: 'nearest', behavior: 'auto' }]);
});

test('without matchMedia, motion is allowed; without content, only the details open', () => {
    globalThis.matchMedia = undefined;
    const instance = controller();
    instance.reveal();
    assert.deepEqual(instance.calls[1], ['scroll', { block: 'nearest', behavior: 'smooth' }]);

    const empty = controller(false);
    empty.reveal();
    assert.equal(empty.detailsTarget.open, true);
    assert.deepEqual(empty.calls, []);
});

test('conceal closes the content', () => {
    const instance = controller();
    instance.detailsTarget.open = true;
    let prevented = 0;
    instance.conceal({ preventDefault: () => (prevented += 1) });
    instance.conceal();

    assert.equal(instance.detailsTarget.open, false);
    assert.equal(prevented, 1);
});
