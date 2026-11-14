// The document controller, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import DocumentController, { written } from '../../assets/src/document_controller.js';

const field = (name, fallback) => ({
    getAttribute: (attribute) => ({ 'data-attribute': name, 'data-default': fallback })[attribute] ?? null,
});

const input = (value, { type = 'radio', checked = true, of = field('motion', 'system') } = {}) => ({
    type,
    value,
    checked,
    closest: (selector) => (selector === '[data-attribute]' ? of : null),
});

test('a checked choice writes its attribute, the default removes it', () => {
    assert.deepEqual(written(input('reduce')), { name: 'data-motion', value: 'reduce' });
    assert.deepEqual(written(input('system')), { name: 'data-motion', value: null });
});

test('anything else writes nothing', () => {
    assert.equal(written(input('reduce', { checked: false })), null, 'an unchecked radio');
    assert.equal(written(input('on', { type: 'checkbox' })), null, 'a checkbox');
    assert.equal(written(input('reduce', { of: null })), null, 'a field without attribute');
    assert.equal(written({ type: 'radio', checked: true, value: 'x' }), null, 'an element without closest()');
    assert.equal(written(null), null);
});

const root = () => {
    const attributes = new Map([['data-motion', 'reduce']]);

    return {
        attributes,
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: (name) => attributes.delete(name),
    };
};

test('applying a choice sets or removes the attribute of the root element', () => {
    const html = root();
    const controller = new DocumentController();
    controller.element = { ownerDocument: { documentElement: html } };

    controller.apply({ target: input('larger', { of: field('text-size', 'system') }) });
    assert.equal(html.attributes.get('data-text-size'), 'larger');

    controller.apply({ target: input('system') });
    assert.equal(html.attributes.has('data-motion'), false);

    controller.apply({ target: input('reduce', { checked: false }) });
    assert.equal(html.attributes.has('data-motion'), false, 'an unchecked radio changes nothing');
});
