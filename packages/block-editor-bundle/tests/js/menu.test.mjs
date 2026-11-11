// The menus as data: matching, moving, triggers, inserted references and search answers.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { filterItems, findTrigger, fold, moveIndex, referenceHtml, searchUrl, suggestions } from '../../assets/src/lib/menu.js';

const items = [
    { label: 'Text', keywords: ['paragraph'] },
    { label: 'Heading 1', keywords: ['h1', 'title'] },
    { label: 'Bulleted list', keywords: ['ul'] },
    { label: 'Citation', keywords: ['quote'] },
    { label: 'Séparateur' },
];

test('entries are found by label, then by word, then by any part, diacritics folded', () => {
    assert.equal(fold('ÉtÉ'), 'ete');
    assert.equal(filterItems(items, '  '), items);
    assert.deepEqual(filterItems(items, 'he').map((item) => item.label), ['Heading 1']);
    assert.deepEqual(filterItems(items, 'list').map((item) => item.label), ['Bulleted list']);
    assert.deepEqual(filterItems(items, 'ti').map((item) => item.label), ['Heading 1', 'Citation'], 'a word, then a part');
    assert.deepEqual(filterItems(items, 'quo').map((item) => item.label), ['Citation']);
    assert.deepEqual(filterItems(items, 'separ').map((item) => item.label), ['Séparateur']);
    assert.deepEqual(filterItems(items, 'agra').map((item) => item.label), ['Text']);
    assert.deepEqual(filterItems(items, 'zz'), []);
    assert.deepEqual(filterItems([{ label: 'Bb' }, { label: 'Ab' }], 'b').map((item) => item.label), ['Bb', 'Ab']);
});

test('the selection wraps around', () => {
    assert.equal(moveIndex(0, 1, 3), 1);
    assert.equal(moveIndex(2, 1, 3), 0);
    assert.equal(moveIndex(0, -1, 3), 2);
    assert.equal(moveIndex(0, 1, 0), -1);
});

test('the triggers before the caret', () => {
    assert.deepEqual(findTrigger('/'), { kind: 'slash', query: '', length: 1 });
    assert.deepEqual(findTrigger('Some text /hea'), { kind: 'slash', query: 'hea', length: 4 });
    assert.equal(findTrigger('a/b'), null, 'a slash inside a word is a slash');
    assert.equal(findTrigger('/a b'), null);
    assert.deepEqual(findTrigger('Hi @ad'), { kind: 'mention', query: 'ad', length: 3 });
    assert.deepEqual(findTrigger('(@'), { kind: 'mention', query: '', length: 1 });
    assert.equal(findTrigger('mail@example'), null);
    assert.deepEqual(findTrigger('See [[Page one'), { kind: 'link', query: 'Page one', length: 10 });
    assert.equal(findTrigger('[[done]] after'), null);
    assert.equal(findTrigger('Hi @ad', ['slash']), null);
    assert.equal(findTrigger('[[x', ['mention']), null);
    assert.deepEqual(findTrigger(' /', ['slash']), { kind: 'slash', query: '', length: 1 });
});

test('a chosen suggestion is a mention or a page link, its address kept only when safe', () => {
    assert.equal(referenceHtml('mention', { reference: 'user:42', label: 'Ada <L>', url: '/u/42' }), '<a href="/u/42" data-mention="user:42">@Ada &lt;L&gt;</a>');
    assert.equal(referenceHtml('link', { reference: 'page:7', label: 'Page', url: 'javascript:x' }), '<a data-ref="page:7">Page</a>');
    assert.equal(referenceHtml('link', { reference: 'page:7', label: 'Page', url: null }), '<a data-ref="page:7">Page</a>');
});

test('the search address and its answer', () => {
    assert.equal(searchUrl('/s', 'a b&c'), '/s?q=a%20b%26c');
    assert.equal(searchUrl('/s?kind=user', 'x'), '/s?kind=user&q=x');
    assert.deepEqual(suggestions({ items: [{ reference: 'user:1', label: 'A' }, { reference: 'bad', label: 'B' }, { reference: 'user:2', label: '' }, { reference: 'user:3' }, null] }), [{ reference: 'user:1', label: 'A' }]);
    assert.deepEqual(suggestions({}), []);
    assert.deepEqual(suggestions(null), []);
});
