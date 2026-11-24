// The email field's controller and what it says of an address, under Node's test runner, Stimulus resolved to a stub
// by `register.mjs`: `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { distance, hint, isRole, suggest } from '../../assets/src/email_address.js';
import EmailAddressController, { load } from '../../assets/src/email_address_controller.js';

const lists = {
    domains: ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'videotron.ca', 'protonmail.com'],
    slips: { con: 'com', nte: 'net' },
    roles: ['admin', 'info', 'support'],
};

test('the distance counts insertions, deletions, substitutions and swaps of neighbours', () => {
    assert.equal(distance('gmail.com', 'gmail.com'), 0);
    assert.equal(distance('gmial.com', 'gmail.com'), 1);
    assert.equal(distance('gmai.com', 'gmail.com'), 1);
    assert.equal(distance('gmaill.com', 'gmail.com'), 1);
    assert.equal(distance('gmeil.com', 'gmail.com'), 1);
    assert.equal(distance('ab', 'ba'), 1);
    assert.equal(distance('abc', 'cab'), 2);
    assert.equal(distance('', 'abc'), 3);
    assert.equal(distance('abc', ''), 3);
});

test('a slip of a known domain is suggested, the part before the @ kept as typed', () => {
    assert.equal(suggest('Ada.L@gmial.com', lists), 'Ada.L@gmail.com');
    assert.equal(suggest('  ada@GMIAL.com ', lists), 'ada@gmail.com');
    assert.equal(suggest('ada@hotmial.com', lists), 'ada@hotmail.com');
    assert.equal(suggest('ada@videotorn.ca', lists), 'ada@videotron.ca');
});

test('two slips are allowed from a domain of ten characters or more, one below', () => {
    assert.equal(suggest('ada@hotmial.cm', lists), 'ada@hotmail.com');
    assert.equal(suggest('ada@protonmial.cm', lists), 'ada@protonmail.com');
    assert.equal(suggest('ada@gmial.cm', lists), null);
    assert.equal(suggest('ada@yhaoo.com', lists), 'ada@yahoo.com');
    assert.equal(suggest('ada@yhaoo.cm', lists), null);
});

test('a domain of fewer than eight characters is never suggested', () => {
    assert.equal(suggest('ada@aol.co', lists), null);
    assert.equal(suggest('ada@aon.com', lists), null);
});

test('the closest domain wins, the first in the list on a tie', () => {
    assert.equal(suggest('ada@gmail.co', { domains: ['gmail.com', 'gmail.co.uk'], slips: {} }), 'ada@gmail.com');
    assert.equal(suggest('ada@outlok.com', { domains: ['outlook.com', 'outlock.com'], slips: {} }), 'ada@outlook.com');
    assert.equal(suggest('ada@outlok.com', { domains: ['outlock.com', 'outlook.com'], slips: {} }), 'ada@outlock.com');
    assert.equal(suggest('ada@hotmial.cm', { domains: ['hotmail.co', 'hotmail.com'], slips: {} }), 'ada@hotmail.co');
});

test('a top-level slip is replaced when no known domain is close', () => {
    assert.equal(suggest('ada@example.con', lists), 'ada@example.com');
    assert.equal(suggest('ada@example.nte', lists), 'ada@example.net');
    assert.equal(suggest('ada@example.org', lists), null);
    assert.equal(suggest('ada@localhost', lists), null);
    assert.equal(suggest('ada@example.constructor', lists), null);
    assert.equal(suggest('ada@example.toString', lists), null);
});

test('no suggestion for a known domain, a domain of other characters, or a text without its parts', () => {
    assert.equal(suggest('ada@gmail.com', lists), null);
    assert.equal(suggest('ada@GMAIL.COM', lists), null);
    assert.equal(suggest('ada@gmial_com', lists), null);
    assert.equal(suggest('ada@gmiäl.com', lists), null);
    assert.equal(suggest('@gmial.com', lists), null);
    assert.equal(suggest('gmial.com', lists), null);
    assert.equal(suggest(`ada@${'a'.repeat(254)}`, lists), null);
});

test('a role address is told, its +tag ignored, its case too', () => {
    assert.equal(isRole('info@example.com', lists.roles), true);
    assert.equal(isRole(' Support+billing@example.com', lists.roles), true);
    assert.equal(isRole('ADMIN@example.com', lists.roles), true);
    assert.equal(isRole('ada@example.com', lists.roles), false);
    assert.equal(isRole('information@example.com', lists.roles), false);
    assert.equal(isRole('@example.com', lists.roles), false);
    assert.equal(isRole('info', lists.roles), false);
});

test('a hint is a suggestion first, else a role warning, else nothing', () => {
    assert.deepEqual(hint('info@gmial.com', lists), { kind: 'suggestion', address: 'info@gmail.com' });
    assert.deepEqual(hint('info@example.com', lists), { kind: 'role' });
    assert.equal(hint('ada@example.com', lists), null);
    assert.equal(hint('info@example.com', { domains: [], slips: {} }), null);
});

class Node {
    constructor(tagName, document) {
        Object.assign(this, { tagName, ownerDocument: document, children: [], attributes: {}, listeners: {}, className: '', id: '', textContent: '', type: '' });
    }

