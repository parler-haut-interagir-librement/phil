// The frames blocked until their consent, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { afterEach, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';

import EmbedController from '../../assets/src/embed_controller.js';

const controller = ({ frame = true, once = true } = {}) => {
    const instance = new EmbedController();
    instance.categoryValue = 'videos';
    instance.hasFrameTarget = frame;
    instance.frameTarget = { content: 'the iframe' };
    instance.hasOnceTarget = once;
    instance.onceTarget = { hidden: true };
    instance.element = {
        replacedWith: null,
        replaceWith(node) {
            this.replacedWith = node;
        },
    };

    return instance;
};

beforeEach(() => {
    globalThis.document = { importNode: (content, deep) => (deep ? `imported ${content}` : null) };
});

afterEach(() => {
    delete globalThis.document;
});

test('the button that loads one frame shows only with JavaScript', () => {
    const instance = controller();
    instance.connect();

    assert.equal(instance.onceTarget.hidden, false);
    assert.doesNotThrow(() => controller({ once: false }).connect());
});

test('the frame replaces its placeholder when its category is granted', () => {
    const instance = controller();
    instance.changed({ detail: { granted: ['analytics', 'videos'] } });

    assert.equal(instance.element.replacedWith, 'imported the iframe');
});

test('another category leaves the placeholder', () => {
    const instance = controller();
    instance.changed({ detail: { granted: ['analytics'] } });

    assert.equal(instance.element.replacedWith, null);
});

test('loading once needs no consent', () => {
    const instance = controller();
    instance.once();

    assert.equal(instance.element.replacedWith, 'imported the iframe');
});

test('without its template nothing is loaded', () => {
    const instance = controller({ frame: false });
    instance.once();

    assert.equal(instance.element.replacedWith, null);
});
