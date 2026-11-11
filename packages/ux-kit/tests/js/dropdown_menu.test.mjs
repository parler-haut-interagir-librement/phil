import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { setTimeout as sleep } from 'node:timers/promises';

import DropdownMenuController from '../../dropdown-menu/assets/controllers/dropdown_menu_controller.js';
import DropdownMenuSubController from '../../dropdown-menu/assets/controllers/dropdown_menu_sub_controller.js';
import { browser, FakeElement, keydown, stimulate } from './fake.mjs';

let listeners;
beforeEach(() => {
    listeners = browser();
});

const makeItem = (
    menu,
    label,
    { disabled = false, closeOnSelect = 'true', tagName = 'BUTTON', attributes = {} } = {},
) =>
    Object.assign(new FakeElement({ attributes, tagName, dataset: { disabled: String(disabled), closeOnSelect } }), {
        parentMenu: menu,
        textContent: label,
    });

const setUp = ({ open = false } = {}) => {
    const trigger = new FakeElement({
        attributes: { popovertarget: 'c' },
        rect: { top: 0, bottom: 40, left: 0, right: 100 },
    });
    const content = new FakeElement({ dataset: { side: 'bottom', align: 'start' } });
    const items = [
        makeItem(content, 'Edit'),
        makeItem(content, 'Gone', { disabled: true }),
        makeItem(content, 'Delete'),
    ];
    const controller = stimulate(DropdownMenuController, {
        targets: { trigger: [trigger], content: [content], item: items },
        values: { open },
    });
    for (const item of items) {
        controller.itemTargetConnected(item);
    }

    return { controller, trigger, content, items };
};

test('items leave the tab order once the controller is there', () => {
    const { items } = setUp();
    assert.equal(items[0].tabIndex, -1);
});

test('opened by the invoker, the focus goes on the first item, and the state follows', () => {
    const { controller, trigger, content, items } = setUp();
    controller.connect();
    controller.toggle({ currentTarget: trigger });
    assert.equal(controller.openValue, false);
    content.popoverOpen = true;
    controller.sync({ newState: 'open' });
    assert.equal(controller.openValue, true);
    assert.equal(FakeElement.focused, items[0]);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    assert.equal(content.style.inset, '46px auto auto 0px');
    content.popoverOpen = false;
    controller.sync({ newState: 'closed' });
    assert.equal(listeners.window.size, 0);
});

test('another trigger toggles through the value', () => {
    const { controller, content, trigger } = setUp();
    controller.connect();
    controller.toggle({ currentTarget: new FakeElement() });
    assert.equal(content.popoverOpen, true);
    controller.toggle({ currentTarget: new FakeElement() });
    assert.equal(content.popoverOpen, false);
    assert.equal(FakeElement.focused, trigger);
});

test('the arrows open it from the trigger on the first or the last item', () => {
    const { controller, trigger, items } = setUp();
    controller.connect();
    controller.onTriggerKeydown(keydown('ArrowUp', trigger));
    assert.equal(FakeElement.focused, items[2]);
    controller.close();
    const event = keydown('ArrowDown', trigger);
    controller.onTriggerKeydown(event);
    assert.equal(event.prevented, true);
    assert.equal(FakeElement.focused, items[0]);
    const other = keydown('a', trigger);
    controller.onTriggerKeydown(other);
    assert.equal(other.prevented, false);
});

test('open at connection', () => {
    const { controller, content } = setUp({ open: true });
    FakeElement.focused = null;
    controller.connect();
    assert.equal(content.popoverOpen, true);
    assert.equal(FakeElement.focused, null);
    controller.close({ focus: false });
    assert.equal(FakeElement.focused, null);
    controller.disconnect();
});

test('an item closes the menu, unless it says otherwise or is disabled', () => {
    const { controller, items, content } = setUp();
    controller.connect();
    controller.open();
    const disabled = {
        currentTarget: items[1],
        prevented: false,
        preventDefault() {
            this.prevented = true;
        },
    };
    controller.closeFromItem(disabled);
    assert.equal(disabled.prevented, true);
    assert.equal(content.popoverOpen, true);
    controller.closeFromItem({ currentTarget: items[0] });
    assert.equal(content.popoverOpen, false);

    items[2].dataset.closeOnSelect = 'false';
    controller.open();
    controller.closeFromItem({ currentTarget: items[2] });
    assert.equal(content.popoverOpen, true);
});

