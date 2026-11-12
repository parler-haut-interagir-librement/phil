import assert from 'node:assert/strict';
import { test } from 'node:test';

import { DraftClient, HEADER, parseServerDraft } from '../../assets/src/lib/server.js';

const random = { getRandomValues: (bytes) => bytes.fill(0) };
const TOKEN = 'AAAAAAAAAAAAAAAAAAAAAAAA';

function server(...answers) {
    const calls = [];
    const doc = { location: { protocol: 'http:' }, cookie: '' };
    const fetcher = async (url, init) => {
        calls.push([url, init]);
        const answer = answers.shift();

        if (answer instanceof Error) {
            throw answer;
        }

        return { status: answer[0], json: async () => (answer[1] instanceof Error ? Promise.reject(answer[1]) : answer[1]) };
    };

    return { calls, doc, client: new DraftClient('/_autosave/drafts/k', 'cookie_name', fetcher, doc, random) };
}

test('a draft of the server is read back only when it is one', () => {
    assert.deepEqual(parseServerDraft({ revision: 2, version: 'v', fields: { a: [] }, savedAt: 'x' }), { revision: 2, version: 'v', fields: { a: [] } });

    for (const body of [null, 'x', { revision: 0, version: 'v', fields: {} }, { revision: 1.5, version: 'v', fields: {} }, { revision: 1, version: 3, fields: {} }, { revision: 1, version: 'v', fields: { a: 'b' } }]) {
        assert.equal(parseServerDraft(body), null, JSON.stringify(body));
    }
});

test('reading sends the header without a token, and answers the draft or null', async () => {
    const { calls, client } = server([200, { revision: 1, version: 'v', fields: {} }], [204, null], [200, { nope: true }], new Error('offline'), [200, new Error('bad json')]);

    assert.deepEqual(await client.read(), { revision: 1, version: 'v', fields: {} });
    assert.deepEqual(calls[0], [
        '/_autosave/drafts/k',
        { method: 'GET', headers: { [HEADER]: '1', Accept: 'application/json' }, body: undefined, credentials: 'same-origin', cache: 'no-store', keepalive: false },
    ]);
    assert.equal(await client.read(), null);
    assert.equal(await client.read(), null);
    assert.equal(await client.read(), null);
    assert.equal(await client.read(), null);
});

test('saving sends the revision read with a CSRF token, and tells what the server answered', async () => {
    const { calls, doc, client } = server(
        [200, { revision: 4 }],
        [409, { revision: 7 }],
        [409, { error: 'gone' }],
        [413, null],
        [500, null],
        [200, { nope: true }],
        new Error('offline'),
    );

    assert.deepEqual(await client.save(3, 'v', { a: ['1'] }), { status: 'saved', revision: 4 });
    assert.deepEqual(calls[0][1], {
        method: 'PUT',
        headers: { [HEADER]: '1', Accept: 'application/json', cookie_name: TOKEN, 'Content-Type': 'application/json' },
        body: '{"revision":3,"version":"v","fields":{"a":["1"]}}',
        credentials: 'same-origin',
        cache: 'no-store',
        keepalive: false,
    });
    assert.equal(doc.cookie, `cookie_name_${TOKEN}=cookie_name; path=/; max-age=60; samesite=strict`);
    assert.deepEqual(await client.save(3, 'v', {}), { status: 'conflict', revision: 7 });
    assert.deepEqual(await client.save(3, 'v', {}), { status: 'conflict', revision: null });
    assert.deepEqual(await client.save(3, 'v', {}), { status: 'tooLarge' });
    assert.deepEqual(await client.save(3, 'v', {}), { status: 'failed' });
    assert.deepEqual(await client.save(3, 'v', {}), { status: 'failed' });
    assert.deepEqual(await client.save(3, 'v', {}), { status: 'failed' });
});

test('discarding keeps the request alive past the page, and tells whether the server did', async () => {
    const { calls, client } = server([204, null], [403, null], new Error('offline'));

    assert.equal(await client.discard(), true);
    assert.deepEqual(calls[0][1], {
        method: 'DELETE',
        headers: { [HEADER]: '1', Accept: 'application/json', cookie_name: TOKEN },
        body: undefined,
        credentials: 'same-origin',
        cache: 'no-store',
        keepalive: true,
    });
    assert.equal(await client.discard(), false);
    assert.equal(await client.discard(), false);
});
