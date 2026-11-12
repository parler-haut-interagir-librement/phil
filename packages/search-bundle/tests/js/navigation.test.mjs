// The decisions of the filters controller that need no page, under Node's test runner.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import { formUrl, isPlainClick, isSameOrigin } from '../../assets/src/lib/navigation.js';

const click = (overrides = {}) => ({
    defaultPrevented: false,
    button: 0,
    metaKey: false,
    ctrlKey: false,
    shiftKey: false,
    altKey: false,
    ...overrides,
});

test('only a plain click of the main button is taken over', () => {
    assert.equal(isPlainClick(click()), true);

    for (const key of ['metaKey', 'ctrlKey', 'shiftKey', 'altKey']) {
        assert.equal(isPlainClick(click({ [key]: true })), false, key);
    }

    assert.equal(isPlainClick(click({ button: 1 })), false);
    assert.equal(isPlainClick(click({ defaultPrevented: true })), false);
});

test('only the page’s own origin is fetched', () => {
    const location = new URL('https://example.com/games?q=x');

    assert.equal(isSameOrigin(new URL('https://example.com/other'), location), true);
    assert.equal(isSameOrigin(new URL('https://example.org/games'), location), false);
    assert.equal(isSameOrigin(new URL('http://example.com/games'), location), false);
});

test('a form goes to its action or to the page, its fields replacing the query string', () => {
    const location = new URL('https://example.com/games?q=old&page=3#top');
    const entries = [
        ['q', 'jeu de rôle'],
        ['filter[genre][]', 'jdr'],
        ['filter[genre][]', 'a&b'],
        ['file', { name: 'x' }],
    ];

    assert.equal(
        formUrl('', entries, location).href,
        'https://example.com/games?q=jeu+de+r%C3%B4le&filter%5Bgenre%5D%5B%5D=jdr&filter%5Bgenre%5D%5B%5D=a%26b',
    );
    assert.equal(formUrl(null, [], location).href, 'https://example.com/games');
    assert.equal(formUrl('/search', [['q', 'x']], location).href, 'https://example.com/search?q=x');
});
