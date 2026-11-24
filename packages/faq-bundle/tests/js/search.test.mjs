// The search folded as the server folds it, and the questions this browser voted for.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import { fold, matches, normalize, terms } from '../../assets/src/lib/search.js';
import { remember, voted } from '../../assets/src/lib/voted.js';

test('a search is folded as the server folds it', () => {
    assert.equal(normalize('  Où est  MA commande ?'), 'ou est ma commande');
    assert.equal(normalize('ﬁn ①'), 'fin 1');
    assert.equal(normalize("L'Ordre — tome-2"), 'l ordre tome 2');
    assert.equal(normalize('?!'), '');
    assert.equal(fold(42), '42');
});

test('a search is cut to one hundred characters without a trailing space', () => {
    assert.equal(normalize('é'.repeat(150)), 'e'.repeat(100));
    assert.equal(normalize(`${'a'.repeat(99)} bcd`), 'a'.repeat(99));
});

test('the words are distinct and ten at most', () => {
    assert.deepEqual(terms(''), []);
    assert.deepEqual(terms('a b a'), ['a', 'b']);
    assert.deepEqual(terms('1 2 3 4 5 6 7 8 9 10 11'), ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);
});

test('a text matches when it holds every word', () => {
    assert.equal(matches('how do i pay by card', ['pay', 'card']), true);
    assert.equal(matches('how do i pay by card', ['pay', 'cash']), false);
    assert.equal(matches('anything', []), true);
});

const memory = (initial = null) => {
    const items = new Map(initial === null ? [] : [['phil-faq-voted', initial]]);

    return {
        getItem: (key) => items.get(key) ?? null,
        setItem: (key, value) => items.set(key, value),
        items,
    };
};

test('the questions voted for are remembered, once each, the latest last', () => {
    const storage = memory();
    remember(storage, 'a');
    remember(storage, 'b');
    remember(storage, 'a');

    assert.deepEqual(voted(storage), ['b', 'a']);
});

test('at most five hundred questions are remembered', () => {
    const storage = memory(JSON.stringify(Array.from({ length: 500 }, (_, i) => `q${i}`)));
    remember(storage, 'last');
    const list = voted(storage);

    assert.equal(list.length, 500);
    assert.equal(list[0], 'q1');
    assert.equal(list.at(-1), 'last');
});

test('a broken or refused storage remembers nothing and breaks nothing', () => {
    assert.deepEqual(voted(memory('{not json')), []);
    assert.deepEqual(voted(memory('{"a":1}')), []);
    assert.deepEqual(voted(memory('["a", 3]')), ['a']);
    assert.deepEqual(voted(null), []);

    const refusing = { getItem: () => null, setItem: () => { throw new Error('quota'); } };
    assert.doesNotThrow(() => remember(refusing, 'a'));
    assert.doesNotThrow(() => remember(null, 'a'));
});
