import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import PopoverController from '../../popover/assets/controllers/popover_controller.js';
import { browser, FakeElement, keydown, stimulate } from './fake.mjs';

let listeners;
beforeEach(() => {
    listeners = browser();
});

const setUp = ({ open = false, name = '' } = {}) => {
    const trigger = new FakeElement({
        attributes: { popovertarget: 'p' },
        rect: { top: 100, bottom: 140, left: 100, right: 200 },
    });
    const field = new FakeElement();
    const content = new FakeElement({ dataset: { side: 'bottom', align: 'start' } });
    content.selectors = {
        '[autofocus], input:not([type="hidden"]), select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])':
            field,
    };
    const element = new FakeElement({ children: [trigger, content] });
    const controller = stimulate(PopoverController, {
        element,
        targets: { trigger: [trigger], content: [content] },
        values: { open, name },
    });

    return { controller, trigger, content, field, element };
};

test('the invoker is left to the browser, any other trigger toggles the value', () => {
    const { controller, trigger, content } = setUp();
    controller.connect();
    controller.toggle({ currentTarget: trigger });
    assert.equal(controller.openValue, false);

    controller.toggle({ currentTarget: new FakeElement() });
    assert.equal(controller.openValue, true);
    assert.equal(content.popoverOpen, true);

    controller.close();
    assert.equal(content.popoverOpen, false);
});

test('open at connection shows the panel without moving the focus', () => {
    const { controller, content, field } = setUp({ open: true });
    FakeElement.focused = null;
    controller.connect();
    assert.equal(content.popoverOpen, true);
    assert.equal(content.dataset.state, 'open');
    assert.notEqual(FakeElement.focused, field);
});

test('the toggle of the browser syncs the state, places the panel, moves the focus and follows the page', () => {
    const { controller, trigger, content, element, field } = setUp();
    controller.connect();
    content.popoverOpen = true;
    controller.sync({ newState: 'open' });

    assert.equal(controller.openValue, true);
    assert.equal(element.dataset.state, 'open');
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    assert.equal(content.style.inset, '146px auto auto 100px');
    assert.equal(content.dataset.placed, 'bottom');
    assert.equal(FakeElement.focused, field);
    assert.ok(listeners.window.has('scroll'));
    listeners.window.get('resize')();

    content.popoverOpen = false;
    controller.sync({ newState: 'closed' });
    assert.equal(controller.openValue, false);
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');
    assert.equal(listeners.window.size, 0);
});

test('a named popover announces itself and closes for another of its group', () => {
    const { controller, element } = setUp({ name: 'g' });
    controller.connect();
    controller.openValue = true;
    assert.equal(globalThis.lastWindowEvent.type, 'popover:open');
    assert.deepEqual(globalThis.lastWindowEvent.detail, { name: 'g', source: element });

    controller.handleGroupOpen({ detail: { name: 'other', source: {} } });
    assert.equal(controller.openValue, true);
    controller.handleGroupOpen({ detail: { name: 'g', source: element } });
    assert.equal(controller.openValue, true);
    controller.handleGroupOpen({ detail: { name: 'g', source: {} } });
    assert.equal(controller.openValue, false);
});

test('a click outside and Escape close it, Escape gives the focus back', () => {
    const { controller, trigger, element } = setUp();
    controller.connect();
    controller.openValue = true;
    controller.handleOutsideClick({ target: element });
    assert.equal(controller.openValue, true);
    controller.handleOutsideClick({ target: new FakeElement() });
    assert.equal(controller.openValue, false);

    controller.handleEscape(keydown('Escape', element));
    assert.notEqual(FakeElement.focused, trigger);

    controller.openValue = true;
    controller.handleEscape(keydown('Escape', element));
    assert.equal(controller.openValue, false);
    assert.equal(FakeElement.focused, trigger);
});

test('disconnect stops following the page', () => {
    const { controller, content } = setUp();
    controller.connect();
    content.popoverOpen = true;
    controller.sync({ newState: 'open' });
    controller.disconnect();
    assert.equal(listeners.window.size, 0);
});
