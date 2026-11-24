// The pace of a questionnaire under Node's test runner: `node --test tests/js` from the package.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import { isShown, nextShown, percent, position, previousShown, settle } from '../../assets/src/steps.js';

// A question: an element naming its field, hidden or not.
function question(hidden = false) {
    return { hidden, hasAttribute: (name) => 'data-form-logic-field' === name, querySelectorAll: () => [] };
}

// A section holding questions.
function section(...questions) {
    return { hidden: false, hasAttribute: () => false, querySelectorAll: () => questions };
}

test('a question is shown unless the logic hides it, a section while one of its questions is', () => {
    assert.equal(isShown(question()), true);
    assert.equal(isShown(question(true)), false);
    assert.equal(isShown(section(question(true), question())), true);
    assert.equal(isShown(section(question(true), question(true))), false);
    assert.equal(isShown(section()), false);
});

test('next and previous skip the hidden steps and stay at the ends', () => {
    const steps = [question(), question(true), question(), question(true)];

    assert.equal(nextShown(steps, 0), 2);
    assert.equal(nextShown(steps, 2), 2);
    assert.equal(previousShown(steps, 2), 0);
    assert.equal(previousShown(steps, 0), 0);
});

test('a step the logic hides gives way to the next shown, else the previous', () => {
    const steps = [question(), question(true), question(), question(true)];

    assert.equal(settle(steps, 0), 0);
    assert.equal(settle(steps, 1), 2);
    assert.equal(settle(steps, 3), 2);
    assert.equal(settle([question(true), question(true)], 1), 1);
});

test('the position counts the shown steps only, and the progress rounds', () => {
    const steps = [question(), question(true), question(), question()];

    assert.deepEqual(position(steps, 0), { current: 1, total: 3 });
    assert.deepEqual(position(steps, 3), { current: 3, total: 3 });
    assert.equal(percent(1, 3), 33);
    assert.equal(percent(2, 3), 67);
    assert.equal(percent(3, 3), 100);
    assert.equal(percent(0, 0), 0);
});
