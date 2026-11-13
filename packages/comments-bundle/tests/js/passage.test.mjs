import assert from 'node:assert/strict';
import { test } from 'node:test';

import { fieldOf, fieldPath, fields, findBlock, htmlText, length, offsetIn, pointAt, reanchor, replaceHtml, replaceInDocument, slice } from '../../assets/src/lib/passage.js';

// A few DOM nodes, enough for the functions that walk them.
const text = (data) => ({ nodeType: 3, data, get textContent() { return data; }, childNodes: [], parentElement: null });
const element = (children = [], dataset = {}) => {
    const node = { nodeType: 1, childNodes: children, dataset, get textContent() { return children.map((child) => child.textContent).join(''); } };
    children.forEach((child) => { child.parentElement = node; });

    return node;
};

test('characters are counted as code points', () => {
    assert.equal(length('a😀b'), 3);
    assert.equal(slice('a😀bc', 1, 3), '😀b');
    assert.equal(slice('a😀bc', 2), 'bc');
});

test('the text of inline HTML is its textContent', () => {
    assert.equal(htmlText('a <b>bold</b> &amp; &lt;i&gt;<br>&nbsp;&#233;&#x1F600;&eacute;'), 'a bold & <i> é😀&eacute;');
    assert.equal(htmlText('1 < 2 & 3'), '1 < 2 & 3');
    assert.equal(htmlText('&#0;&#x110000;'), '��');
    assert.equal(htmlText('&quot;&apos;'), '"\'');
});

test('a passage is replaced with every tag kept', () => {
    assert.equal(replaceHtml('one <b>two</b> three', 4, 3, 'deux'), 'one <b>deux</b> three');
    assert.equal(replaceHtml('one <b>two</b> three', 2, 7, 'X'), 'onX<b></b>hree');
    assert.equal(replaceHtml('a &amp; b', 2, 1, '<&>"\''), 'a &lt;&amp;&gt;&quot;&apos; b');
    assert.equal(replaceHtml('😀ab', 1, 1, 'x'), '😀xb');
    assert.equal(replaceHtml('ab', 0, 2, ''), '');
});

const anchor = (start, quote, prefix = '', suffix = '', block = 'b1', field = 'text') => ({ block, field, start, end: start + length(quote), quote, prefix, suffix });

test('a passage still in place keeps its place', () => {
    assert.deepEqual(reanchor(anchor(4, 'cat', 'the ', ' sat'), [{ block: 'b1', field: 'text', text: 'the cat sat' }]), anchor(4, 'cat', 'the ', ' sat'));
});

test('a passage moved by an edit is found by its context', () => {
    const found = reanchor(anchor(4, 'cat', 'the ', ' sat'), [{ block: 'b1', field: 'text', text: 'the cat ran, the cat sat' }]);
    assert.equal(found.start, 17);
    assert.equal(found.prefix, 'the cat ran, the ');
    assert.equal(found.suffix, ' sat');
});

test('in its field, the nearest place wins among equal contexts, the first on a tie', () => {
    assert.equal(reanchor(anchor(9, 'x'), [{ block: 'b1', field: 'text', text: 'x.......x.x' }]).start, 8);
    assert.equal(reanchor(anchor(5, 'x'), [{ block: 'b1', field: 'text', text: 'x...x.x' }]).start, 4);
    assert.equal(reanchor(anchor(2, 'x'), [{ block: 'b1', field: 'text', text: 'x...x' }]).start, 0);
});

test('its field comes before its block, its block before the rest', () => {
    const passages = [
        { block: 'b0', field: 'text', text: 'cat' },
        { block: 'b1', field: 'caption', text: 'cat' },
        { block: 'b1', field: 'text', text: 'a cat' },
    ];
    assert.equal(reanchor(anchor(0, 'cat'), passages).field, 'text');
    assert.equal(reanchor(anchor(0, 'cat'), passages.slice(0, 2)).field, 'caption');
    assert.equal(reanchor(anchor(0, 'cat'), passages.slice(0, 1)).block, 'b0');
    assert.equal(reanchor(anchor(0, 'cat', '', '', null, null), [{ block: 'b0', field: 'text', text: 'cat' }, { block: null, field: 'x', text: 'cat' }]).block, 'b0');
    assert.equal(reanchor(anchor(0, 'cat', '', 'z'), [{ block: 'b0', field: 'text', text: 'cat' }, { block: 'b2', field: 'text', text: 'catz' }]).block, 'b2');
});

test('a passage that occurs nowhere is gone', () => {
    assert.equal(reanchor(anchor(0, 'dog'), [{ block: 'b1', field: 'text', text: 'cat' }]), null);
});

test('characters beyond the basic plane count once', () => {
    const found = reanchor(anchor(0, 'b', '😀'), [{ block: 'b1', field: 'text', text: '😀😀b😀' }]);
    assert.deepEqual([found.start, found.end, found.prefix, found.suffix], [2, 3, '😀😀', '😀']);
    assert.equal(reanchor(anchor(0, 'aa'), [{ block: 'b1', field: 'text', text: 'aaa' }]).start, 0);
    assert.equal(reanchor(anchor(1, 'aa'), [{ block: 'b1', field: 'text', text: 'aaa' }]).start, 1);
    assert.equal(reanchor(anchor(0, 'a', 'x'.repeat(40)), [{ block: 'b1', field: 'text', text: 'y'.repeat(40) + 'a' + 'z'.repeat(40) }]).prefix, 'y'.repeat(32));
    assert.equal(reanchor(anchor(0, 'a'), [{ block: 'b1', field: 'text', text: 'y'.repeat(40) + 'a' + 'z'.repeat(40) }]).suffix, 'z'.repeat(32));
});