test('a checkbox item changes and shows its mark', () => {
    const { controller, content } = setUp();
    const indicator = new FakeElement();
    const item = makeItem(content, 'C', { closeOnSelect: 'false', attributes: { 'aria-checked': 'false' } });
    item.selectors = { '[data-checkbox-indicator]': indicator };
    controller.connect();
    controller.toggleCheckbox({ currentTarget: item });
    assert.equal(item.getAttribute('aria-checked'), 'true');
    assert.equal(indicator.hidden, false);
    controller.toggleCheckbox({ currentTarget: item });
    assert.equal(indicator.hidden, true);

    const disabled = makeItem(content, 'D', { disabled: true });
    const event = {
        currentTarget: disabled,
        preventDefault() {
            this.prevented = true;
        },
    };
    controller.toggleCheckbox(event);
    assert.equal(event.prevented, true);

    const bare = makeItem(content, 'B', { closeOnSelect: 'false' });
    controller.toggleCheckbox({ currentTarget: bare });
    assert.equal(bare.getAttribute('aria-checked'), 'true');
});

test('a radio item is alone checked in its group', () => {
    const { controller, content } = setUp();
    const group = new FakeElement();
    const radios = ['a', 'b'].map((value) => {
        const radio = makeItem(content, value, { closeOnSelect: 'false' });
        radio.dataset.value = value;
        radio.indicator = new FakeElement();
        radio.selectors = { '[data-radio-indicator]': radio.indicator };
        radio.closest = (selector) => (selector === '[data-slot="dropdown-menu-radio-group"]' ? group : null);

        return radio;
    });
    group.selectorsAll = { '[role="menuitemradio"]': radios };
    controller.connect();
    controller.selectRadio({ currentTarget: radios[1] });
    assert.equal(group.dataset.value, 'b');
    assert.equal(radios[1].getAttribute('aria-checked'), 'true');
    assert.equal(radios[0].getAttribute('aria-checked'), 'false');
    assert.equal(radios[0].indicator.hidden, true);

    const disabled = makeItem(content, 'x', { disabled: true });
    const event = {
        currentTarget: disabled,
        preventDefault() {
            this.prevented = true;
        },
    };
    controller.selectRadio(event);
    assert.equal(event.prevented, true);

    const alone = makeItem(content, 'y');
    alone.closest = () => null;
    controller.selectRadio({ currentTarget: alone });
});

test('the keys inside the menu', () => {
    const { controller, items, content } = setUp();
    controller.connect();
    const press = (key, target) => controller.onContentKeydown(keydown(key, target));

    press('ArrowDown', items[0]);
    assert.equal(FakeElement.focused, items[2]);
    press('ArrowDown', items[2]);
    assert.equal(FakeElement.focused, items[0]);
    press('ArrowUp', items[0]);
    assert.equal(FakeElement.focused, items[2]);
    press('ArrowUp', items[2]);
    assert.equal(FakeElement.focused, items[0]);
    press('End', items[0]);
    assert.equal(FakeElement.focused, items[2]);
    press('Home', items[2]);
    assert.equal(FakeElement.focused, items[0]);

    controller.open();
    press('Tab', items[0]);
    assert.equal(controller.openValue, false);

    content.closest = () => null;
});

test('Enter and Space click what is not a button', () => {
    const { controller, content, items } = setUp();
    const link = makeItem(content, 'L', { tagName: 'A' });
    controller.connect();
    const event = keydown('Enter', link);
    controller.onContentKeydown(event);
    assert.equal(event.prevented, true);
    assert.deepEqual(link.calls.at(-1), ['click']);
    const onButton = keydown(' ', items[0]);
    controller.onContentKeydown(onButton);
    assert.equal(onButton.prevented, false);
});

test('typing goes to the item, the shortcuts are left alone', () => {
    const { controller, items } = setUp();
    controller.connect();
    controller.onContentKeydown(keydown('d', items[0]));
    assert.equal(FakeElement.focused, items[2]);
    FakeElement.focused = null;
    controller.onContentKeydown(keydown('Shift', items[0]));
    controller.onContentKeydown(keydown('k', items[0], { metaKey: true }));
    assert.equal(FakeElement.focused, null);
    controller.disconnect();
});

