// The chosen controller, under Node's test runner, its peer resolved to a stub by `register.mjs`.

import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import ChosenController from '../../assets/src/chosen_controller.js';

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

const controller = () => {
    const chosen = new ChosenController();
    chosen.pollValue = 'p-1';
    chosen.dateValue = '2026-12-05T19:00:00-05:00';
    chosen.connect();

    return chosen;
};

beforeEach(() => {
    globalThis.window = { sessionStorage: storage() };
});

test('a date chosen is said once, on the window, naming the poll and the date', () => {
    assert.deepEqual(controller().dispatched, [
        {
            name: 'chosen',
            prefix: 'scheduling-poll',
            target: globalThis.window,
            detail: { poll: 'p-1', date: '2026-12-05T19:00:00-05:00' },
        },
    ]);
    assert.equal(globalThis.window.sessionStorage.kept.get('phil-scheduling-poll-chosen:p-1'), '1');
    assert.deepEqual(controller().dispatched, []);
});

test('without storage, the date chosen is said every time', () => {
    globalThis.window = { sessionStorage: storage(true) };

    assert.equal(controller().dispatched.length, 1);
    assert.equal(controller().dispatched.length, 1);
});
