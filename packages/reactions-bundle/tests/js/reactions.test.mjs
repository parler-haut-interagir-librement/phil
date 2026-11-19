// The reactions controller, under Node's test runner, its peers resolved to stubs by `register.mjs`.

import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import ReactionsController from '../../assets/src/reactions_controller.js';
import { component } from './live.stub.mjs';

globalThis.CSS = { escape: (value) => value };

const control = (key) => ({
    dataset: { philFocus: key },
    focused: 0,
    closest(selector) {
        return selector === '[data-phil-focus]' ? this : null;
    },
    focus() {
        this.focused += 1;
    },
});

const page = (controls, activeElement = null, owns = true) => ({
    ownerDocument: { activeElement },
    contains(node) {
        return (owns && node === activeElement) || controls.includes(node);
    },
    querySelector(selector) {
        return controls.find((one) => selector === `[data-phil-focus="${one.dataset.philFocus}"]`) ?? null;
    },
});

const controller = async (element) => {
    const reactions = new ReactionsController();
    reactions.element = element;
    reactions.contentValue = 'article:1';
    reactions.instanceValue = 'me';
    await reactions.connect();

    return reactions;
};

beforeEach(() => {
    component.handlers = {};
    component.rendered = 0;
});

test('the control that had the focus gets it back', async () => {
    const like = control('reaction:like');
    await controller(page([like], like));

    component.handlers['render:started']();
    component.handlers['render:finished']();

    assert.equal(like.focused, 1);
});

test('nothing is focused when nothing had the focus', async () => {
    const like = control('reaction:like');
    await controller(page([like]));

    component.handlers['render:started']();
    component.handlers['render:finished']();

    assert.equal(like.focused, 0);
});

test('the focus in another component of the same content is left to it', async () => {
    const theirs = control('reaction:like');
    const mine = control('reaction:like');
    const reactions = await controller(page([mine], theirs, false));

    component.handlers['render:started']();
    component.handlers['render:finished']();

    assert.equal(reactions.focused, null);
    assert.equal(mine.focused, 0);
    assert.equal(theirs.focused, 0);
});

test('a control gone after the render is left alone', async () => {
    const like = control('reaction:like');
    const reactions = await controller(page([], like));

    component.handlers['render:started']();

    assert.doesNotThrow(() => component.handlers['render:finished']());
    assert.equal(reactions.focused, 'reaction:like');
});

test('the hooks are taken off with the controller', async () => {
    const reactions = await controller(page([]));

    reactions.disconnect();

    assert.deepEqual(component.handlers, {});
});

test('another component of the same content draws itself again', async () => {
    const reactions = await controller(page([]));

    reactions.refresh({ detail: { content: 'article:1', instance: 'other' } });

    assert.equal(component.rendered, 1);
});

test('the component that changed and the other contents do not', async () => {
    const reactions = await controller(page([]));

    reactions.refresh({ detail: { content: 'article:1', instance: 'me' } });
    reactions.refresh({ detail: { content: 'article:2', instance: 'other' } });

    assert.equal(component.rendered, 0);
});
