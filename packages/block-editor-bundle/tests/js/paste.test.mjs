// Pasted HTML and text turned into blocks of the toolset.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { pasteBlocks, textBlocks } from '../../assets/src/lib/paste.js';
import { BLOCK_TOOLS, INLINE_TOOLS } from '../../assets/src/lib/tools.js';
import { sequence } from './ids.mjs';

const full = () => ({ tools: BLOCK_TOOLS, inlineTools: INLINE_TOOLS, makeId: sequence() });
const shapes = (blocks) => blocks.map((block) => [block.type, block.data]);
const item = (content, items = [], meta = {}) => ({ content, meta, items });

test('plain text becomes paragraphs, single new lines kept as line breaks', () => {
    assert.deepEqual(textBlocks('one\ntwo <3\r\n\r\n  \n\nthree\n', { makeId: sequence() }), [
        { id: 'n1', type: 'paragraph', data: { text: 'one<br>two &lt;3' } },
        { id: 'n2', type: 'paragraph', data: { text: 'three' } },
    ]);
    assert.deepEqual(textBlocks('   '), []);
    assert.match(textBlocks('x')[0].id, /^[A-Za-z0-9_-]{10}$/);
});

test('a page\'s HTML: paragraphs, headings, quotes, rules, code and images', () => {
    const html = '<html><head><style>p{}</style><title>t</title></head><body><h2>Title <em>here</em></h2>\n<p>One\n <strong>two</strong>  three</p><div>Four<br>five</div><blockquote>Q</blockquote><hr><pre><code>a &lt; b\n  c<br>d</code></pre><img src="https://example.com/a.png"><img src="javascript:x"><p>  </p><script>x</script></body></html>';
    assert.deepEqual(shapes(pasteBlocks(html, full())), [
        ['header', { text: 'Title <i>here</i>', level: 2 }],
        ['paragraph', { text: 'One <b>two</b> three' }],
        ['paragraph', { text: 'Four<br>five' }],
        ['quote', { text: 'Q', caption: '', alignment: 'left' }],
        ['delimiter', {}],
        ['code', { code: 'a < b\n  c\nd' }],
        ['image', { file: { url: 'https://example.com/a.png' }, caption: '', withBorder: false, withBackground: false, stretched: false }],
    ]);
});

test('inline text outside any block, and two line breaks, make paragraphs', () => {
    assert.deepEqual(shapes(pasteBlocks('a <b>b</b><br><br>c<br>d', full())), [
        ['paragraph', { text: 'a <b>b</b>' }],
        ['paragraph', { text: 'c<br>d' }],
    ]);
});

test('nested lists and checklists, stray items and unclosed lists', () => {
    const html = '<ul><li>A<ul><li>A1</li></ul></li><li><p>B</p><p>b</p></li></ul><ol><li>One</li></ol><ul><ul><li>deep</li></ul></ul><ul><li><input type="checkbox" checked> done</li><li><input type="checkbox"> todo</li></ul><li>stray</li><ul><li>open';
    assert.deepEqual(shapes(pasteBlocks(html, full())), [
        ['list', { style: 'unordered', meta: {}, items: [item('A', [item('A1')]), item('B<br>b')] }],
        ['list', { style: 'ordered', meta: {}, items: [item('One')] }],
        ['list', { style: 'unordered', meta: {}, items: [item('', [item('deep')])] }],
        ['list', { style: 'checklist', meta: {}, items: [item('done', [], { checked: true }), item('todo', [], { checked: false })] }],
        ['paragraph', { text: 'stray' }],
        ['list', { style: 'unordered', meta: {}, items: [item('open')] }],
    ]);
});

