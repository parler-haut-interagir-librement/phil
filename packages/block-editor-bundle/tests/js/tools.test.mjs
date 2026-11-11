// The block tools: data as phil/ujo writes it, conversions, menus and actions.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { BLOCK_TOOLS, INLINE_TOOLS, PRESETS, TOOLS, blockActions, columnCount, conversions, convert, createBlock, holdsText, isEmpty, isKnown, slashItems } from '../../assets/src/lib/tools.js';
import { sequence } from './ids.mjs';

test('the tools and presets are those of the bundle\'s Toolset', () => {
    assert.deepEqual(BLOCK_TOOLS, ['paragraph', 'header', 'list', 'quote', 'code', 'alert', 'toggle', 'columns', 'table', 'image', 'delimiter']);
    assert.deepEqual(INLINE_TOOLS, ['bold', 'italic', 'underline', 'strikethrough', 'inlineCode', 'link', 'marker', 'textColor', 'superscript', 'subscript', 'mention', 'wikiLink', 'lineBreak']);
    assert.deepEqual(PRESETS.reduced, { tools: ['paragraph', 'header', 'list', 'quote'], inlineTools: ['bold', 'italic', 'link', 'lineBreak'] });
    assert.deepEqual(Object.keys(TOOLS), BLOCK_TOOLS);
});

test('new blocks start with the data phil/ujo writes', () => {
    const ids = sequence();
    assert.deepEqual(createBlock('paragraph', {}, ids), { id: 'n1', type: 'paragraph', data: { text: '' } });
    assert.deepEqual(createBlock('header', { level: 3 }, ids).data, { text: '', level: 3 });
    assert.deepEqual(createBlock('header', undefined, ids).data, { text: '', level: 2 });
    assert.deepEqual(createBlock('list', {}, ids).data, { style: 'unordered', meta: {}, items: [{ content: '', meta: {}, items: [] }] });
    assert.deepEqual(createBlock('list', { style: 'checklist', checked: true }, ids).data.items[0].meta, { checked: true });
    assert.deepEqual(createBlock('list', { style: 'checklist' }, ids).data.items[0].meta, { checked: false });
    assert.deepEqual(createBlock('quote', {}, ids).data, { text: '', caption: '', alignment: 'left' });
    assert.deepEqual(createBlock('code', {}, ids).data, { code: '' });
    assert.deepEqual(createBlock('code', { language: 'php' }, ids).data, { code: '', language: 'php' });
    assert.deepEqual(createBlock('alert', {}, ids).data, { type: 'info', align: 'left', text: '' });
    assert.deepEqual(createBlock('alert', { type: 'danger' }, ids).data.type, 'danger');
    assert.deepEqual(createBlock('toggle', {}, ids).data, { text: '', open: true, blocks: [] });
    assert.deepEqual(createBlock('columns', {}, ids).data, { cols: [{ blocks: [] }, { blocks: [] }] });
    assert.equal(createBlock('columns', { count: 3 }, ids).data.cols.length, 3);
    assert.deepEqual(createBlock('table', {}, ids).data, { withHeadings: false, stretched: false, content: [['', ''], ['', '']] });
    assert.deepEqual(createBlock('image', {}, ids).data, { file: { url: '' }, caption: '', withBorder: false, withBackground: false, stretched: false });
    assert.deepEqual(createBlock('delimiter', {}, ids).data, {});
    assert.match(createBlock('paragraph').id, /^[A-Za-z0-9_-]{10}$/);
});

test('a block showing nothing is empty; a type the editor does not know never is', () => {
    const empty = [
        ['paragraph', { text: ' <br>' }], ['header', { text: '' }], ['quote', { text: '', caption: '' }], ['code', { code: '  ' }],
        ['alert', { text: '' }], ['toggle', { text: '', blocks: [] }], ['columns', { cols: [{ blocks: [] }] }],
        ['table', { content: [['', ' ']] }], ['image', { file: { url: '' } }], ['image', {}],
        ['list', { items: [{ content: '', items: [{ content: ' ', items: [] }] }] }],
    ];
    const full = [
        ['paragraph', { text: 'x' }], ['header', { text: 'x' }], ['quote', { text: '', caption: 'x' }], ['code', { code: 'x' }],
        ['alert', { text: 'x' }], ['toggle', { text: '', blocks: [{}] }], ['columns', { cols: [{ blocks: [] }, { blocks: [{}] }] }],
        ['table', { content: [['', 'x']] }], ['image', { file: { url: '/x.png' } }], ['delimiter', {}],
        ['list', { items: [{ content: '', items: [{ content: 'x', items: [] }] }] }], ['statblock', {}],
    ];

    for (const [type, data] of empty) {
        assert.ok(isEmpty({ type, data }), type);
    }

    for (const [type, data] of full) {
        assert.ok(!isEmpty({ type, data }), type);
    }

    assert.ok(isKnown('paragraph'));
    assert.ok(!isKnown('statblock'));
    assert.ok(!isKnown('constructor'));
    assert.ok(holdsText('code'));
    assert.ok(!holdsText('table'));
    assert.ok(!holdsText('statblock'));
});

