import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import SelectController, { typeahead } from '../../select/assets/controllers/select_controller.js';
import { browser, FakeElement, keydown, stimulate } from './fake.mjs';

let listeners;
beforeEach(() => {
    listeners = browser();
});

const item = (value, label, disabled = false) => {
    const text = Object.assign(new FakeElement(), { textContent: ` ${label} ` });
    const indicator = new FakeElement();
    const element = new FakeElement({ dataset: { value, disabled: String(disabled) } });
    element.selectors = { '[data-slot="select-item-text"]': text, '[data-slot="select-item-indicator"]': indicator };
    element.indicator = indicator;

    return element;
};

const setUp = ({ value = '', open = false, hidden = true } = {}) => {
    const trigger = new FakeElement({
        attributes: { popovertarget: 'c' },
        rect: { top: 0, bottom: 40, left: 0, right: 100 },
    });
    const valueTarget = new FakeElement({ dataset: { placeholder: 'Pick' } });
    const content = new FakeElement({ dataset: { side: 'bottom', align: 'start' } });
    const viewport = Object.assign(new FakeElement(), {
        scrollTop: 0,
        clientHeight: 100,
        scrollHeight: 300,
        scrolled: [],
        scrollBy(options) {
            this.scrolled.push(options);
        },
    });
    const items = [item('a', 'Apple'), item('b', 'Banana', true), item('c', 'Cherry')];
    const up = new FakeElement();
    const down = new FakeElement();
    const hiddenSelect = Object.assign(new FakeElement(), { options: [{ value: '', textContent: '' }], value: '' });
    const controller = stimulate(SelectController, {
        targets: {
            trigger: [trigger],
            value: [valueTarget],
            content: [content],
            viewport: [viewport],
            item: items,
            hiddenSelect: hidden ? [hiddenSelect] : [],
            scrollUpButton: [up],
            scrollDownButton: [down],
        },
        values: { open, value },
    });

    return { controller, trigger, valueTarget, content, viewport, items, up, down, hiddenSelect };
};

test('typeahead ignores case', () => {
    assert.equal(typeahead(['Apple', 'Cherry'], 'c'), 1);
    assert.equal(typeahead(['Apple', 'Cherry'], 'CH'), 1);
    assert.equal(typeahead(['Apple'], 'z'), -1);
});

test('the value is shown, written in the form, and marked on its option', () => {
    const { controller, items, valueTarget, hiddenSelect, trigger } = setUp({ value: 'c' });
    controller.connect();
    assert.equal(valueTarget.textContent, 'Cherry');
    assert.equal(hiddenSelect.options[0].value, 'c');
    assert.equal(hiddenSelect.options[0].textContent, 'Cherry');
    assert.equal(hiddenSelect.value, 'c');
    assert.equal(items[2].getAttribute('aria-selected'), 'true');
    assert.equal(items[2].indicator.hidden, false);
    assert.equal(items[0].indicator.hidden, true);
    assert.equal(trigger.dataset.hasValue, 'true');
    assert.equal(hiddenSelect.calls.length, 0);
});

test('without a value the placeholder shows', () => {
    const { controller, valueTarget, trigger } = setUp();
    controller.connect();
    assert.equal(valueTarget.textContent, 'Pick');
    assert.equal(trigger.dataset.hasValue, 'false');
});

test('opened by the invoker, the focus goes on the selected option, or the first enabled', () => {
    const { controller, trigger, content, items } = setUp({ value: 'c' });
    controller.connect();
    controller.toggle({ currentTarget: trigger });
    assert.equal(controller.openValue, false);
    content.popoverOpen = true;
    controller.sync({ newState: 'open' });
    assert.equal(controller.openValue, true);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    assert.equal(FakeElement.focused, items[2]);
    assert.ok(listeners.window.has('scroll'));
    assert.equal(content.style.inset, '46px auto auto 0px');

    content.popoverOpen = false;
    controller.sync({ newState: 'closed' });
    assert.equal(listeners.window.size, 0);
    assert.equal(trigger.dataset.state, 'closed');
});

test('another trigger toggles through the value', () => {
    const { controller, content, items, trigger } = setUp();
    controller.connect();
    controller.toggle({ currentTarget: new FakeElement() });
    assert.equal(content.popoverOpen, true);
    assert.equal(FakeElement.focused, items[0]);
    controller.toggle({ currentTarget: new FakeElement() });
    assert.equal(content.popoverOpen, false);
    assert.equal(FakeElement.focused, trigger);
});

