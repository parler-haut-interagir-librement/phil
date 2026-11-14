import assert from 'node:assert/strict';
import { test } from 'node:test';

import { activeQuery, handleOf, insertMention } from '../../assets/src/lib/query.js';

test('the handle being typed after an @ is the query', () => {
    assert.deepEqual(activeQuery('Hello @ad', 9), { query: 'ad', start: 6, end: 9 });
    assert.deepEqual(activeQuery('@', 1), { query: '', start: 0, end: 1 });
    assert.deepEqual(activeQuery('(@élo', 5), { query: 'élo', start: 1, end: 5 });
    assert.deepEqual(activeQuery('Hello @ada and more', 10), { query: 'ada', start: 6, end: 10 });
});

test('an @ after a word, a space after the handle or no @ open nothing', () => {
    assert.equal(activeQuery('ada@example', 11), null);
    assert.equal(activeQuery('\\@ada', 5), null);
    assert.equal(activeQuery('@@ada', 5), null);
    assert.equal(activeQuery('https://x.test/@ada', 19), null);
    assert.equal(activeQuery('@ada ', 5), null);
    assert.equal(activeQuery('plain', 5), null);
});

test('only the 65 characters before the caret are read', () => {
    const long = 'x '.repeat(100) + '@' + 'a'.repeat(64);

    assert.deepEqual(activeQuery(long, long.length), { query: 'a'.repeat(64), start: 200, end: long.length });
    assert.equal(activeQuery('@' + 'a'.repeat(65), 66), null);
});

test('the chosen handle replaces the query and the rest of its word', () => {
    assert.deepEqual(insertMention('Hi @ad!', { query: 'ad', start: 3, end: 6 }, 'ada'), { text: 'Hi @ada !', caret: 8 });
    assert.deepEqual(insertMention('Hi @adxyz there', { query: 'ad', start: 3, end: 6 }, 'ada'), { text: 'Hi @ada there', caret: 8 });
    assert.deepEqual(insertMention('@', { query: '', start: 0, end: 1 }, 'bo'), { text: '@bo ', caret: 4 });
});

test('a suggestion names its handle in its hint', () => {
    assert.equal(handleOf({ hint: '@ada' }), 'ada');
    assert.equal(handleOf({ hint: 'ada' }), null);
    assert.equal(handleOf({ hint: '@a b' }), null);
    assert.equal(handleOf({ hint: '@' }), null);
    assert.equal(handleOf({ hint: '@' + 'a'.repeat(65) }), null);
    assert.equal(handleOf({}), null);
    assert.equal(handleOf(null), null);
});
