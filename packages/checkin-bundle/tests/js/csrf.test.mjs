import assert from 'node:assert/strict';
import { test } from 'node:test';

import { csrfHeaders, newToken } from '../../assets/src/lib/csrf.js';

const counting = {
    getRandomValues: (bytes) =>
        bytes.forEach((_, i) => {
            bytes[i] = 250 + i;
        }),
};

function page(protocol = 'http:') {
    const cookies = [];

    return {
        cookies,
        location: { protocol },
        set cookie(value) {
            cookies.push(value);
        },
    };
}

test('a token is 24 URL-safe characters of random bytes', () => {
    assert.equal(newToken(counting), '-vv8_f7_AAECAwQFBgcICQoL');
    assert.match(newToken(), /^[-A-Za-z0-9_]{24}$/);
});

test('the header carries the token, and the cookie names it, secure over HTTPS', () => {
    const plain = page();
    assert.deepEqual(csrfHeaders('csrf-token', plain, counting), { 'csrf-token': '-vv8_f7_AAECAwQFBgcICQoL' });
    assert.deepEqual(plain.cookies, [
        'csrf-token_-vv8_f7_AAECAwQFBgcICQoL=csrf-token; path=/; max-age=60; samesite=strict',
    ]);

    const secure = page('https:');
    csrfHeaders('csrf-token', secure, counting);
    assert.deepEqual(secure.cookies, [
        '__Host-csrf-token_-vv8_f7_AAECAwQFBgcICQoL=csrf-token; path=/; max-age=60; samesite=strict; secure',
    ]);
});

test('no header and no cookie without a name the server gives', () => {
    const doc = page();

    for (const name of [undefined, null, '', 'a b', 'x;y=z', 7]) {
        assert.deepEqual(csrfHeaders(name, doc, counting), {});
    }

    assert.deepEqual(doc.cookies, []);
});
