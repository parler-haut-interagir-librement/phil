// The recurrence controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import RecurrenceController, { shown } from '../../assets/src/recurrence_controller.js';

const row = (show) => {
    const controls = [{ disabled: false }, { disabled: false }];

    return { dataset: { show }, hidden: false, controls, querySelectorAll: (selector) => (selector === 'input, select' ? controls : []) };
};

test('a row shows for its frequencies or its end, and never without frequency', () => {
    assert.equal(shown('DAILY WEEKLY', 'WEEKLY', 'never'), true);
    assert.equal(shown('MONTHLY  YEARLY', 'YEARLY', 'never'), true);
    assert.equal(shown('YEARLY', 'MONTHLY', 'never'), false);
    assert.equal(shown('count', 'DAILY', 'count'), true);
    assert.equal(shown('until', 'DAILY', 'count'), false);
    assert.equal(shown('count', '', 'count'), false);
});

test('the rows follow the frequency and the end, their fields disabled when hidden', () => {
    const controller = new RecurrenceController();
    const weekly = row('DAILY WEEKLY');
    const yearly = row('YEARLY');
    const count = row('count');
    controller.frequencyTarget = { value: 'WEEKLY' };
    controller.endTarget = { value: 'count' };
    controller.rowTargets = [weekly, yearly, count];
    controller.connect();

    assert.deepEqual([weekly.hidden, yearly.hidden, count.hidden], [false, true, false]);
    assert.deepEqual(yearly.controls.map((control) => control.disabled), [true, true]);
    assert.deepEqual(weekly.controls.map((control) => control.disabled), [false, false]);

    controller.frequencyTarget = { value: 'YEARLY' };
    controller.endTarget = { value: 'never' };
    controller.show();
    assert.deepEqual([weekly.hidden, yearly.hidden, count.hidden], [true, false, true]);
});