test('blocks holding text convert to one another', () => {
    const ids = sequence();
    const paragraph = { id: 'p', type: 'paragraph', data: { text: 'a<br><b>b</b>' }, tunes: { anchor: { anchor: 'x' } } };
    assert.deepEqual(convert(paragraph, 'header', { level: 3 }, ids), [{ id: 'p', type: 'header', data: { text: 'a<br><b>b</b>', level: 3 }, tunes: { anchor: { anchor: 'x' } } }]);
    assert.deepEqual(convert(paragraph, 'list', { style: 'ordered' }, ids)[0].data, { style: 'ordered', meta: {}, items: [{ content: 'a', meta: {}, items: [] }, { content: '<b>b</b>', meta: {}, items: [] }] });
    assert.deepEqual(convert(paragraph, 'list', undefined, ids)[0].data.style, 'unordered');
    assert.deepEqual(convert(paragraph, 'code', {}, ids)[0].data, { code: 'a\nb' });
    assert.deepEqual(convert({ id: 'c', type: 'code', data: { code: 'x <y>\nz' } }, 'paragraph', {}, ids)[0].data, { text: 'x &lt;y&gt;<br>z' });
    assert.deepEqual(convert(paragraph, 'quote', {}, ids)[0].data, { text: 'a<br><b>b</b>', caption: '', alignment: 'left' });
    assert.deepEqual(convert(paragraph, 'alert', { type: 'warning' }, ids)[0].data, { type: 'warning', align: 'left', text: 'a<br><b>b</b>' });
    assert.deepEqual(convert(paragraph, 'alert', undefined, ids)[0].data.type, 'info');
    assert.deepEqual(convert(paragraph, 'toggle', {}, ids)[0].data, { text: 'a<br><b>b</b>', open: true, blocks: [] });
    assert.deepEqual(convert({ id: 'h', type: 'header', data: { text: 'x', level: 1 } }, 'header', undefined, ids)[0].data.level, 2);

    const toggle = { id: 't', type: 'toggle', data: { text: 'T', open: false, blocks: [{ id: 'i', type: 'paragraph', data: { text: 'in' } }] } };
    assert.deepEqual(convert(toggle, 'paragraph', {}, ids).map((block) => block.id), ['t', 'i'], 'the nested blocks follow');

    const list = { id: 'l', type: 'list', data: { style: 'unordered', meta: {}, items: [{ content: 'A', meta: {}, items: [{ content: 'A1', meta: {}, items: [] }] }, { content: 'B', meta: {}, items: [] }] } };
    assert.deepEqual(convert(list, 'paragraph', {}, ids), [
        { id: 'l', type: 'paragraph', data: { text: 'A' } },
        { id: 'n1', type: 'paragraph', data: { text: 'A1' } },
        { id: 'n2', type: 'paragraph', data: { text: 'B' } },
    ]);
    assert.deepEqual(convert(list, 'list', { style: 'checklist' }, ids)[0].data.items[0].items[0], { content: 'A1', meta: { checked: false }, items: [] });
    assert.equal(convert(list, 'list', {}, ids)[0].data.style, 'unordered');
    assert.equal(convert({ id: 't', type: 'table', data: {} }, 'paragraph'), null);
    assert.equal(convert(paragraph, 'image'), null);
    assert.equal(convert(list, 'paragraph')[1].id.length, 10);
    assert.deepEqual(TOOLS.list.text(list.data), 'A<br>A1<br>B');
});

test('the slash menu lists the tools offered, the turn-into menu those holding text', () => {
    assert.deepEqual(slashItems(BLOCK_TOOLS).map((entry) => entry.key), ['paragraph', 'heading1', 'heading2', 'heading3', 'unordered', 'ordered', 'checklist', 'quote', 'code', 'alert', 'toggle', 'columns2', 'columns3', 'table', 'image', 'delimiter']);
    assert.deepEqual(slashItems(['paragraph', 'quote']).map((entry) => entry.key), ['paragraph', 'quote']);
    assert.deepEqual(slashItems(['header'])[2], { key: 'heading3', type: 'header', options: { level: 3 }, keywords: ['h3', 'heading', 'titre'] });
    assert.deepEqual(conversions({ type: 'paragraph' }, ['paragraph', 'table', 'code']).map((entry) => entry.key), ['paragraph', 'code']);
    assert.deepEqual(conversions({ type: 'table' }, BLOCK_TOOLS), []);
});

