// Who edits what.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { active, locks, roster, sameLocks, withDeadlines } from '../../assets/src/lib/presence.js';

const items = [
    { holder: 'bob', name: 'Bob', part: 'b1', expiresIn: 30 },
    { holder: 'me', name: 'Me', part: 'b2', expiresIn: 30 },
    { holder: 'bob', name: 'Bob', part: 'b3', expiresIn: 5 },
    { holder: 'cy', name: 'Cy', part: '', expiresIn: 'x' },
];

test('each lease ends on this page\'s clock', () => {
    const timed = withDeadlines(items, 1000);

    assert.deepEqual(timed.map((item) => item.deadline), [31000, 31000, 6000, 1000]);
    assert.deepEqual(withDeadlines([{ expiresIn: -4 }], 10).map((item) => item.deadline), [10]);
    assert.deepEqual(active(timed, 1000).map((item) => item.part), ['b1', 'b2', 'b3']);
    assert.deepEqual(active(timed, 6000).map((item) => item.part), ['b1', 'b2']);
});

test('the blocks of the others are closed, all of them under a whole lease', () => {
    assert.deepEqual(locks(items.slice(0, 3), 'me', ['b1', 'b2', 'b3', 'b4']), { b1: 'Bob', b3: 'Bob' });
    assert.deepEqual(locks(items, 'me', ['b1', 'b2', 'b4']), { b1: 'Bob', b3: 'Bob', b2: 'Cy', b4: 'Cy' });
    assert.deepEqual(locks([], 'me', ['b1']), {});
    assert.equal(sameLocks({ a: 'X', b: 'Y' }, { b: 'Y', a: 'X' }), true);
    assert.equal(sameLocks({ a: 'X' }, { a: 'Y' }), false);
    assert.equal(sameLocks({ a: 'X' }, { a: 'X', b: 'Y' }), false);
    assert.equal(sameLocks({}, {}), true);
});

test('the roster lists each writer once', () => {
    assert.deepEqual(roster(items, 'me'), [
        { holder: 'bob', name: 'Bob', parts: ['b1', 'b3'], whole: false, mine: false },
        { holder: 'me', name: 'Me', parts: ['b2'], whole: false, mine: true },
        { holder: 'cy', name: 'Cy', parts: [], whole: true, mine: false },
    ]);
});
