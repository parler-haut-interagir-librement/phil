// The picker controller and its search, under Node's test runner.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import { matches, normalized } from '../../assets/src/lib/search.js';
import PickerController from '../../assets/src/picker_controller.js';

const focusable = (extra = {}) => ({
    focused: 0,
    focus() {
        this.focused += 1;
    },
    ...extra,
});

const option = (search) => focusable({
    dataset: { search },
    hidden: false,
    tabIndex: 0,
    clicked: 0,
    click() {
        this.clicked += 1;
    },
});

const key = (name, target) => {
    const event = { key: name, target, prevented: 0, preventDefault() { this.prevented += 1; } };

    return event;
};

const picker = (labels = ['Like', 'Thanks', 'Éclairant']) => {
    const controller = new PickerController();
    const options = labels.map(option);
    const inFirst = options.slice(0, 2);
    controller.element = { open: true, contains: (node) => node === 'inside' };
    controller.summaryTarget = focusable();
    controller.searchTarget = focusable({ hidden: true, value: 'old' });
    controller.statusTarget = { textContent: '' };
    controller.emptyTarget = { hidden: true };
    controller.optionTargets = options;
    controller.groupTargets = [
        { hidden: false, contains: (node) => inFirst.includes(node) },
        { hidden: false, contains: (node) => !inFirst.includes(node) },
    ];
    controller.resultsValue = 'Shown: {count}';

    return { controller, options };
};

test('the search compares without case or accent', () => {
    assert.equal(normalized('ÉCLAIRANT'), 'eclairant');
    assert.ok(matches('Éclairant', ' ecl '));
    assert.ok(matches('Like', ''));
    assert.ok(!matches('Like', 'thanks'));
});

test('opening shows the search, empty, focused', () => {
    const { controller, options } = picker();

    controller.toggled();

    assert.equal(controller.searchTarget.hidden, false);
    assert.equal(controller.searchTarget.value, '');
    assert.equal(controller.searchTarget.focused, 1);
    assert.equal(controller.statusTarget.textContent, 'Shown: 3');
    assert.deepEqual(options.map((one) => one.tabIndex), [0, -1, -1]);
});

test('closing leaves the search alone', () => {
    const { controller } = picker();
    controller.element.open = false;

    controller.toggled();

    assert.equal(controller.searchTarget.hidden, true);
    assert.equal(controller.searchTarget.focused, 0);
});

test('the search hides what does not match, and the groups left empty', () => {
    const { controller, options } = picker();
    controller.searchTarget.value = 'eclair';

    controller.filter();

    assert.deepEqual(options.map((one) => one.hidden), [true, true, false]);
    assert.deepEqual(controller.groupTargets.map((one) => one.hidden), [true, false]);
    assert.equal(controller.emptyTarget.hidden, true);
    assert.equal(controller.statusTarget.textContent, 'Shown: 1');
    assert.deepEqual(options.map((one) => one.tabIndex), [-1, -1, 0]);
});

test('a search that finds nothing says so', () => {
    const { controller } = picker();
    controller.searchTarget.value = 'zzz';

    controller.filter();

    assert.equal(controller.emptyTarget.hidden, false);
    assert.equal(controller.statusTarget.textContent, 'Shown: 0');
});

test('Escape closes and goes back to the summary', () => {
    const { controller, options } = picker();
    const event = key('Escape', options[0]);

    controller.keydown(event);

    assert.equal(event.prevented, 1);
    assert.equal(controller.element.open, false);
    assert.equal(controller.summaryTarget.focused, 1);
});

test('Enter in the search picks the first reaction shown, without sending the form by itself', () => {
    const { controller, options } = picker();
    options[0].hidden = true;
    const event = key('Enter', controller.searchTarget);

    controller.keydown(event);

    assert.equal(event.prevented, 1);
    assert.equal(options[1].clicked, 1);
    assert.equal(options[0].clicked, 0);
});

test('Enter in a search that found nothing sends nothing', () => {
    const { controller, options } = picker();
    options.forEach((one) => {
        one.hidden = true;
    });
    const event = key('Enter', controller.searchTarget);

    assert.doesNotThrow(() => controller.keydown(event));
    assert.equal(event.prevented, 1);
});

test('ArrowDown goes from the search to the first reaction; other keys type', () => {
    const { controller, options } = picker();
    const down = key('ArrowDown', controller.searchTarget);
    const letter = key('a', controller.searchTarget);

    controller.keydown(letter);
    controller.keydown(down);

    assert.equal(letter.prevented, 0);
    assert.equal(down.prevented, 1);
    assert.equal(options[0].focused, 1);
});

test('the arrows move among the reactions shown', () => {
    const { controller, options } = picker();
    options[1].hidden = true;

    for (const name of ['ArrowDown', 'ArrowRight']) {
        options[2].focused = 0;
        const event = key(name, options[0]);
        controller.keydown(event);
        assert.equal(event.prevented, 1);
        assert.equal(options[2].focused, 1);
        assert.equal(options[2].tabIndex, 0);
        assert.equal(options[0].tabIndex, -1);
    }

    for (const name of ['ArrowUp', 'ArrowLeft']) {
        options[0].focused = 0;
        controller.keydown(key(name, options[2]));
        assert.equal(options[0].focused, 1);
    }
});

test('going up from the first reaction goes to the search, down from the last stays', () => {
    const { controller, options } = picker();

    controller.keydown(key('ArrowUp', options[0]));
    assert.equal(controller.searchTarget.focused, 1);

    const event = key('ArrowDown', options[2]);
    controller.keydown(event);
    assert.equal(options[2].focused, 1);
    assert.equal(event.prevented, 1);
});

test('Home and End', () => {
    const { controller, options } = picker();

    controller.keydown(key('End', options[0]));
    assert.equal(options[2].focused, 1);

    controller.keydown(key('Home', options[2]));
    assert.equal(options[0].focused, 1);
});

test('any other key on a reaction, and keys elsewhere, are left alone', () => {
    const { controller, options } = picker();
    const space = key(' ', options[0]);
    const elsewhere = key('ArrowDown', {});

    controller.keydown(space);
    controller.keydown(elsewhere);

    assert.equal(space.prevented, 0);
    assert.equal(elsewhere.prevented, 0);
});

test('choosing a reaction closes the picker', () => {
    const { controller } = picker();

    controller.choose({ target: { closest: () => null } });
    assert.equal(controller.element.open, true);

    controller.choose({ target: { closest: () => ({}) } });
    assert.equal(controller.element.open, false);
    assert.equal(controller.summaryTarget.focused, 1);
});

test('a click elsewhere closes it, and does not take the focus', () => {
    const { controller } = picker();

    controller.outside({ target: 'inside' });
    assert.equal(controller.element.open, true);

    controller.outside({ target: 'outside' });
    assert.equal(controller.element.open, false);
    assert.equal(controller.summaryTarget.focused, 0);
});
