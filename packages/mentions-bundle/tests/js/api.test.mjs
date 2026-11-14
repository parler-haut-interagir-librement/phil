import assert from 'node:assert/strict';
import { test } from 'node:test';

import { suggestions } from '../../assets/src/lib/api.js';

function answering(status, body, calls = []) {
    return async (url, init) => {
        calls.push([url, init]);

        return { ok: status < 300, status, json: async () => (body instanceof Error ? Promise.reject(body) : body) };
    };
}

test('the search is a GET with the editor header and the query', async () => {
    const calls = [];
    const items = await suggestions('/_editor/mentions', 'a d', answering(200, {
        items: [
            { reference: 'user:1', label: 'Ada', url: '/m/ada', hint: '@ada' },
            { reference: 'page:2', label: 'A page', url: null, hint: null },
            { reference: 'user:3', label: '', hint: '@nobody' },
        ],
    }, calls));

    assert.deepEqual(items, [{ handle: 'ada', label: 'Ada', url: '/m/ada', reference: 'user:1' }]);
    assert.equal(calls[0][0], '/_editor/mentions?q=a%20d');
    assert.deepEqual(calls[0][1].headers, { 'X-Phil-Block-Editor': '1', Accept: 'application/json' });
    assert.equal(calls[0][1].method, 'GET');
    assert.equal(calls[0][1].credentials, 'same-origin');
});

test('a URL with a query string gets the query after an ampersand', async () => {
    const calls = [];
    await suggestions('/m?scope=team', 'x', answering(200, { items: [] }, calls));

    assert.equal(calls[0][0], '/m?scope=team&q=x');
});

test('no URL, a refusal, a broken body or a failed request give no suggestion', async () => {
    assert.deepEqual(await suggestions('', 'a', answering(200, { items: [] })), []);
    assert.deepEqual(await suggestions(null, 'a', answering(200, { items: [] })), []);
    assert.deepEqual(await suggestions('/m', 'a', answering(403, { items: [{ label: 'x', hint: '@x' }] })), []);
    assert.deepEqual(await suggestions('/m', 'a', answering(200, new Error('not JSON'))), []);
    assert.deepEqual(await suggestions('/m', 'a', answering(200, { items: 'none' })), []);
    assert.deepEqual(await suggestions('/m', 'a', async () => { throw new Error('offline'); }), []);
});
