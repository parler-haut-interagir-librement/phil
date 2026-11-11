// Telling the changed blocks apart.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { changes, childLists, isBlock, parseBlocks, shape, snapshot } from '../../assets/src/lib/snapshot.js';

const p = (id, text = id) => ({ id, type: 'paragraph', data: { text } });

const doc = () => [
    p('a'),
    { id: 't', type: 'toggle', data: { text: 'T', open: true, blocks: [p('t1'), p('t2')] } },
    { id: 'c', type: 'columns', data: { cols: [{ blocks: [p('c1')] }, {}] } },
    { id: 'x', type: 'paragraph', data: [] },
    p('z'),
];

test('the blocks of a document are read leniently', () => {
    assert.deepEqual(parseBlocks('{"blocks":[{"id":"a"}]}'), [{ id: 'a' }]);
    assert.deepEqual(parseBlocks(''), []);
    assert.deepEqual(parseBlocks('{nope'), []);
    assert.deepEqual(parseBlocks('null'), []);
    assert.deepEqual(parseBlocks('{"blocks":{}}'), []);
    assert.equal(isBlock(p('a')), true);
    assert.equal(isBlock(null), false);
    assert.equal(isBlock({ id: 'a', type: 'p', data: [] }), false);
    assert.equal(isBlock({ id: 'a', type: 1, data: {} }), false);
});

test('a snapshot knows each block, its list and the block before it', () => {
    const known = snapshot(doc());

    assert.deepEqual([...known.keys()], ['a', 't', 't1', 't2', 'c', 'c1', 'z']);
    assert.deepEqual(['a', 't', 't1', 't2', 'c', 'c1', 'z'].map((id) => [known.get(id).list, known.get(id).after]), [
        ['root', null], ['root', 'a'], ['t', null], ['t', 't1'], ['root', 't'], ['c:0', null], ['root', 'c'],
    ]);
    assert.equal(known.get('t').shape, JSON.stringify(['toggle', { text: 'T', open: true, blocks: ['t1', 't2'] }, null]));
    assert.equal(known.get('c').shape, JSON.stringify(['columns', { cols: [{ blocks: ['c1'] }, { blocks: [] }] }, null]));
    assert.equal(shape({ id: 'q', type: 'quote', data: { text: 'x' }, tunes: { anchor: 'y' } }), JSON.stringify(['quote', { text: 'x' }, { anchor: 'y' }]));
    assert.deepEqual(childLists(p('a')), []);
    assert.deepEqual(childLists({ id: 'c', type: 'columns', data: { cols: [null] } }), [{ key: 'c:0', list: [] }]);
});

test('the changes from one snapshot to the next', () => {
    const before = snapshot(doc());
    const next = doc();
    next[1].data.blocks[1] = p('t2', 'changed');
    next[1].data.blocks.push(p('t3'));
    next.splice(0, 1);
    next.push(p('a'));

    assert.deepEqual(changes(before, snapshot(next)), { saved: ['t', 't2', 't3', 'a'], removed: [] });
    assert.deepEqual(changes(before, snapshot(doc())), { saved: [], removed: [] });
    assert.deepEqual(changes(before, snapshot([p('a')])), { saved: [], removed: ['t', 't1', 't2', 'c', 'c1', 'z'] });

    const moved = doc();
    moved[2].data.cols[1].blocks = [moved[2].data.cols[0].blocks.pop()];
    assert.deepEqual(changes(before, snapshot(moved)), { saved: ['c', 'c1'], removed: [] });
});
