// The calendar controller, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import CalendarController, { moved } from '../../assets/src/calendar_controller.js';

test('the arrows move a day or a week, Home and End to the ends of the week, past the grid too', () => {
    assert.equal(moved('ArrowRight', 3), 4);
    assert.equal(moved('ArrowLeft', 3), 2);
    assert.equal(moved('ArrowDown', 3), 10);
    assert.equal(moved('ArrowUp', 10), 3);
    assert.equal(moved('Home', 10), 7);
    assert.equal(moved('End', 10), 13);
    assert.equal(moved('ArrowLeft', 0), -1);
    assert.equal(moved('ArrowUp', 3), -4);
    assert.equal(moved('ArrowRight', 34), 35);
    assert.equal(moved('Enter', 3), null);
});

test('right to left, the side arrows are mirrored', () => {
    assert.equal(moved('ArrowRight', 3, true), 2);
    assert.equal(moved('ArrowLeft', 3, true), 4);
    assert.equal(moved('ArrowDown', 3, true), 10);
});

const day = (current = false) => ({
    tabIndex: 0,
    focused: false,
    focus() { this.focused = true; },
    hasAttribute: (name) => current && name === 'data-current',
});

const link = () => ({ clicked: 0, click() { this.clicked += 1; } });

function connected(days, rtl = false) {
    const listeners = {};
    const controller = new CalendarController();
    controller.element = {
        addEventListener: (type, listener) => { listeners[type] = listener; },
        removeEventListener: (type, listener) => { if (listeners[type] === listener) delete listeners[type]; },
    };
    controller.dayTargets = days;
    controller.previousTargets = [link()];
    controller.nextTargets = [link()];
    controller.isRtl = () => rtl;
    controller.connect();

    const press = (key, target, modifiers = {}) => {
        let prevented = false;
        listeners.keydown({ key, target, ...modifiers, preventDefault: () => { prevented = true; } });

        return prevented;
    };

    return { controller, listeners, press };
}

test('the current day alone is in the tab order, the first when none is', () => {
    const days = Array.from({ length: 14 }, (_, position) => day(position === 5));
    connected(days);
    assert.equal(days.filter((one) => one.tabIndex === 0).length, 1);
    assert.equal(days[5].tabIndex, 0);

    const none = Array.from({ length: 7 }, () => day());
    connected(none);
    assert.equal(none[0].tabIndex, 0);
    assert.equal(none[1].tabIndex, -1);
});

test('a key moves the focus and the tab order; another key, a modifier or another element is left alone', () => {
    const days = Array.from({ length: 14 }, () => day());
    const { controller, listeners, press } = connected(days);

    assert.equal(press('ArrowDown', days[2]), true);
    assert.equal(days[9].tabIndex, 0);
    assert.equal(days[2].tabIndex, -1);
    assert.equal(days[9].focused, true);

    assert.equal(press('a', days[9]), false);
    assert.equal(press('ArrowDown', {}), false);
    assert.equal(press('ArrowLeft', days[9], { altKey: true }), false);
    assert.equal(press('ArrowLeft', days[9], { ctrlKey: true }), false);
    assert.equal(press('ArrowLeft', days[9], { metaKey: true }), false);
    assert.equal(days[9].tabIndex, 0);

    controller.disconnect();
    assert.equal(listeners.keydown, undefined);
});

test('Page Up, Page Down and the edges of the grid go to the period before or after', () => {
    const days = Array.from({ length: 14 }, () => day());
    const { controller, press } = connected(days);

    assert.equal(press('PageUp', days[3]), true);
    assert.equal(press('ArrowUp', days[3]), true);
    assert.equal(controller.previousTargets[0].clicked, 2);
    assert.equal(press('PageDown', days[3]), true);
    assert.equal(press('ArrowRight', days[13]), true);
    assert.equal(controller.nextTargets[0].clicked, 2);
    assert.equal(days[0].tabIndex, 0, 'the tab order stays for the new period to set');

    const rtl = connected(Array.from({ length: 7 }, () => day()), true);
    assert.equal(rtl.press('ArrowRight', rtl.controller.dayTargets[0]), true);
    assert.equal(rtl.controller.previousTargets[0].clicked, 1);
});
