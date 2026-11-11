import assert from 'node:assert/strict';
import { test } from 'node:test';

import { csrfHeaders, csrfToken, fillForm, newToken } from '../../assets/src/lib/csrf.js';

const counting = { getRandomValues: (bytes) => bytes.forEach((_, i) => { bytes[i] = 250 + i; }) };

function page(protocol = 'http:') {
    const cookies = [];

    return { cookies, location: { protocol }, set cookie(value) { cookies.push(value); } };
}

function form(value) {
    const field = value === undefined ? null : { value, dataset: {} };

    return { field, querySelector: (selector) => ('input[name="_csrf_token"]' === selector ? field : null) };
}

test('a token is 24 URL-safe characters of random bytes', () => {
    assert.equal(newToken(counting), '-vv8_f7_AAECAwQFBgcICQoL');
    assert.match(newToken(), /^[-A-Za-z0-9_]{24}$/);
    assert.notEqual(newToken(), newToken());
});

test('the cookie names the token, and is secure with the host prefix over HTTPS', () => {
    const plain = page();
    assert.equal(csrfToken('csrf-token', plain, counting), '-vv8_f7_AAECAwQFBgcICQoL');
    assert.deepEqual(plain.cookies, ['csrf-token_-vv8_f7_AAECAwQFBgcICQoL=csrf-token; path=/; max-age=60; samesite=strict']);

    const secure = page('https:');
    csrfToken('csrf-token', secure, counting);
    assert.deepEqual(secure.cookies, ['__Host-csrf-token_-vv8_f7_AAECAwQFBgcICQoL=csrf-token; path=/; max-age=60; samesite=strict; secure']);
});

test('no cookie and no header without a name the server gives', () => {
    const doc = page();

    for (const name of [undefined, null, '', 'a b', 'x;y=z', 7]) {
        assert.equal(csrfToken(name, doc, counting), null);
        assert.deepEqual(csrfHeaders(name, doc, counting), {});
    }

    assert.deepEqual(doc.cookies, []);
    assert.deepEqual(csrfHeaders('my_cookie', doc, counting), { my_cookie: '-vv8_f7_AAECAwQFBgcICQoL' });
    assert.equal(doc.cookies.length, 1);
});

test('a form gets a new token in its field at each submission', () => {
    const doc = page();
    const sent = form('csrf-token');

    assert.equal(fillForm(sent, doc, counting), '-vv8_f7_AAECAwQFBgcICQoL');
    assert.equal(sent.field.value, '-vv8_f7_AAECAwQFBgcICQoL');
    assert.equal(sent.field.dataset.csrfCookie, 'csrf-token');
    fillForm(sent, doc);
    assert.match(sent.field.value, /^[-A-Za-z0-9_]{24}$/);
    assert.equal(doc.cookies.length, 2);
    assert.ok(doc.cookies[1].startsWith('csrf-token_' + sent.field.value + '=csrf-token;'));

    assert.equal(fillForm(form(), doc), null);
    const unnamed = form('');
    assert.equal(fillForm(unnamed, doc), null);
    assert.equal(unnamed.field.value, '');
    assert.equal(doc.cookies.length, 2);
});
