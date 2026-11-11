// The document model: reading, writing, nested lists, list items, tables and the history.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { History, VERSION, addColumn, addRow, childLists, cloneBlock, contains, duplicate, flatItems, indentItem, isBlock, itemAt, itemContents, listOf, locate, moveBy, moveTo, newId, newItem, outdentItem, parseDocument, place, removeBlock, removeColumn, removeItem, removeRow, restyleList, serialize, splitItem, walk } from '../../assets/src/lib/model.js';
import { sequence } from './ids.mjs';

const p = (id, text = id) => ({ id, type: 'paragraph', data: { text } });

const tree = () => [
    p('a'),
    { id: 't', type: 'toggle', data: { text: 'T', open: true, blocks: [p('t1'), p('t2')] } },
    { id: 'c', type: 'columns', data: { cols: [{ blocks: [p('c1')] }, { blocks: [] }] } },
    p('z'),
];

test('identifiers are ten characters of Editor.js\' alphabet', () => {
    const id = newId((bytes) => bytes.map((_, index) => index * 7 + 60));
    assert.equal(id, '8DKRYfmt07');
    assert.match(newId(), /^[A-Za-z0-9_-]{10}$/);
});

test('a document is read leniently and written as UJO', () => {
    assert.deepEqual(parseDocument(''), { time: null, version: VERSION, blocks: [] });
    assert.deepEqual(parseDocument(null), { time: null, version: VERSION, blocks: [] });
    assert.deepEqual(parseDocument('{nope'), { time: null, version: VERSION, blocks: [] });
    assert.deepEqual(parseDocument('[1]'), { time: null, version: VERSION, blocks: [] });
    assert.deepEqual(parseDocument('{"time": 5, "version": "2.30.0", "blocks": [{"id": "a", "type": "paragraph", "data": {"text": "x"}}, {"id": 1}, null, {"id": "b", "type": "x", "data": []}]}'), {
        time: 5,
        version: '2.30.0',
        blocks: [p('a', 'x')],
    });
    assert.equal(parseDocument('{"time": "5", "version": 2, "blocks": []}').time, null);
    assert.equal(parseDocument('{"version": 2, "blocks": []}').version, VERSION);

    const empty = (block) => 'paragraph' === block.type && '' === block.data.text;
    const document = { version: '2.31.7', blocks: [p('a'), p('e', ''), { id: 't', type: 'toggle', data: { text: '', blocks: [p('x', '')] } }, { id: 'c', type: 'columns', data: { cols: [{ blocks: [p('y', ''), p('k')] }, {}] } }] };
    assert.equal(serialize(document, empty, 7), JSON.stringify({
        time: 7,
        blocks: [p('a'), { id: 't', type: 'toggle', data: { text: '', blocks: [] } }, { id: 'c', type: 'columns', data: { cols: [{ blocks: [p('k')] }, { blocks: [] }] } }],
        version: '2.31.7',
    }));
    assert.equal(document.blocks.length, 4, 'the document itself is left as it is');
    assert.equal(serialize({ blocks: [p('e', '')] }, empty), '');
    assert.equal(JSON.parse(serialize({ blocks: [p('a')] }, empty, 1)).version, VERSION);
});

test('blocks are found at any depth, with the key of their list', () => {
    const blocks = tree();
    assert.deepEqual(childLists(blocks[2]).map((child) => child.key), ['c:0', 'c:1']);
    assert.deepEqual(childLists(blocks[0]), []);
    assert.equal(locate(blocks, 't2').key, 't');
    assert.equal(locate(blocks, 't2').index, 1);
    assert.equal(locate(blocks, 'c1').key, 'c:0');
    assert.equal(locate(blocks, 'z').key, 'root');
    assert.equal(locate(blocks, 'nope'), null);
    assert.equal(listOf(blocks, 'root'), blocks);
    assert.equal(listOf(blocks, 'c:1'), blocks[2].data.cols[1].blocks);
    assert.equal(listOf(blocks, 't'), blocks[1].data.blocks);
    assert.equal(listOf(blocks, 'x'), null);
    assert.deepEqual(Array.from(walk(blocks), (block) => block.id), ['a', 't', 't1', 't2', 'c', 'c1', 'z']);
    assert.ok(contains(blocks[1], 't2'));
    assert.ok(contains(blocks[1], 't'));
    assert.ok(!contains(blocks[1], 'c1'));
    const bare = { id: 'b', type: 'columns', data: { cols: [{}] } };
    assert.deepEqual(childLists(bare)[0].list, []);
});

