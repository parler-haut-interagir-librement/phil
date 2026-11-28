import assert from 'node:assert/strict';
import { test } from 'node:test';
import { csrfHeaders, newToken } from '../../assets/src/lib/csrf.js';
import { answer, collect, debounce, HEADER, requestInit } from '../../assets/src/lib/preview.js';

test('collects text fields and merges JSON objects', () => {
    assert.deepEqual(
        collect([
            { name: 'latex', value: 'x^2' },
            { name: '', value: 'ignored' },
            { name: 'config', value: '{"type":"bar","data":{"labels":["a"]}}', kind: 'json' },
        ]),
        { latex: 'x^2', type: 'bar', data: { labels: ['a'] } },
    );
});

test('refuses a JSON field that is not an object', () => {
    for (const value of ['[1]', 'null', '3', '"a"']) {
        assert.throws(() => collect([{ name: 'config', value, kind: 'json' }]), /must be a JSON object/);
    }

    assert.throws(() => collect([{ name: 'config', value: '{', kind: 'json' }]), SyntaxError);
});

test('builds the request of the endpoint', () => {
    const init = requestInit('math', { latex: 'x' }, { token: 'abc' });

    assert.equal(init.method, 'POST');
    assert.equal(init.credentials, 'same-origin');
    assert.equal(init.body, '{"type":"math","data":{"latex":"x"}}');
    assert.deepEqual(init.headers, {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        [HEADER]: '1',
        token: 'abc',
    });
    assert.equal(HEADER, 'X-Phil-Svg-Blocks');
    assert.deepEqual(requestInit('qrCode', {}).headers[HEADER], '1');
});

test('reads the answer', () => {
    assert.deepEqual(answer(200, { markup: '<svg/>' }), { markup: '<svg/>' });
    assert.deepEqual(answer(422, { error: 'Bad formula.' }), { error: 'Bad formula.' });
    assert.deepEqual(answer(200, {}), { error: 'The preview failed (200).' });
    assert.deepEqual(answer(500, null), { error: 'The preview failed (500).' });
    assert.deepEqual(answer(403, { markup: '<svg/>' }), { error: 'The preview failed (403).' });
});

test('waits for a pause before calling', () => {
    const timers = new Map();
    let next = 0;
    const clock = {
        setTimeout: (fn, delay) => {
            timers.set(++next, { fn, delay });

            return next;
        },
        clearTimeout: (id) => timers.delete(id),
    };
    let calls = 0;
    const debounced = debounce(() => calls++, 250, clock);

    debounced();
    debounced();
    assert.equal(timers.size, 1);
    const [{ fn, delay }] = timers.values();
    assert.equal(delay, 250);
    timers.clear();
    fn();
    assert.equal(calls, 1);

    debounced();
    debounced.cancel();
    assert.equal(timers.size, 0);
});

test('sends a new CSRF token in a header and a cookie', () => {
    const random = { getRandomValues: (bytes) => bytes.fill(255) };
    const doc = { cookie: '', location: { protocol: 'https:' } };

    assert.equal(newToken(random), '________________________');
    assert.deepEqual(csrfHeaders('csrf-token', doc, random), { 'csrf-token': '________________________' });
    assert.equal(
        doc.cookie,
        '__Host-csrf-token_________________________=csrf-token; path=/; max-age=60; samesite=strict; secure',
    );

    const plain = { cookie: '', location: { protocol: 'http:' } };
    csrfHeaders('csrf-token', plain, random);
    assert.equal(plain.cookie, 'csrf-token_________________________=csrf-token; path=/; max-age=60; samesite=strict');

    assert.deepEqual(csrfHeaders('', doc, random), {});
    assert.deepEqual(csrfHeaders('a b', doc, random), {});
    assert.deepEqual(csrfHeaders(undefined, doc, random), {});
});
