// What the controller reads of a Mercure update, and the toast it builds.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { dismissDelay, parseUpdate, safeUrl, toastElement } from '../../assets/src/lib/updates.js';

test('an update carries the unread count and maybe a toast', () => {
    assert.deepEqual(parseUpdate('{"unread": 3}'), { unread: 3, toast: null });
    assert.deepEqual(parseUpdate('{"unread": 0, "toast": {"id": "n1", "title": "Hi"}}'), { unread: 0, toast: { id: 'n1', title: 'Hi' } });
    assert.deepEqual(parseUpdate('{"unread": 1, "toast": {"id": 5, "title": "Hi"}}'), { unread: 1, toast: null });
    assert.deepEqual(parseUpdate('{"unread": 1, "toast": null}'), { unread: 1, toast: null });
    assert.equal(parseUpdate('nope'), null);
    assert.equal(parseUpdate('null'), null);
    assert.equal(parseUpdate('{"unread": -1}'), null);
    assert.equal(parseUpdate('{"unread": 1.5}'), null);
    assert.equal(parseUpdate('"x"'), null);
});

test('a toast links only to this site or an http address', () => {
    assert.equal(safeUrl('/events/7'), true);
    assert.equal(safeUrl('https://example.test/a'), true);
    assert.equal(safeUrl('//evil.test/a'), false);
    assert.equal(safeUrl('javascript:alert(1)'), false);
    assert.equal(safeUrl(null), false);
});

test('motion reduced, a toast stays longer', () => {
    assert.equal(dismissDelay(false), 6000);
    assert.equal(dismissDelay(true), 10000);
});

// A document as small as the toast needs.
function fakeDocument() {
    const element = (tag) => ({
        tag,
        className: '',
        dataset: {},
        attributes: {},
        children: [],
        textContent: '',
        append(child) {
            this.children.push(child);
        },
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    });

    return { createElement: element };
}

test('a toast is built with text, never markup', () => {
    const toast = toastElement(fakeDocument(), { id: 'n1', title: '<b>Hi</b>', body: 'There', url: '/a' }, 'Close');

    assert.equal(toast.className, 'phil-notifications-toast');
    assert.equal(toast.dataset.notificationId, 'n1');
    assert.equal(toast.children[0].children[0].tag, 'a');
    assert.equal(toast.children[0].children[0].href, '/a');
    assert.equal(toast.children[0].children[0].textContent, '<b>Hi</b>');
    assert.equal(toast.children[1].textContent, 'There');
    assert.equal(toast.children[2].attributes['aria-label'], 'Close');
    assert.equal(toast.children[2].type, 'button');

    const plain = toastElement(fakeDocument(), { id: 'n2', title: 'Hi', body: '', url: 'javascript:x' }, 'Close');
    assert.equal(plain.children[0].textContent, 'Hi');
    assert.equal(plain.children[0].children.length, 0);
    assert.equal(plain.children.length, 2);
});