test('blocks are removed, moved and duplicated', () => {
    const blocks = tree();
    assert.equal(removeBlock(blocks, 't1').block.id, 't1');
    assert.equal(removeBlock(blocks, 'nope'), null);
    assert.deepEqual(blocks[1].data.blocks.map((block) => block.id), ['t2']);

    assert.ok(moveBy(blocks, 'a', 1));
    assert.deepEqual(blocks.map((block) => block.id), ['t', 'a', 'c', 'z']);
    assert.ok(!moveBy(blocks, 't', -1));
    assert.ok(!moveBy(blocks, 'z', 1));
    assert.ok(!moveBy(blocks, 'nope', 1));
    assert.ok(moveBy(blocks, 'a', -1));

    assert.ok(moveTo(blocks, 'z', 'c:1', 0));
    assert.deepEqual(blocks[2].data.cols[1].blocks.map((block) => block.id), ['z']);
    assert.ok(moveTo(blocks, 'a', 'root', 3), 'an index counted before the move');
    assert.deepEqual(blocks.map((block) => block.id), ['t', 'c', 'a']);
    assert.ok(moveTo(blocks, 'a', 'root', 0));
    assert.deepEqual(blocks.map((block) => block.id), ['a', 't', 'c']);
    assert.ok(moveTo(blocks, 'a', 'root', 99));
    assert.deepEqual(blocks.map((block) => block.id), ['t', 'c', 'a']);
    assert.ok(moveTo(blocks, 'a', 'root', -5));
    assert.deepEqual(blocks.map((block) => block.id), ['a', 't', 'c']);
    assert.ok(moveTo(blocks, 'a', 'root', 2));
    assert.deepEqual(blocks.map((block) => block.id), ['t', 'a', 'c']);
    assert.ok(!moveTo(blocks, 'c', 'c:0', 0), 'never into itself');
    assert.ok(!moveTo(blocks, 'nope', 'root', 0));
    assert.ok(!moveTo(blocks, 'a', 'nope', 0));
    assert.ok(moveTo(blocks, 'z', 't', 1));
    assert.deepEqual(blocks[0].data.blocks.map((block) => block.id), ['t2', 'z']);

    const ids = sequence();
    const copy = duplicate(blocks, 'c', ids);
    assert.equal(copy.id, 'n1');
    assert.equal(copy.data.cols[0].blocks[0].id, 'n2');
    assert.deepEqual(blocks.map((block) => block.id), ['t', 'a', 'c', 'n1']);
    copy.data.cols[0].blocks[0].data.text = 'changed';
    assert.equal(blocks[2].data.cols[0].blocks[0].data.text, 'c1', 'a deep copy');
    assert.equal(duplicate(blocks, 'nope', ids), null);
    assert.equal(cloneBlock(p('q'), () => 'r').id, 'r');
});

const list = (style = 'unordered') => ({
    style,
    meta: {},
    items: [
        { content: 'A', meta: {}, items: [{ content: 'A1', meta: {}, items: [] }, { content: 'A2', meta: {}, items: [] }] },
        { content: 'B', meta: {}, items: [] },
    ],
});

test('list items are split, nested, taken out and removed', () => {
    assert.deepEqual(newItem('checklist', 'x'), { content: 'x', meta: { checked: false }, items: [] });
    assert.deepEqual(newItem('ordered'), { content: '', meta: {}, items: [] });

    const data = list();
    assert.equal(itemAt(data.items, [0, 1]).content, 'A2');
    assert.equal(itemAt(data.items, [5]), undefined);
    assert.deepEqual(flatItems(data.items).map((entry) => entry.path.join('.')), ['0', '0.0', '0.1', '1']);
    assert.deepEqual(itemContents(data.items), ['A', 'A1', 'A2', 'B']);

    assert.deepEqual(splitItem(data, [0], 'A', 'a'), [1]);
    assert.deepEqual(itemContents(data.items), ['A', 'a', 'A1', 'A2', 'B'], 'the nested items follow the new item');
    assert.deepEqual(splitItem(data, [1, 1], 'A2', ''), [1, 2]);
    assert.deepEqual(itemContents(data.items), ['A', 'a', 'A1', 'A2', '', 'B']);

    const nested = list();
    assert.equal(indentItem(nested, [0]), null);
    assert.equal(indentItem(nested, [0, 0]), null);
    assert.deepEqual(indentItem(nested, [1]), [0, 2]);
    assert.deepEqual(flatItems(nested.items).map((entry) => entry.path.join('.') + entry.item.content), ['0A', '0.0A1', '0.1A2', '0.2B']);
    assert.deepEqual(indentItem(nested, [0, 1]), [0, 0, 0]);

    const out = list();
    assert.equal(outdentItem(out, [1]), null);
    assert.deepEqual(outdentItem(out, [0, 0]), [1]);
    assert.deepEqual(flatItems(out.items).map((entry) => entry.path.join('.') + entry.item.content), ['0A', '1A1', '1.0A2', '2B'], 'the items after it become its own');

    const removed = list();
    assert.equal(removeItem(removed, [0]), null);
    assert.deepEqual(itemContents(removed.items), ['A1', 'A2', 'B'], 'its nested items take its place');
    assert.deepEqual(removeItem(removed, [2]), [1]);
    const deep = list();
    assert.deepEqual(removeItem(deep, [1]), [0, 1]);
    assert.deepEqual(removeItem(deep, [0, 0]), [0]);
    assert.deepEqual(itemContents(deep.items), ['A', 'A2']);
});