test('Word\'s list paragraphs become lists, its bullets and markers dropped', () => {
    const html = '<p class=MsoNormal>Intro<o:p></o:p></p>'
        + '<p class=MsoListParagraphCxSpFirst style=\'mso-list:l0 level1 lfo1\'><![if !supportLists]><span style=\'font-family:Symbol\'>·<span>&nbsp;&nbsp; </span></span><![endif]>First</p>'
        + '<p class=MsoListParagraphCxSpMiddle style=\'mso-list:l0 level2 lfo1\'><!--[if !supportLists]--><span style=\'mso-list:Ignore\'>o<span>&nbsp; </span></span><!--[endif]-->Nested</p>'
        + '<p class=MsoListParagraphCxSpLast style=\'mso-list:l0 level3 lfo1\'><span style=\'mso-list:Ignore\'>·</span>Deeper</p>'
        + '<p class=MsoListParagraph style=\'mso-list:l0 level1 lfo1\'><span style=\'mso-list:Ignore\'>·</span>Second</p>'
        + '<p class=MsoNormal></p>'
        + '<p class=MsoNormal>Between</p>'
        + '<p style=\'mso-list:l1 level1 lfo2\'><span style=\'mso-list:Ignore\'>1.<span> </span></span>Numbered</p>'
        + '<p class=MsoListParagraph><span style=\'mso-list:Ignore\'>a)</span>Lettered</p>';
    assert.deepEqual(shapes(pasteBlocks(html, full())), [
        ['paragraph', { text: 'Intro' }],
        ['list', { style: 'unordered', meta: {}, items: [item('First', [item('Nested', [item('Deeper')])]), item('Second')] }],
        ['paragraph', { text: 'Between' }],
        ['list', { style: 'ordered', meta: {}, items: [item('Numbered'), item('Lettered')] }],
    ]);
});

test('Word list items jump at most one level deeper', () => {
    const html = '<p style="mso-list:l0 level3 lfo1">Deep first</p><p style="mso-list:l0 level1 lfo1">Back</p>';
    assert.deepEqual(shapes(pasteBlocks(html, full())), [
        ['list', { style: 'unordered', meta: {}, items: [item('Deep first'), item('Back')] }],
    ]);
});

test('tables, with a header row when the first row has header cells', () => {
    const html = '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td><p>1</p><p>one</p></td></tr><tr></tr></tbody></table><table><tr><td>x</td></tr></table>';
    assert.deepEqual(shapes(pasteBlocks(html, full())), [
        ['table', { withHeadings: true, stretched: false, content: [['A', 'B'], ['1<br>one', '']] }],
        ['table', { withHeadings: false, stretched: false, content: [['x']] }],
    ]);
    assert.deepEqual(shapes(pasteBlocks('<table></table><td>z</td><tr><td>w</td></tr>', full())), [['paragraph', { text: 'z' }], ['paragraph', { text: 'w' }]]);
});

test('what the toolset does not offer falls back to paragraphs, marks it does not offer are unwrapped', () => {
    const html = '<h1>T</h1><blockquote>Q</blockquote><ul><li>A<ul><li>B</li></ul></li><li></li></ul><table><tr><td>c</td><td></td></tr></table><pre>x\ny</pre><pre> </pre><hr><img src="/a.png"><p><u>u</u> <b>b</b></p>';
    assert.deepEqual(shapes(pasteBlocks(html, { tools: ['paragraph'], inlineTools: ['bold'], makeId: sequence() })), [
        ['paragraph', { text: 'T' }],
        ['paragraph', { text: 'Q' }],
        ['paragraph', { text: 'A' }],
        ['paragraph', { text: 'B' }],
        ['paragraph', { text: 'c' }],
        ['paragraph', { text: 'x<br>y' }],
        ['paragraph', { text: 'u <b>b</b>' }],
    ]);
    assert.deepEqual(shapes(pasteBlocks('<p>x</p>')), [['paragraph', { text: 'x' }]]);
    assert.match(pasteBlocks('<p>x</p>')[0].id, /^[A-Za-z0-9_-]{10}$/);
});

test('styles from Google Docs become marks', () => {
    const html = '<meta charset="utf-8"><b style="font-weight:normal;" id="docs-internal-guid-x"><p dir="ltr"><span style="font-weight:700;">Bold</span><span style="font-style:italic;"> italic</span></p></b>';
    assert.deepEqual(shapes(pasteBlocks(html, full())), [['paragraph', { text: '<b>Bold</b><i> italic</i>' }]]);
});

test('a code block left open keeps what it holds', () => {
    assert.deepEqual(shapes(pasteBlocks('<p>a</p><pre>x', full())), [['paragraph', { text: 'a' }]]);
    assert.deepEqual(shapes(pasteBlocks('<pre>a<p>b</p>c</pre>', full())), [['code', { code: 'abc' }]]);
});
