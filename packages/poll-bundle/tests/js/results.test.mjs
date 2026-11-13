// The results controller, under Node's test runner, its peer resolved to a stub by `register.mjs`.

import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import ResultsController from '../../assets/src/results_controller.js';

const storage = (refuse = false) => {
    const kept = new Map();

    return {
        kept,
        getItem(key) {
            if (refuse) {
                throw new Error('refused');
            }

            return kept.get(key) ?? null;
        },
        setItem(key, value) {
            if (refuse) {
                throw new Error('refused');
            }

            kept.set(key, value);
        },
    };
};

const controller = (revealed) => {
    const results = new ResultsController();
    results.pollValue = 'p-1';
    results.revealedValue = revealed;
    results.winnersValue = ['Tea'];
    results.connect();

    return results;
};

beforeEach(() => {
    globalThis.window = { sessionStorage: storage() };
});

test('revealed results are said once, on the window, naming the poll and its winners', () => {
    const first = controller(true);

    assert.deepEqual(first.dispatched, [
        { name: 'revealed', prefix: 'poll', target: globalThis.window, detail: { poll: 'p-1', winners: ['Tea'] } },
    ]);
    assert.equal(globalThis.window.sessionStorage.kept.get('phil-poll-revealed:p-1'), '1');
    assert.deepEqual(controller(true).dispatched, []);
});

test('results not revealed are not said', () => {
    assert.deepEqual(controller(false).dispatched, []);
    assert.equal(globalThis.window.sessionStorage.kept.size, 0);
});

test('without storage, revealed results are said every time', () => {
    globalThis.window = { sessionStorage: storage(true) };

    assert.equal(controller(true).dispatched.length, 1);
    assert.equal(controller(true).dispatched.length, 1);
});