const withSub = () => {
    const base = setUp();
    const sub = new FakeElement();
    const parent = new FakeElement();
    const subTrigger = makeItem(base.content, 'More', { attributes: { 'aria-haspopup': 'menu' } });
    subTrigger.parentElement = parent;
    parent.selectors = { '[role="menu"]': sub, '[aria-haspopup="menu"]': subTrigger };
    sub.parentElement = parent;
    const subItem = makeItem(sub, 'Inside');
    base.items.push(subTrigger, subItem);

    return { ...base, sub, subTrigger, subItem };
};

test('the right arrow opens a sub-menu, the left one closes it', () => {
    const { controller, sub, subTrigger, subItem, items } = withSub();
    controller.connect();
    const open = keydown('ArrowRight', subTrigger);
    controller.onContentKeydown(open);
    assert.equal(open.prevented, true);
    assert.equal(sub.popoverOpen, true);
    assert.equal(FakeElement.focused, subItem);

    controller.onContentKeydown(keydown('ArrowRight', subTrigger));
    assert.equal(sub.calls.filter(([name]) => name === 'showPopover').length, 1);

    const close = keydown('ArrowLeft', subItem);
    controller.onContentKeydown(close);
    assert.equal(close.prevented, true);
    assert.equal(sub.popoverOpen, false);
    assert.equal(FakeElement.focused, subTrigger);

    const onMenu = keydown('ArrowLeft', items[0]);
    controller.onContentKeydown(onMenu);
    assert.equal(onMenu.prevented, false);
    const onItem = keydown('ArrowRight', items[0]);
    controller.onContentKeydown(onItem);
    assert.equal(onItem.prevented, false);
});

test('Escape closes the submenu alone and gives the focus back to its trigger; on the menu it is left to the browser', () => {
    browser();
    const { controller, sub, subTrigger, subItem, items } = withSub();
    controller.connect();
    controller.onContentKeydown(keydown('ArrowRight', subTrigger));

    const inSub = keydown('Escape', subItem);
    controller.onContentKeydown(inSub);
    assert.equal(inSub.prevented, true);
    assert.equal(sub.popoverOpen, false);
    assert.equal(FakeElement.focused, subTrigger);

    const onMenu = keydown('Escape', items[0]);
    controller.onContentKeydown(onMenu);
    assert.equal(onMenu.prevented, false);
});

test('right to left, the arrows are mirrored', () => {
    browser({ direction: 'rtl' });
    const { controller, sub, subTrigger } = withSub();
    controller.connect();
    controller.onContentKeydown(keydown('ArrowLeft', subTrigger));
    assert.equal(sub.popoverOpen, true);
});

const subMenu = ({ openDelay = 0, closeDelay = 0, direction = 'ltr' } = {}) => {
    browser({ direction });
    const trigger = new FakeElement({ rect: { top: 100, bottom: 130, left: 100, right: 200 } });
    const content = new FakeElement();
    const controller = stimulate(DropdownMenuSubController, {
        targets: { trigger: [trigger], content: [content] },
        values: { openDelay, closeDelay },
    });

    return { controller, trigger, content };
};

test('sub-menu: opened and closed at once by default', () => {
    const { controller, content } = subMenu();
    controller.show();
    controller.show();
    assert.equal(content.calls.filter(([name]) => name === 'showPopover').length, 1);
    controller.hide();
    controller.hide();
    assert.equal(content.calls.filter(([name]) => name === 'hidePopover').length, 1);
});

test('sub-menu: the delays, a new movement cancelling the last', async () => {
    const { controller, content } = subMenu({ openDelay: 20, closeDelay: 20 });
    controller.show();
    assert.equal(content.popoverOpen, false);
    await sleep(40);
    assert.equal(content.popoverOpen, true);
    controller.hide();
    controller.show();
    await sleep(40);
    assert.equal(content.popoverOpen, true);
    controller.hide();
    controller.disconnect();
    await sleep(40);
    assert.equal(content.popoverOpen, true);
});

test('sub-menu: its state follows and it opens next to its trigger', () => {
    const { controller, trigger, content } = subMenu();
    controller.sync({ newState: 'open' });
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    assert.equal(content.dataset.state, 'open');
    assert.equal(content.style.inset, '100px auto auto 202px');
    controller.sync({ newState: 'closed' });
    assert.equal(trigger.dataset.state, 'closed');

    const mirrored = subMenu({ direction: 'rtl' });
    mirrored.trigger.rect = { top: 100, bottom: 130, left: 300, right: 400 };
    mirrored.controller.sync({ newState: 'open' });
    assert.equal(mirrored.content.style.inset, '100px auto auto 198px');
});
