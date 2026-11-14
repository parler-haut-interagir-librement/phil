// The table controller, under Node's test runner, its peers resolved to stubs by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.

import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import TableController from '../../assets/src/table_controller.js';
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

const page = (controls, autofocus = null, activeElement = null) => ({
    ownerDocument: { activeElement },
    querySelector(selector) {
        if (selector === '[autofocus]') {
            return autofocus;
        }

        return controls.find((one) => selector === `[data-phil-focus="${one.dataset.philFocus}"]`) ?? null;
    },
});

const controller = async (element) => {
    const table = new TableController();
    table.element = element;
    await table.connect();

    return table;
};

beforeEach(() => {
    component.handlers = {};
});

test('the control that had the focus gets it back', async () => {
    const sort = control('sort:title');
    await controller(page([sort], null, sort));

    component.handlers['render:started']();
    component.handlers['render:finished']();

    assert.equal(sort.focused, 1);
});

test('what the server asks to focus goes first', async () => {
    const sort = control('sort:title');
    const input = control('edit:1:title');
    await controller(page([sort, input], input, sort));

    component.handlers['render:started']();
    component.handlers['render:finished']();

    assert.equal(input.focused, 1);
    assert.equal(sort.focused, 0);
});

test('nothing moves when the focus was elsewhere', async () => {
    const sort = control('sort:title');
    await controller(page([sort], null, { closest: () => null }));

    component.handlers['render:started']();
    component.handlers['render:finished']();

    assert.equal(sort.focused, 0);
});

test('a control that is gone leaves the focus alone', async () => {
    const gone = control('edit:1:title');
    await controller(page([], null, gone));

    component.handlers['render:started']();
    assert.doesNotThrow(() => component.handlers['render:finished']());
    assert.equal(gone.focused, 0);
});

test('disconnecting removes the hooks', async () => {
    const table = await controller(page([]));

    table.disconnect();

    assert.deepEqual(component.handlers, {});
});

test('disconnecting before the component is there is harmless', () => {
    assert.doesNotThrow(() => new TableController().disconnect());
});

test('Escape follows the cancel link', () => {
    const table = new TableController();
    let clicked = 0;
    let prevented = 0;
    Object.defineProperty(table, 'hasCancelTarget', { value: true });
    Object.defineProperty(table, 'cancelTarget', { value: { click: () => (clicked += 1) } });

    table.cancel({ preventDefault: () => (prevented += 1) });

    assert.deepEqual([clicked, prevented], [1, 1]);
});

test('Escape without a cancel link does nothing more', () => {
    const table = new TableController();
    let prevented = 0;
    Object.defineProperty(table, 'hasCancelTarget', { value: false });

    table.cancel({ preventDefault: () => (prevented += 1) });

    assert.equal(prevented, 1);
});

const dialog = (modal = false) => ({
    open: true,
    modal,
    calls: [],
    matches(selector) {
        return selector === ':modal' && this.modal;
    },
    close() {
        this.calls.push('close');
        this.open = false;
    },
    showModal() {
        this.calls.push('showModal');
        this.open = true;
        this.modal = true;
    },
});

const withDialog = (table, state) => {
    Object.defineProperty(table, 'hasDialogTarget', { get: () => state.dialog !== null, configurable: true });
    Object.defineProperty(table, 'dialogTarget', { get: () => state.dialog, configurable: true });
};

test('a question drawn open is made modal, and the focus goes back to what asked once it is gone', async () => {
    const ask = control('ask:delete:3');
    const cancel = control('cancel');
    const state = { dialog: null };
    let autofocus = null;
    const element = page([ask], null, ask);
    element.querySelector = (selector) => {
        if (selector === '[autofocus]') {
            return autofocus;
        }

        return selector === '[data-phil-focus="ask:delete:3"]' ? ask : null;
    };
    const table = new TableController();
    table.element = element;
    withDialog(table, state);
    await table.connect();

    component.handlers['render:started']();
    state.dialog = dialog();
    autofocus = cancel;
    component.handlers['render:finished']();

    assert.deepEqual(state.dialog.calls, ['close', 'showModal']);
    assert.equal(cancel.focused, 1);

    element.ownerDocument.activeElement = cancel;
    component.handlers['render:started']();
    state.dialog = null;
    autofocus = null;
    component.handlers['render:finished']();

    assert.equal(ask.focused, 1);
    assert.equal(table.returnTo, null);
});

test('a dialog already modal, or closed, is left as it is', async () => {
    const state = { dialog: dialog(true) };
    const table = new TableController();
    table.element = page([]);
    withDialog(table, state);
    await table.connect();

    assert.deepEqual(state.dialog.calls, []);

    state.dialog = { ...dialog(), open: false, calls: [] };
    table.showDialog();
    assert.deepEqual(state.dialog.calls, []);
});

test('a browser without showModal keeps the dialog in the page', async () => {
    const state = { dialog: { open: true, matches: () => false } };
    const table = new TableController();
    table.element = page([]);
    withDialog(table, state);

    assert.doesNotThrow(() => table.showDialog());
});

test('a question shown on the first render remembers nothing to go back to', async () => {
    const state = { dialog: dialog() };
    const table = new TableController();
    table.element = page([]);
    withDialog(table, state);
    await table.connect();

    assert.equal(table.returnTo, null);
    assert.deepEqual(state.dialog.calls, ['close', 'showModal']);
});

test('Escape on the question follows the dismiss link', () => {
    const table = new TableController();
    let clicked = 0;
    let prevented = 0;
    Object.defineProperty(table, 'hasDismissTarget', { value: true });
    Object.defineProperty(table, 'dismissTarget', { value: { click: () => (clicked += 1) } });

    table.dismiss({ preventDefault: () => (prevented += 1) });

    assert.deepEqual([clicked, prevented], [1, 1]);

    const alone = new TableController();
    Object.defineProperty(alone, 'hasDismissTarget', { value: false });
    alone.dismiss({ preventDefault: () => (prevented += 1) });
    assert.equal(prevented, 2);
});
