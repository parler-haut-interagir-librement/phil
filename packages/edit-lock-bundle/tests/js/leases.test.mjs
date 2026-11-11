// The endpoints, called through a fetch that records.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { HEADER, LeaseClient } from '../../assets/src/lib/leases.js';

const URLS = { acquire: '/a', heartbeat: '/h', release: '/r', takeOver: '/t', leave: '/l', presence: '/p', save: '/s', remove: '/x' };

function recorder(answers = []) {
    const calls = [];
    const fetcher = async (url, init) => {
        calls.push([url, init]);
        const answer = answers.shift() ?? { status: 204, text: '' };

        if (answer instanceof Error) {
            throw answer;
        }

        return { ok: answer.status < 300, status: answer.status, text: async () => answer.text };
    };

    return { calls, fetcher };
}

test('each lease request is a POST of the resource and the part', async () => {
    const { calls, fetcher } = recorder([{ status: 200, text: '{"lease":{"part":"b1"}}' }]);
    const client = new LeaseClient({ resource: 'page:1', urls: URLS }, fetcher);

    assert.deepEqual(await client.acquire('b1'), { ok: true, status: 200, body: { lease: { part: 'b1' } } });
    await client.heartbeat('b1');
    await client.release('b1');
    await client.takeOver('b2');
    await client.leave(true);
    await client.save({ id: 'b1' }, 'root', null);
    await client.remove('b1');

    assert.deepEqual(calls.map(([url, init]) => [url, init.method, JSON.parse(init.body), init.keepalive]), [
        ['/a', 'POST', { resource: 'page:1', part: 'b1' }, false],
        ['/h', 'POST', { resource: 'page:1', part: 'b1' }, false],
        ['/r', 'POST', { resource: 'page:1', part: 'b1' }, false],
        ['/t', 'POST', { resource: 'page:1', part: 'b2' }, false],
        ['/l', 'POST', { resource: 'page:1' }, true],
        ['/s', 'POST', { resource: 'page:1', block: { id: 'b1' }, list: 'root', after: null }, false],
        ['/x', 'POST', { resource: 'page:1', id: 'b1' }, false],
    ]);
    assert.deepEqual(calls[0][1].headers, { [HEADER]: '1', 'Content-Type': 'application/json', Accept: 'application/json' });
    assert.equal(calls[0][1].credentials, 'same-origin');
    assert.equal(HEADER, 'X-Phil-Edit-Lock');
});

test('presence is a GET of the resource', async () => {
    const { calls, fetcher } = recorder([{ status: 200, text: '{"leases":[]}' }, { status: 200, text: '{}' }]);

    assert.deepEqual(await new LeaseClient({ resource: 'page 1&2', urls: URLS }, fetcher).presence(), { ok: true, status: 200, body: { leases: [] } });
    await new LeaseClient({ resource: 'p', urls: { ...URLS, presence: '/p?x=1' } }, fetcher).presence();

    assert.deepEqual(calls.map(([url, init]) => [url, init.method, init.headers]), [
        ['/p?resource=page%201%262', 'GET', { [HEADER]: '1', Accept: 'application/json' }],
        ['/p?x=1&resource=p', 'GET', { [HEADER]: '1', Accept: 'application/json' }],
    ]);
});

test('each request that changes something carries a new CSRF token when the server names its cookie, the presence none', async () => {
    const { calls, fetcher } = recorder();
    const cookies = [];
    const doc = { location: { protocol: 'http:' }, set cookie(value) { cookies.push(value); } };
    const client = new LeaseClient({ resource: 'page', urls: URLS, csrfCookie: 'csrf-token' }, fetcher, doc);

    await client.acquire('b1');
    await client.presence();
    await client.release('b1');

    const tokens = calls.map(([, init]) => init.headers['csrf-token']);
    assert.equal(tokens.length, 3);
    assert.equal(tokens[1], undefined);
    assert.deepEqual(calls[1][1].headers, { [HEADER]: '1', Accept: 'application/json' });
    [tokens[0], tokens[2]].forEach((token) => assert.match(token, /^[-A-Za-z0-9_]{24}$/));
    assert.notEqual(tokens[0], tokens[2]);
    assert.deepEqual(cookies, [tokens[0], tokens[2]].map((token) => `csrf-token_${token}=csrf-token; path=/; max-age=60; samesite=strict`));
});

test('without endpoints or network, nothing throws', async () => {
    const none = { ok: false, status: 0, body: null };
    const { calls, fetcher } = recorder([new Error('offline'), { status: 500, text: '<html>' }, { status: 409, text: '{"error":"held"}' }]);
    const client = new LeaseClient({ resource: 'page', urls: URLS }, fetcher);

    assert.deepEqual(await client.acquire('b1'), none);
    assert.deepEqual(await client.acquire('b1'), { ok: false, status: 500, body: null });
    assert.deepEqual(await client.acquire('b1'), { ok: false, status: 409, body: { error: 'held' } });

    for (const config of [{ resource: 'page', urls: null }, { resource: 5, urls: URLS }, { resource: 'page' }]) {
        const off = new LeaseClient(config, fetcher);
        assert.equal(off.enabled, false);
        assert.deepEqual(await off.acquire('b1'), none);
        assert.deepEqual(await off.presence(), none);
    }

    const partial = new LeaseClient({ resource: 'page', urls: { acquire: '/a' } }, fetcher);
    assert.deepEqual(await partial.release('b1'), none);
    assert.deepEqual(await partial.presence(), none);
    assert.equal(calls.length, 3);
});
