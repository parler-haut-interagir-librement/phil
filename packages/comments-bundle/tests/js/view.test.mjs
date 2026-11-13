import assert from 'node:assert/strict';
import { test } from 'node:test';

import { translator } from '../../assets/src/lib/i18n.js';
import { mentionAttributes, textForm, threadElement } from '../../assets/src/lib/view.js';

const CONTROLLER = 'phil--mentions-bundle--mention';
const URL_VALUE = 'data-' + CONTROLLER + '-url-value';

/** Just what the panel's elements ask of a document. */
class Element {
    constructor(name) {
        this.name = name;
        this.attributes = new Map();
        this.children = [];
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    append(child) {
        this.children.push(child);
    }

    *walk() {
        yield this;

        for (const child of this.children) {
            if (child instanceof Element) {
                yield* child.walk();
            }
        }
    }

    areas() {
        return [...this.walk()].filter((element) => 'textarea' === element.name);
    }
}

const document = {
    createElement: (name) => new Element(name),
    createTextNode: (text) => text,
};

const t = translator('en');

const thread = {
    id: 't1',
    status: 'open',
    detached: false,
    suggestion: null,
    resolvedBy: null,
    anchor: { quote: 'words' },
    may: { reply: true, resolve: false, decide: false },
    comments: [
        {
            id: 'c1',
            body: 'A comment',
            author: { name: 'Ada' },
            createdAt: '2026-11-09T10:00:00Z',
            editedAt: null,
            may: true,
        },
    ],
};

test('a text area takes the mention controller and its search when the address is known', () => {
    const [area] = textForm(document, t, 'open', '', '', null, '/members').areas();

    assert.equal(area.attributes.get('data-controller'), CONTROLLER);
    assert.equal(area.attributes.get(URL_VALUE), '/members');
    assert.equal(area.attributes.get('data-' + CONTROLLER + '-empty-value'), 'No member found');
    assert.equal(area.attributes.get('data-' + CONTROLLER + '-found-value'), '{count} members found');
    assert.equal(area.attributes.get('name'), 'body');
});

test('without the mentions bundle a text area stays plain', () => {
    for (const url of [null, undefined, '', 42]) {
        assert.deepEqual(mentionAttributes(t, url), {});
    }

    assert.equal(textForm(document, t, 'open', '').areas()[0].attributes.has('data-controller'), false);
});

test('the words of the empty list follow the language', () => {
    assert.equal(
        mentionAttributes(translator('fr'), '/members')['data-' + CONTROLLER + '-empty-value'],
        'Aucun membre trouvé',
    );
    assert.equal(
        mentionAttributes(translator('fr'), '/members')['data-' + CONTROLLER + '-found-value'],
        '{count} membres trouvés',
    );
});

test('the reply and the edit of a thread take the controller too', () => {
    const [edit, reply] = threadElement(document, t, thread, { editing: 'c1', mentions: '/members' }).areas();

    assert.equal(edit.attributes.get(URL_VALUE), '/members');
    assert.equal(edit.children[0], 'A comment');
    assert.equal(reply.attributes.get(URL_VALUE), '/members');
});

test('a thread without the address has plain text areas', () => {
    for (const area of threadElement(document, t, thread, { editing: 'c1' }).areas()) {
        assert.equal(area.attributes.has('data-controller'), false);
    }
});

/** The texts under an element, in order. */
function texts(element) {
    return [...element.walk()].flatMap((each) => each.children.filter((child) => 'string' === typeof child));
}

function classOf(element) {
    return element.attributes.get('class') ?? '';
}

test('a thread on the whole page shows no passage and is never orphaned', () => {
    const page = { ...thread, anchor: null, detached: false, suggestion: { text: 'x', state: 'pending' } };
    const element = threadElement(document, t, page, { located: false });

    assert.match(classOf(element), /\bis-page\b/);
    assert.doesNotMatch(classOf(element), /\bis-detached\b/);
    assert.equal(
        [...element.walk()].some((each) => 'blockquote' === each.name || 'del' === each.name),
        false,
    );
    assert.equal(texts(element).includes(t('detached')), false);
});

test('a passage no longer found is said orphaned on the screen', () => {
    for (const [options, value] of [
        [{ located: false }, thread],
        [{ located: true }, { ...thread, detached: true }],
    ]) {
        const element = threadElement(document, t, value, options);
        const state = [...element.walk()].find((each) => /phil-comments__state--orphaned/.test(classOf(each)));

        assert.match(classOf(element), /\bis-detached\b/);
        assert.deepEqual(state?.children, ['Orphaned: the quoted passage is no longer in the text']);
    }

    assert.equal(
        [...threadElement(document, t, thread).walk()].some((each) => /--orphaned/.test(classOf(each))),
        false,
    );
    assert.equal(translator('fr')('detached'), "Orphelin : le passage cité n'est plus dans le texte");
});

test('the form of the page stays in place, named for what it does', () => {
    const form = textForm(document, t, 'page', '', '', null, '/members');
    const [area] = form.areas();

    assert.equal(form.attributes.get('data-comments-form'), 'page');
    assert.equal(area.attributes.get('aria-label'), 'Comment on the page');
    assert.equal(area.attributes.get(URL_VALUE), '/members');
    assert.equal(
        [...form.walk()].some((each) => 'cancel' === each.attributes.get('data-comments-do')),
        false,
    );
    assert.equal(
        [...textForm(document, t, 'open', '').walk()].some((each) => 'cancel' === each.attributes.get('data-comments-do')),
        true,
    );
    assert.equal(textForm(document, t, 'edit', 'c1').areas()[0].attributes.get('aria-label'), 'Edit');
    assert.equal(textForm(document, t, 'open', '').areas()[0].attributes.get('aria-label'), 'Comment');
});

test('without the roles of WAI-ARIA 1.3, nothing of them is written', () => {
    const element = threadElement(document, t, { ...thread, suggestion: { text: 'new words', state: 'pending' } });
    const all = [...element.walk()];

    assert.equal(
        all.some((each) => each.attributes.has('role') || each.attributes.has('aria-details') || each.attributes.has('id')),
        false,
    );
    assert.deepEqual(
        all.find((each) => /phil-comments__suggestion/.test(classOf(each))).children.map((child) => child.name ?? child),
        ['span', ' ', 'del', ' ', 'ins'],
    );
});

test('with the roles of WAI-ARIA 1.3, comments and suggestions say what they are', () => {
    const element = threadElement(
        document,
        t,
        { ...thread, suggestion: { text: 'new words', state: 'pending' } },
        { aria: true },
    );
    const all = [...element.walk()];
    const quote = all.find((each) => 'blockquote' === each.name);
    const list = all.find((each) => 'ol' === each.name);
    const comment = all.find((each) => 'comment' === each.attributes.get('role'));
    const suggested = all.find((each) => 'suggestion' === each.attributes.get('role'));

    assert.equal(quote.attributes.get('aria-details'), 'phil-comments-t1-comments');
    assert.equal(list.attributes.get('id'), 'phil-comments-t1-comments');
    assert.equal(classOf(comment), 'phil-comments__content');
    assert.deepEqual(
        comment.children.map((child) => classOf(child)),
        ['phil-comments__meta', 'phil-comments__body'],
    );
    assert.deepEqual(
        suggested.children.map((child) => child.name),
        ['del', 'ins'],
    );
    assert.equal(suggested.name, 'span');
});

test('a removal keeps its word outside the suggestion, and a page thread names no details', () => {
    const removal = threadElement(document, t, { ...thread, suggestion: { text: '', state: 'pending' } }, { aria: true });
    const paragraph = [...removal.walk()].find((each) => /phil-comments__suggestion/.test(classOf(each)));

    assert.deepEqual(
        paragraph.children.map((child) => child.name ?? child),
        ['span', ' ', 'span', ' ', 'em'],
    );
    assert.deepEqual(
        paragraph.children[2].children.map((child) => child.name),
        ['del'],
    );

    const page = threadElement(document, t, { ...thread, anchor: null }, { aria: true });

    assert.equal(
        [...page.walk()].some((each) => each.attributes.has('id') || each.attributes.has('aria-details')),
        false,
    );
    assert.equal(
        [...page.walk()].filter((each) => 'comment' === each.attributes.get('role')).length,
        1,
    );
});
