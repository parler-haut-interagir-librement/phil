import assert from 'node:assert/strict';
import { test } from 'node:test';

import { CommentsClient } from '../../assets/src/lib/api.js';

const urls = { threads: '/c/threads', open: '/c/threads', reply: '/c/threads/__id__/reply', resolve: '/c/threads/__id__/resolve', edit: '/c/comments/__id__/edit', delete: '/c/comments/__id__/delete' };

function recorder(answer = { ok: true, status: 200, json: async () => ({ done: true }) }) {
    const calls = [];

    return { calls, fetch: async (url, init) => { calls.push([url, init]); if (answer instanceof Error) { throw answer; } return answer; } };
}

test('requests carry the header, JSON bodies and the identifiers', async () => {
    const { calls, fetch } = recorder();
    const client = new CommentsClient({ urls, subject: 'page:1 a' }, fetch);

    assert.deepEqual(await client.threads(), { ok: true, status: 200, data: { done: true } });
    await client.open({ block: 'b' }, 'Hi', 'x');
    await client.reply('t/1', 'Yes');
    await client.act('resolve', 't1');
    await client.edit('c1', 'New');
    await client.remove('c1');

    assert.deepEqual(calls.map(([url]) => url), ['/c/threads?subject=page%3A1%20a', '/c/threads', '/c/threads/t%2F1/reply', '/c/threads/t1/resolve', '/c/comments/c1/edit', '/c/comments/c1/delete']);
    assert.deepEqual(calls[0][1], { credentials: 'same-origin', method: 'GET', headers: { 'X-Phil-Comments': '1', Accept: 'application/json' } });
    assert.deepEqual(calls[1][1], { credentials: 'same-origin', method: 'POST', headers: { 'X-Phil-Comments': '1', Accept: 'application/json', 'Content-Type': 'application/json' }, body: '{"subject":"page:1 a","anchor":{"block":"b"},"body":"Hi","suggestion":"x"}' });
    assert.equal(calls[2][1].body, '{"body":"Yes"}');
    assert.equal(calls[3][1].body, '{}');
    assert.equal(calls[4][1].body, '{"body":"New"}');
    assert.equal(calls[5][1].body, '{}');
});

test('requests that change something carry a new CSRF token when the server names its cookie, a GET none', async () => {
    const { calls, fetch } = recorder();
    const cookies = [];
    const doc = { location: { protocol: 'https:' }, set cookie(value) { cookies.push(value); } };
    const client = new CommentsClient({ urls, subject: 's:1', csrfCookie: 'csrf-token' }, fetch, doc);

    await client.threads();
    await client.reply('t1', 'Yes');
    await client.act('resolve', 't1');

    const [read, first, second] = calls.map(([, init]) => init.headers['csrf-token']);
    assert.equal(read, undefined);
    assert.deepEqual(calls[0][1].headers, { 'X-Phil-Comments': '1', Accept: 'application/json' });
    assert.match(first, /^[-A-Za-z0-9_]{24}$/);
    assert.notEqual(first, second);
    assert.equal(calls[1][1].headers['X-Phil-Comments'], '1');
    assert.equal(calls[1][1].headers['Content-Type'], 'application/json');
    assert.deepEqual(cookies, [`__Host-csrf-token_${first}=csrf-token; path=/; max-age=60; samesite=strict; secure`, `__Host-csrf-token_${second}=csrf-token; path=/; max-age=60; samesite=strict; secure`]);
});

test('a list URL with a query keeps it', async () => {
    const { calls, fetch } = recorder();
    await new CommentsClient({ urls: { threads: '/t?x=1' }, subject: 's:1' }, fetch).threads();
    assert.equal(calls[0][0], '/t?x=1&subject=s%3A1');
});

test('an open thread without suggestion sends null', async () => {
    const { calls, fetch } = recorder();
    await new CommentsClient({ urls }, fetch).open({}, 'Hi');
    assert.equal(calls[0][1].body, '{"subject":"","anchor":{},"body":"Hi","suggestion":null}');
});

test('a comment on the whole page sends no anchor', async () => {
    const { calls, fetch } = recorder();
    await new CommentsClient({ urls, subject: 'page:1' }, fetch).open(null, 'Nice');
    assert.equal(calls[0][1].body, '{"subject":"page:1","anchor":null,"body":"Nice","suggestion":null}');
});

test('without URLs nothing is sent', async () => {
    const { calls, fetch } = recorder();
    const client = new CommentsClient({}, fetch);

    assert.deepEqual(await client.threads(), { ok: false, status: 0, data: null });
    assert.deepEqual(await client.reply('t', 'x'), { ok: false, status: 0, data: null });
    assert.equal(client.url('open'), null);
    assert.equal(calls.length, 0);
});

test('a failed request and a body that is no JSON are answered', async () => {
    assert.deepEqual(await new CommentsClient({ urls }, recorder(new Error('down')).fetch).threads(), { ok: false, status: 0, data: null });
    assert.deepEqual(await new CommentsClient({ urls }, recorder({ ok: false, status: 403, json: async () => { throw new Error('no'); } }).fetch).threads(), { ok: false, status: 403, data: null });
});