    setAttribute(name, value) {
        this.attributes[name] = value;
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    append(...children) {
        this.children.push(...children);
    }

    replaceChildren(...children) {
        this.children = children;
    }

    addEventListener(name, listener) {
        this.listeners[name] = listener;
    }

    after(node) {
        this.next = node;
    }

    remove() {
        this.removed = true;
    }

    focus() {
        this.focused = true;
    }
}

const documentStub = { createElement: (tag) => new Node(tag.toUpperCase(), documentStub) };

function field(value, id = 'subscriber_email') {
    const input = new Node('INPUT', documentStub);
    Object.assign(input, { value, id });

    return input;
}

function controller(input, url = `/domains-${Math.random()}.json`) {
    const instance = new EmailAddressController();
    Object.assign(instance, {
        element: input,
        urlValue: url,
        suggestionValue: 'Did you mean %address%?',
        useValue: 'Use it',
        roleValue: 'A shared mailbox.',
    });
    instance.connect();

    return instance;
}

function answering(body, status = 200) {
    const calls = [];
    globalThis.fetch = async (url, options) => {
        calls.push([url, options]);

        return { ok: status === 200, status, json: async () => body };
    };

    return calls;
}

test('the live region is made after the field, announced politely and named by the field', () => {
    const input = field('ada@example.com');
    input.setAttribute('aria-describedby', 'subscriber_email_help');
    const instance = controller(input);

    assert.equal(input.next, instance.region);
    assert.equal(instance.region.tagName, 'P');
    assert.equal(instance.region.className, 'phil-email-address-hint');
    assert.equal(instance.region.getAttribute('role'), 'status');
    assert.equal(instance.region.getAttribute('aria-live'), 'polite');
    assert.equal(instance.region.id, 'subscriber_email-hint');
    assert.equal(input.getAttribute('aria-describedby'), 'subscriber_email_help subscriber_email-hint');

    const bare = controller(field('ada@example.com', ''));
    assert.equal(bare.region.id, 'email-hint');
    assert.equal(bare.element.getAttribute('aria-describedby'), 'email-hint');

    instance.disconnect();
    assert.equal(instance.region, null);
    assert.equal(input.next.removed, true);
    instance.show({ kind: 'role' });
});

test('a suggestion is written with a button that puts it in the field', async () => {
    const calls = answering(lists);
    const input = field('ada@gmial.com');
    const instance = controller(input, '/email-address/domains.json');

    await instance.check();

    assert.deepEqual(calls, [['/email-address/domains.json', { headers: { Accept: 'application/json' } }]]);
    const [text, space, button] = instance.region.children;
    assert.equal(text, 'Did you mean ada@gmail.com?');
    assert.equal(space, ' ');
    assert.equal(button.tagName, 'BUTTON');
    assert.equal(button.type, 'button');
    assert.equal(button.textContent, 'Use it');
    assert.deepEqual(instance.dispatched, [['hint', { kind: 'suggestion', address: 'ada@gmail.com' }]]);

    button.listeners.click();

    assert.equal(input.value, 'ada@gmail.com');
    assert.deepEqual(instance.region.children, []);
    assert.equal(input.focused, true);
    assert.deepEqual(instance.dispatched.at(-1), ['applied', 'ada@gmail.com']);
});

test('a role address is warned about, and a right address clears what was written', async () => {
    answering(lists);
    const input = field('info@example.com');
    const instance = controller(input);

    await instance.check();
    assert.deepEqual(instance.region.children, ['A shared mailbox.']);

    input.value = 'ada@example.com';
    await instance.check();
    assert.deepEqual(instance.region.children, []);
    assert.deepEqual(instance.dispatched.at(-1), ['hint', null]);
});

test('an empty field reads no list and writes nothing', async () => {
    const calls = answering(lists);
    const instance = controller(field('   '));

    await instance.check();

    assert.deepEqual(calls, []);
    assert.deepEqual(instance.dispatched, [['hint', null]]);
});

test('the lists are read once per URL, whatever the fields', async () => {
    const calls = answering(lists);
    const url = '/shared.json';

    await controller(field('ada@gmial.com'), url).check();
    await controller(field('ada@gmial.com', 'other'), url).check();

    assert.equal(calls.length, 1);
    assert.equal(await load(url), lists);
});

test('an answer for a value the person changed since is not written', async () => {
    answering(lists);
    const input = field('ada@gmial.com');
    const instance = controller(input);
    const checking = instance.check();
    input.value = 'ada@gmail.com';
    await checking;

    assert.deepEqual(instance.region.children, []);
    assert.deepEqual(instance.dispatched, []);
});

test('lists that cannot be read dispatch failed, and are asked again next time', async () => {
    const calls = answering({}, 500);
    const instance = controller(field('ada@gmial.com'), '/broken.json');

    await instance.check();

    assert.equal(instance.dispatched[0][0], 'failed');
    assert.equal(instance.dispatched[0][1].message, 'The email lists answered 500.');
    assert.deepEqual(instance.region.children, []);

    answering(lists);
    await instance.check();
    assert.equal(calls.length, 1);
    assert.deepEqual(instance.dispatched.at(-1), ['hint', { kind: 'suggestion', address: 'ada@gmail.com' }]);
});
