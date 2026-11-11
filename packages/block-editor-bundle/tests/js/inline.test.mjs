// The inline sanitizer, which must write what phil/ujo's `rich` profile writes.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { cleanAttribute, decodeEntities, escapeHtml, fromText, isBlank, isSafeUrl, parseAttributes, profile, sanitize, textOf, tokenize } from '../../assets/src/lib/inline.js';

test('entities are decoded, and text escaped as htmlspecialchars with ENT_QUOTES', () => {
    assert.equal(decodeEntities('&amp;&lt;&gt;&quot;&#039;&#x41;&nbsp;&eacute;&unknown;&#0;&#xD800;'), '&<>"\'A é&unknown;��');
    assert.equal(escapeHtml('<a href="x">it\'s & </a>'), '&lt;a href=&quot;x&quot;&gt;it&#039;s &amp; &lt;/a&gt;');
    assert.equal(fromText('a\nb\r\nc<'), 'a<br>b<br>c&lt;');
});

test('addresses are links or sources as phil/ujo\'s SafeUrl says', () => {
    for (const url of ['https://example.com', 'http://example.com/a?b#c', 'mailto:a@example.com', 'tel:+15555550100', '/page', 'page', '#top', '?q=1']) {
        assert.ok(isSafeUrl(url), url);
    }

    for (const url of ['', 'javascript:alert(1)', 'JAVASCRIPT:x', '//evil.example', 'https://user@evil.example', 'http:/x', 'a b', 'data:image/png;base64,x', 'https:\\\\x']) {
        assert.ok(!isSafeUrl(url), url);
    }

    assert.ok(!isSafeUrl('mailto:a@example.com', { media: true }));
    assert.ok(isSafeUrl('https://example.com/a.png', { media: true }));
});

test('attribute values are cleaned or refused', () => {
    assert.equal(cleanAttribute('link', 'https://example.com'), 'https://example.com');
    assert.equal(cleanAttribute('link', 'javascript:x'), null);
    assert.equal(cleanAttribute('token', 'accent-2'), 'accent-2');
    assert.equal(cleanAttribute('token', 'a b'), null);
    assert.equal(cleanAttribute('reference', 'user:42'), 'user:42');
    assert.equal(cleanAttribute('reference', 'User:42'), null);
    assert.equal(cleanAttribute('target', '_BLANK'), '_blank');
    assert.equal(cleanAttribute('target', '_self'), null);
    assert.equal(cleanAttribute('rel', 'NoFollow evil noopener'), 'nofollow noopener');
    assert.equal(cleanAttribute('rel', 'evil'), null);
});

test('tokens: text decoded, attributes parsed, comments and dropped elements left out', () => {
    assert.deepEqual(tokenize('a<!-- c --><B Class=x id="y" title=\'z\' hidden>b</b><script>no</script><svg/>c<![if !supportLists]>d<![endif]><!DOCTYPE html>'), [
        { kind: 'text', text: 'a' },
        { kind: 'start', name: 'b', attributes: { class: 'x', id: 'y', title: 'z', hidden: '' } },
        { kind: 'text', text: 'b' },
        { kind: 'end', name: 'b' },
        { kind: 'text', text: 'c' },
        { kind: 'text', text: 'd' },
    ]);
    assert.deepEqual(tokenize('<![if !supportLists]>x<![endif]>', { conditions: true }), [
        { kind: 'if', condition: 'if !supportlists' },
        { kind: 'text', text: 'x' },
        { kind: 'if', condition: 'endif' },
    ]);
    assert.deepEqual(tokenize('<style>x'), []);
    assert.deepEqual(parseAttributes(' a="1" A="2" b'), { a: '1', b: '' });
});

test('the rich profile written as phil/ujo writes it', () => {
    assert.equal(sanitize('<strong>b</strong> <em>i</em> <u>u</u> <del>s</del> <mark>m</mark> <span class="inline-code">c</span> <sup>1</sup><sub>2</sub>x<br>y'),
        '<b>b</b> <i>i</i> <u class="cdx-underline">u</u> <s class="cdx-strikethrough">s</s> <mark class="cdx-marker">m</mark> <code class="inline-code">c</code> <sup>1</sup><sub>2</sub>x<br>y');
    assert.equal(sanitize('<a href="/p" target="_blank" rel="nofollow x" onclick="x">l</a>'), '<a href="/p" target="_blank" rel="nofollow">l</a>');
    assert.equal(sanitize('<a href="javascript:x">l</a>'), 'l');
    assert.equal(sanitize('<a href="/u" data-mention="user:42">@Ada</a> <a data-ref="page:7">P</a>'), '<a href="/u" data-mention="user:42">@Ada</a> <a data-ref="page:7">P</a>');
    assert.equal(sanitize('<span class="text-color" data-color="accent">c</span><span class="text-color">d</span><font color="red">e</font>'), '<span class="text-color" data-color="accent">c</span>de');
    assert.equal(sanitize('<a href="/a">x<a href="/b">y</a>z</a>'), '<a href="/a">xyz</a>');
    assert.equal(sanitize('<b>a<i>b</b>c'), '<b>a<i>b</i></b>c');
    assert.equal(sanitize('</i>a<div>b</div><p>c'), 'abc');
    assert.equal(sanitize('a & <b title="x">"q"</b>'), 'a &amp; <b>&quot;q&quot;</b>');
});

test('a profile keeps only the tools named', () => {
    const reduced = profile(['bold', 'link']);
    assert.deepEqual(reduced.map((tool) => tool.name), ['bold', 'link']);
    assert.equal(sanitize('<b>b</b><i>i</i><a href="/x">l</a><br>', reduced), '<b>b</b>i<a href="/x">l</a>');
});

test('pasted styles become marks, Google Docs\' bold wrapper does not', () => {
    const html = '<b style="font-weight:normal;" id="docs-internal-guid-1"><span style="font-weight:700">bold</span> <span style="font-style:italic;text-decoration:underline line-through">both</span> <span style="vertical-align:super">up</span></b>';
    assert.equal(sanitize(html, undefined, { styles: true }), '<b>bold</b> <i><u class="cdx-underline"><s class="cdx-strikethrough">both</s></u></i> <sup>up</sup>');
    assert.equal(sanitize('<b style="font-weight:normal">x</b>'), '<b>x</b>', 'without styles, a b is bold');
    assert.equal(sanitize('<b><span style="font-weight:bold">x</span></b>', undefined, { styles: true }), '<b>x</b>', 'never bold in bold');
    assert.equal(sanitize('<span style="font-weight:700">x</span>', profile(['italic']), { styles: true }), 'x');
    assert.equal(sanitize('<br style="font-weight:700">', undefined, { styles: true }), '<br>', 'a line break takes no mark');
});

test('the text of a fragment, and whether it shows anything', () => {
    assert.equal(textOf('a<b>b</b><br>c&amp;'), 'ab\nc&');
    assert.ok(isBlank(' <br> &nbsp;<b></b>'));
    assert.ok(!isBlank('<b>x</b>'));
});
