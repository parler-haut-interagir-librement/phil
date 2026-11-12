import assert from 'node:assert/strict';
import { test } from 'node:test';

import { debounce } from '../../assets/src/lib/debounce.js';

function clock() {
    const timers = new Map();
    let next = 1;

    return {
        timers,
        setTimeout(step, wait) {
            timers.set(next, [step, wait]);

            return next++;
        },
        clearTimeout(handle) {
            timers.delete(handle);
        },
        fire() {
            for (const [handle, [step]] of [...timers]) {
                timers.delete(handle);
                step();
            }
        },
    };
}

test('the step runs once the changes stop', () => {
    const timers = clock();
    let runs = 0;
    const saving = debounce(() => runs++, 800, timers);

    assert.equal(saving.pending, false);
    saving.schedule();
    saving.schedule();
    saving.schedule();
    assert.equal(timers.timers.size, 1);
    assert.equal([...timers.timers.values()][0][1], 800);
    assert.equal(saving.pending, true);
    timers.fire();
    assert.equal(runs, 1);
    assert.equal(saving.pending, false);
});

test('a flush runs a waiting step at once, and only then', () => {
    const timers = clock();
    let runs = 0;
    const saving = debounce(() => runs++, 800, timers);

    assert.equal(saving.flush(), false);
    saving.schedule();
    assert.equal(saving.flush(), true);
    assert.equal(runs, 1);
    assert.equal(timers.timers.size, 0);
    assert.equal(saving.flush(), false);
    assert.equal(runs, 1);
});

test('a cancel drops a waiting step', () => {
    const timers = clock();
    let runs = 0;
    const saving = debounce(() => runs++, 800, timers);

    saving.cancel();
    saving.schedule();
    saving.cancel();
    assert.equal(saving.pending, false);
    assert.equal(timers.timers.size, 0);
    timers.fire();
    assert.equal(runs, 0);
});

test('the timers of the page by default', async () => {
    let runs = 0;
    const saving = debounce(() => runs++, 1);

    saving.schedule();
    await new Promise((resolve) => setTimeout(resolve, 10));
    assert.equal(runs, 1);
});
