// The ProseMirror trial's schema specs, which must read and write marks by the sanitizer's rules.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { INLINE_TOOLS, profile } from '../../assets/src/lib/inline.js';
import { attributesOf, markAttributes, markDom, schemaSpec, styleRules } from '../../assets/src/lib/rich-schema.js';

const tool = (name) => INLINE_TOOLS.find((candidate) => candidate.name === name);

/** An element as ProseMirror's parser hands it to `getAttrs`: a tag name and attributes. */
const element = (tagName, attributes = {}) => ({
    tagName: tagName.toUpperCase(),
    attributes: Object.entries(attributes).map(([name, value]) => ({ name, value })),
});

test('the attributes of an element are read by name, none when it has none', () => {
    assert.deepEqual(attributesOf(element('a', { href: '/x', rel: 'nofollow' })), { href: '/x', rel: 'nofollow' });
    assert.deepEqual(attributesOf({}), {});
});

test('an element a tool accepts gives its cleaned attributes, any other is refused', () => {
    assert.deepEqual(markAttributes(tool('bold'), element('strong')), {});
    assert.deepEqual(markAttributes(tool('link'), element('a', { href: 'https://example.com', target: '_blank', onclick: 'x' })), {
        href: 'https://example.com',
        target: '_blank',
        rel: null,
    });
    assert.equal(markAttributes(tool('link'), element('a', { href: 'javascript:alert(1)' })), false);
    assert.equal(markAttributes(tool('link'), element('a')), false);
    assert.equal(markAttributes(tool('textColor'), element('span', { class: 'text-color', 'data-color': 'a b' })), false);
    assert.deepEqual(markAttributes(tool('textColor'), element('span', { class: 'x text-color', 'data-color': 'accent' })), {
        'data-color': 'accent',
    });
    assert.equal(markAttributes(tool('inlineCode'), element('span')), false);
    assert.deepEqual(markAttributes(tool('inlineCode'), element('span', { class: 'inline-code' })), {});
    assert.deepEqual(markAttributes(tool('mention'), { nodeName: 'A', attributes: [{ name: 'data-mention', value: 'user:1' }] }), {
        href: null,
        'data-mention': 'user:1',
    });
});

test('a bold element of normal weight is bold only when styles are not read', () => {
    const wrapper = element('b', { style: 'font-weight:normal;' });

    assert.equal(markAttributes(tool('bold'), wrapper, { styles: true }), false);
    assert.equal(markAttributes(tool('bold'), element('b', { style: 'font-weight: 400' }), { styles: true }), false);
    assert.deepEqual(markAttributes(tool('bold'), element('b', { style: 'font-weight: 700' }), { styles: true }), {});
    assert.deepEqual(markAttributes(tool('bold'), wrapper), {});
    assert.deepEqual(markAttributes(tool('italic'), element('i', { style: 'font-weight:normal' }), { styles: true }), {});
});

test('a mark is written in its canonical form, an attribute that no longer passes left out', () => {
    assert.deepEqual(markDom(tool('bold')), ['b', {}, 0]);
    assert.deepEqual(markDom(tool('underline')), ['u', { class: 'cdx-underline' }, 0]);
    assert.deepEqual(markDom(tool('link'), { href: 'https://example.com', target: null, rel: 'nofollow' }), [
        'a',
        { href: 'https://example.com', rel: 'nofollow' },
        0,
    ]);
    assert.deepEqual(markDom(tool('link'), { href: 'javascript:x' }), ['a', {}, 0]);
    assert.deepEqual(markDom(tool('textColor'), { 'data-color': 'accent' }), ['span', { class: 'text-color', 'data-color': 'accent' }, 0]);
});

test('style rules match the values Word and Google Docs write, and nothing else', () => {
    const [bold] = styleRules(tool('bold'));
    assert.equal(bold.style, 'font-weight');
    assert.equal(bold.getAttrs('700'), null);
    assert.equal(bold.getAttrs(' bold '), null);
    assert.equal(bold.getAttrs('bolder'), null);
    assert.equal(bold.getAttrs('400'), false);
    assert.equal(bold.getAttrs('normal'), false);
    assert.equal(bold.getAttrs('1000'), false);

    const underline = styleRules(tool('underline'));
    assert.deepEqual(
        underline.map((rule) => rule.style),
        ['text-decoration', 'text-decoration-line'],
    );
    assert.equal(underline[0].getAttrs('underline dotted'), null);
    assert.equal(underline[1].getAttrs('line-through'), false);
    assert.equal(styleRules(tool('strikethrough'))[0].getAttrs('line-through'), null);
    assert.equal(styleRules(tool('italic'))[0].getAttrs('italic'), null);
    assert.equal(styleRules(tool('italic'))[0].getAttrs('oblique'), false);
    assert.equal(styleRules(tool('superscript'))[0].getAttrs('super'), null);
    assert.equal(styleRules(tool('subscript'))[0].getAttrs('super'), false);
    assert.deepEqual(styleRules(tool('link')), []);
});

test('the schema holds inline content, one mark per tool, a line break only when the tool is on', () => {
    const spec = schemaSpec(profile());

    assert.deepEqual(Object.keys(spec.nodes), ['doc', 'text', 'hard_break']);
    assert.equal(spec.nodes.doc.content, 'inline*');
    assert.deepEqual(spec.nodes.hard_break.parseDOM, [{ tag: 'br' }]);
    assert.deepEqual(spec.nodes.hard_break.toDOM(), ['br']);
    assert.deepEqual(
        Object.keys(spec.marks),
        INLINE_TOOLS.filter((candidate) => 'br' !== candidate.tag).map((candidate) => candidate.name),
    );
    assert.deepEqual(Object.keys(schemaSpec(profile(['bold'])).nodes), ['doc', 'text']);
});

test('links exclude each other and do not grow at their end; other marks do', () => {
    const { marks } = schemaSpec(profile(['bold', 'link', 'mention']));

    assert.equal(marks.link.inclusive, false);
    assert.equal(marks.link.excludes, 'mention link');
    assert.equal(marks.mention.excludes, 'mention link');
    assert.equal(marks.bold.inclusive, true);
    assert.equal('excludes' in marks.bold, false);
    assert.deepEqual(marks.link.attrs, { href: { default: null }, target: { default: null }, rel: { default: null } });
    assert.deepEqual(marks.bold.attrs, {});
});

test('a mark is parsed from every element its tool accepts, then from styles unless they are turned off', () => {
    const { marks } = schemaSpec(profile(['bold', 'strikethrough', 'link']));

    assert.deepEqual(
        marks.bold.parseDOM.map((rule) => rule.tag ?? rule.style),
        ['b', 'strong', 'font-weight'],
    );
    assert.deepEqual(
        marks.strikethrough.parseDOM.map((rule) => rule.tag ?? rule.style),
        ['s', 'strike', 'del', 'text-decoration', 'text-decoration-line'],
    );
    assert.equal(marks.bold.parseDOM[0].getAttrs(element('b', { style: 'font-weight:normal' })), false);
    assert.deepEqual(marks.link.parseDOM[0].getAttrs(element('a', { href: '/page' })), { href: '/page', target: null, rel: null });
    assert.deepEqual(marks.link.toDOM({ attrs: { href: '/page', target: null, rel: null } }), ['a', { href: '/page' }, 0]);

    const plain = schemaSpec(profile(['bold']), { styles: false }).marks.bold;
    assert.deepEqual(
        plain.parseDOM.map((rule) => rule.tag),
        ['b', 'strong'],
    );
    assert.deepEqual(plain.parseDOM[0].getAttrs(element('b', { style: 'font-weight:normal' })), {});
});