test('open at connection without moving the focus', () => {
    const { controller, content } = setUp({ open: true });
    FakeElement.focused = null;
    controller.connect();
    assert.equal(content.popoverOpen, true);
    assert.equal(FakeElement.focused, null);
    controller.open({ focus: false });
    controller.close({ focus: false });
    assert.equal(FakeElement.focused, null);
});

test('choosing writes the value, says so, tells the form and closes', () => {
    const { controller, items, hiddenSelect, valueTarget, trigger } = setUp();
    controller.connect();
    controller.open();
    controller.selectItem({ currentTarget: items[2] });
    assert.equal(controller.valueValue, 'c');
    assert.deepEqual(controller.dispatched[0], ['change', { detail: { value: 'c', label: 'Cherry' }, bubbles: true }]);
    assert.equal(valueTarget.textContent, 'Cherry');
    assert.deepEqual(hiddenSelect.calls, [['dispatchEvent', 'change']]);
    assert.equal(controller.openValue, false);
    assert.equal(FakeElement.focused, trigger);
});

test('a disabled option is not chosen nor highlighted', () => {
    const { controller, items } = setUp();
    controller.connect();
    FakeElement.focused = null;
    controller.selectItem({ currentTarget: items[1] });
    controller.highlightItem({ currentTarget: items[1] });
    assert.equal(controller.valueValue, '');
    assert.equal(FakeElement.focused, null);
});

test('the pointer moves the focus, leaving gives it to the list', () => {
    const { controller, items, content } = setUp();
    controller.connect();
    controller.highlightItem({ currentTarget: items[0] });
    assert.equal(FakeElement.focused, items[0]);
    controller.unhighlightItem();
    assert.equal(FakeElement.focused, content);
});

test('the arrows open from the trigger', () => {
    const { controller, trigger } = setUp();
    controller.connect();
    const event = keydown('ArrowDown', trigger);
    controller.onTriggerKeydown(event);
    assert.equal(event.prevented, true);
    assert.equal(controller.openValue, true);
    const other = keydown('a', trigger);
    controller.onTriggerKeydown(other);
    assert.equal(other.prevented, false);
});

test('the keys inside the list', () => {
    const { controller, items, content } = setUp();
    controller.connect();
    const press = (key, target) => controller.onContentKeydown(keydown(key, target));

    press('ArrowDown', items[0]);
    assert.equal(FakeElement.focused, items[2]);
    press('ArrowDown', items[2]);
    assert.equal(FakeElement.focused, items[0]);
    press('ArrowUp', items[0]);
    assert.equal(FakeElement.focused, items[2]);
    press('ArrowUp', content);
    assert.equal(FakeElement.focused, items[2]);
    press('ArrowUp', items[2]);
    assert.equal(FakeElement.focused, items[0]);
    press('End', items[0]);
    assert.equal(FakeElement.focused, items[2]);
    press('Home', items[2]);
    assert.equal(FakeElement.focused, items[0]);

    press('Enter', content);
    assert.equal(controller.valueValue, '');
    press(' ', items[2]);
    assert.equal(controller.valueValue, 'c');

    controller.open();
    press('Tab', items[0]);
    assert.equal(controller.openValue, false);
});

test('typing goes to the option, letters adding up', () => {
    const { controller, items } = setUp();
    controller.connect();
    controller.onContentKeydown(keydown('c', items[0]));
    assert.equal(FakeElement.focused, items[2]);
    FakeElement.focused = null;
    controller.onContentKeydown(keydown('Shift', items[0]));
    controller.onContentKeydown(keydown('c', items[0], { ctrlKey: true }));
    assert.equal(FakeElement.focused, null);
    controller.disconnect();
});

test('the scroll buttons show where there is more to see', () => {
    const { controller, viewport, up, down } = setUp();
    controller.connect();
    controller.onViewportScroll();
    assert.equal(up.hidden, true);
    assert.equal(down.hidden, false);
    viewport.scrollTop = 200;
    controller.onViewportScroll();
    assert.equal(up.hidden, false);
    assert.equal(down.hidden, true);
    controller.scrollUp();
    controller.scrollDown();
    assert.deepEqual(viewport.scrolled, [{ top: -96 }, { top: 96 }]);
});

test('without a hidden field and an option without text', () => {
    const { controller, items } = setUp({ hidden: false, value: 'a' });
    items[0].selectors = {};
    controller.connect();
    assert.equal(items[0].getAttribute('aria-selected'), 'true');
});