const run = (block, key) => blockActions(block).find((action) => action.key === key);

test('the block menu\'s actions belong to each type', () => {
    const header = { type: 'header', data: { text: 'x', level: 2 } };
    assert.deepEqual(blockActions(header).map((action) => action.key), ['level1', 'level2', 'level3', 'level4', 'level5', 'level6']);
    assert.ok(run(header, 'level2').active(header.data));
    assert.ok(!run(header, 'level3').active(header.data));
    assert.deepEqual(run(header, 'level3').apply(header.data), { text: 'x', level: 3 });

    const list = { type: 'list', data: { style: 'unordered', meta: {}, items: [{ content: 'a', meta: {}, items: [] }] } };
    assert.deepEqual(blockActions(list).map((action) => action.key), ['unordered', 'ordered', 'checklist']);
    assert.ok(run(list, 'unordered').active(list.data));
    assert.ok(!run(list, 'ordered').active(list.data));
    assert.deepEqual(run(list, 'checklist').apply(list.data).items[0].meta, { checked: false });

    const alert = { type: 'alert', data: { type: 'info', align: 'left', text: '' } };
    assert.deepEqual(blockActions(alert).map((action) => action.key), ['alert.info', 'alert.success', 'alert.warning', 'alert.danger']);
    assert.equal(run(alert, 'alert.danger').apply(alert.data).type, 'danger');

    const quote = { type: 'quote', data: { alignment: 'left' } };
    assert.deepEqual(blockActions(quote).map((action) => action.key), ['align.left', 'align.center']);
    assert.equal(run(quote, 'align.center').apply(quote.data).alignment, 'center');

    const image = { type: 'image', data: { file: { url: '/x' }, withBorder: false, withBackground: true, stretched: false } };
    assert.deepEqual(blockActions(image).map((action) => action.key), ['withBorder', 'withBackground', 'stretched', 'replaceImage']);
    assert.ok(run(image, 'withBackground').active(image.data));
    assert.ok(!run(image, 'withBorder').active(image.data));
    assert.ok(!run(image, 'replaceImage').active(image.data));
    assert.equal(run(image, 'withBorder').apply(image.data).withBorder, true);
    assert.deepEqual(run(image, 'replaceImage').apply(image.data).file, { url: '' });

    const table = { type: 'table', data: { withHeadings: false, stretched: false, content: [['a', 'b']] } };
    assert.deepEqual(blockActions(table).map((action) => action.key), ['withHeadings', 'stretched', 'addRow', 'addColumn', 'removeRow', 'removeColumn']);
    assert.deepEqual(run(table, 'addRow').apply(table.data).content, [['a', 'b'], ['', '']]);
    assert.deepEqual(run(table, 'addColumn').apply(table.data).content, [['a', 'b', '']]);
    assert.deepEqual(run(table, 'removeRow').apply(table.data).content, [['a', 'b']]);
    assert.deepEqual(run(table, 'removeColumn').apply(table.data).content, [['a']]);
    assert.deepEqual(table.data.content, [['a', 'b']], 'the data itself is left as it is');
    assert.ok(['addRow', 'addColumn', 'removeRow', 'removeColumn'].every((key) => !run(table, key).active(table.data)));

    const columns = { type: 'columns', data: { cols: [{ blocks: [] }, { blocks: [] }] } };
    assert.deepEqual(blockActions(columns).map((action) => action.key), ['columns2', 'columns3', 'columns4']);
    assert.ok(run(columns, 'columns2').active(columns.data));
    assert.equal(run(columns, 'columns3').apply(columns.data).cols.length, 3);

    assert.deepEqual(blockActions({ type: 'paragraph', data: {} }), []);
});

test('fewer columns move the blocks of those taken away to the last one kept', () => {
    const data = { cols: [{ blocks: ['a'] }, { blocks: ['b'] }, { blocks: ['c'] }] };
    assert.deepEqual(columnCount(data, 2).cols, [{ blocks: ['a'] }, { blocks: ['b', 'c'] }]);
    assert.deepEqual(columnCount(data, 4).cols.length, 4);
    assert.deepEqual(data.cols[1].blocks, ['b']);
});