test('the path of a field follows the data of its block', () => {
    assert.equal(fieldPath({ dataset: { field: 'caption' } }), 'caption');
    assert.equal(fieldPath({ dataset: { itemField: '0.1' } }), 'items.0.items.1.content');
    assert.equal(fieldPath({ dataset: { cell: '2.1' } }), 'content.2.1');
    assert.equal(fieldPath({ dataset: {} }), null);
    assert.equal(fieldPath({}), null);
});

test('the fields of the editor are read in order, with their block', () => {
    const a = { dataset: { field: 'text' }, textContent: 'one', closest: () => ({ dataset: { id: 'b1' } }) };
    const b = { dataset: { cell: '0.0' }, textContent: undefined, closest: () => ({ dataset: { id: 'b2' } }) };
    const orphan = { dataset: { field: 'text' }, textContent: 'x', closest: () => null };
    const nameless = { dataset: {}, textContent: 'x', closest: () => ({ dataset: { id: 'b3' } }) };
    const root = { querySelectorAll: (selector) => (selector.includes('.pbe-rich[data-cell]') ? [a, b, orphan, nameless] : []) };

    assert.deepEqual(fields(root), [{ block: 'b1', field: 'text', text: 'one', element: a }, { block: 'b2', field: 'content.0.0', text: '', element: b }]);
});

test('the field of a node is its closest field', () => {
    const field = { nodeType: 1, closest: (selector) => (selector.includes('data-item-field') ? 'field' : null) };
    assert.equal(fieldOf(field), 'field');
    assert.equal(fieldOf({ nodeType: 3, parentElement: field }), 'field');
    assert.equal(fieldOf({ nodeType: 3, parentElement: null }), null);
    assert.equal(fieldOf(null), null);
});

test('a point of the DOM is a character offset of the field', () => {
    const first = text('ab😀');
    const second = text('cd');
    const bold = element([second]);
    const root = element([first, bold, text('e')]);

    assert.equal(offsetIn(root, first, 4), 3);
    assert.equal(offsetIn(root, second, 1), 4);
    assert.equal(offsetIn(root, root, 2), 5);
    assert.equal(offsetIn(root, bold, 0), 3);
    assert.equal(offsetIn(root, text('z'), 0), null);
});

test('a character offset of the field is a point of the DOM', () => {
    const first = text('ab😀');
    const second = text('cd');
    const root = element([first, element([second])]);

    assert.deepEqual(pointAt(root, 2), { node: first, offset: 2 });
    assert.deepEqual(pointAt(root, 3), { node: second, offset: 0 });
    assert.deepEqual(pointAt(root, 3, true), { node: first, offset: 4 });
    assert.deepEqual(pointAt(root, 5, true), { node: second, offset: 2 });
    assert.equal(pointAt(root, 5), null);
    assert.equal(pointAt(root, 6, true), null);
});

test('a block is found wherever it is nested', () => {
    const inner = { id: 'c', type: 'paragraph', data: { text: 'x' } };
    const blocks = [{ id: 'a', type: 'paragraph', data: {} }, { id: 'b', type: 'toggle', data: { blocks: [inner] } }, null, 'x'];

    assert.equal(findBlock(blocks, 'c'), inner);
    assert.equal(findBlock(blocks, 'z'), null);
    assert.equal(findBlock([{ id: 'c', data: {} }], 'c'), null);
});

test('a suggestion is applied to the block of the document', () => {
    const json = JSON.stringify({ blocks: [
        { id: 'a', type: 'paragraph', data: { text: 'one <b>two</b> three' } },
        { id: 'l', type: 'list', data: { items: [{ content: 'x', items: [{ content: 'first item' }] }] } },
        { id: 'k', type: 'code', data: { code: 'a &lt; b' } },
    ] });

    assert.deepEqual(replaceInDocument(json, anchor(4, 'two', '', '', 'a', 'text'), 'deux'), { id: 'a', type: 'paragraph', data: { text: 'one <b>deux</b> three' } });
    assert.deepEqual(replaceInDocument(json, anchor(0, 'first', '', '', 'l', 'items.0.items.0.content'), '1st').data.items[0].items[0].content, '1st item');
    assert.deepEqual(replaceInDocument(json, anchor(2, '&lt;', '', '', 'k', 'code'), '<').data.code, 'a < b');
    assert.equal(replaceInDocument(json, anchor(0, 'two', '', '', 'a', 'text'), 'x'), null);
    assert.equal(replaceInDocument(json, anchor(0, 'one', '', '', 'z', 'text'), 'x'), null);
    assert.equal(replaceInDocument(json, anchor(0, 'one', '', '', 'a', 'caption'), 'x'), null);
    assert.equal(replaceInDocument(json, anchor(0, 'one', '', '', 'a', 'text.deep'), 'x'), null);
    assert.equal(replaceInDocument(json, anchor(0, 'x', '', '', 'l', 'items'), 'y'), null);
    assert.equal(replaceInDocument('{', anchor(0, 'one', '', '', 'a', 'text'), 'x'), null);
    assert.equal(replaceInDocument('null', anchor(0, 'one', '', '', 'a', 'text'), 'x'), null);
});