test('a list changes style, its checked state kept for a checklist only', () => {
    const data = list();
    data.items[0].meta = { checked: true };
    const checklist = restyleList(data, 'checklist');
    assert.deepEqual(checklist.items.map((item) => item.meta), [{ checked: true }, { checked: false }]);
    assert.deepEqual(checklist.items[0].items[0].meta, { checked: false });
    assert.deepEqual(restyleList(checklist, 'ordered').items[0].meta, {});
    assert.equal(restyleList(data, 'ordered').style, 'ordered');
    assert.deepEqual(restyleList({ style: 'unordered', items: [{ content: 'x' }] }, 'unordered').items, [{ content: 'x', meta: {}, items: [] }]);
});

test('tables gain and lose rows and columns, never the last one', () => {
    const data = { content: [['a', 'b'], ['c', 'd']] };
    assert.deepEqual(addRow(data).content, [['a', 'b'], ['c', 'd'], ['', '']]);
    assert.deepEqual(addRow(data, 0).content[0], ['', '']);
    assert.deepEqual(addColumn(data).content.map((row) => row.length), [3, 3, 3, 3]);
    assert.deepEqual(addColumn(data, 0).content[1], ['', 'a', 'b', '']);
    assert.deepEqual(removeRow(data).content.length, 3);
    assert.deepEqual(removeRow(data, 0).content, [['', 'a', 'b', ''], ['', 'c', 'd', '']]);
    assert.deepEqual(removeColumn(data).content, [['', 'a', 'b'], ['', 'c', 'd']]);
    assert.deepEqual(removeColumn(data, 0).content, [['a', 'b'], ['c', 'd']]);

    const single = { content: [['x']] };
    assert.deepEqual(removeRow(single).content, [['x']]);
    assert.deepEqual(removeColumn(single).content, [['x']]);
    assert.deepEqual(addRow({ content: [] }).content, [['']]);
    assert.deepEqual(addColumn({ content: [] }).content, []);
    assert.deepEqual(removeColumn({ content: [] }).content, []);
});

test('the history goes back and forth, and forgets what was undone when something changes', () => {
    const history = new History(3);
    assert.equal(history.undo('a'), null);
    history.record('a');
    history.record('a');
    history.record('b');
    assert.equal(history.undo('c'), 'b');
    assert.equal(history.undo('b'), 'a');
    assert.equal(history.undo('a'), null);
    assert.equal(history.redo('a'), 'b');
    assert.equal(history.redo('b'), 'c');
    assert.equal(history.redo('c'), null);
    history.undo('c');
    history.record('x');
    assert.equal(history.redo('x'), null, 'what was undone is forgotten');

    const limited = new History(2);
    ['1', '2', '3', '4'].forEach((state) => limited.record(state));
    assert.deepEqual(limited.past, ['3', '4']);
    assert.equal(limited.undo('4'), '3', 'a state equal to the current one is skipped');
});

test('a block another writer saved is put in place', () => {
    const blocks = tree();
    const ids = (list) => list.map((block) => block.id);

    assert.deepEqual(place(blocks, p('a', 'A2')), { replaced: true, key: 'root', index: 0 });
    assert.equal(blocks[0].data.text, 'A2');
    assert.deepEqual(place(blocks, p('t2', 'T2')), { replaced: true, key: 't', index: 1 });
    assert.equal(blocks[1].data.blocks[1].data.text, 'T2');

    assert.deepEqual(place(blocks, p('n1'), 'root', 'a'), { replaced: false, key: 'root', index: 1 });
    assert.deepEqual(place(blocks, p('n2')), { replaced: false, key: 'root', index: 0 });
    assert.deepEqual(place(blocks, p('n3'), 't', null), { replaced: false, key: 't', index: 0 });
    assert.deepEqual(place(blocks, p('n4'), 'c:1', 'gone'), { replaced: false, key: 'c:1', index: 0 });
    assert.deepEqual(place(blocks, p('n5'), 't', 'gone'), { replaced: false, key: 't', index: 3 });
    assert.deepEqual(place(blocks, p('n6'), 'nowhere', 'a'), { replaced: false, key: 'root', index: 6 });

    assert.deepEqual(ids(blocks), ['n2', 'a', 'n1', 't', 'c', 'z', 'n6']);
    assert.deepEqual(ids(blocks[3].data.blocks), ['n3', 't1', 't2', 'n5']);
    assert.deepEqual(ids(blocks[4].data.cols[1].blocks), ['n4']);
});

test('the shape of a block is recognised', () => {
    assert.equal(isBlock(p('a')), true);
    assert.equal(isBlock({ id: 'a', type: 'paragraph', data: [] }), false);
    assert.equal(isBlock({ id: 1, type: 'paragraph', data: {} }), false);
    assert.equal(isBlock(null), false);
});
