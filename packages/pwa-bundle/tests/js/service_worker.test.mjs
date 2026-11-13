// The service worker under Node's test runner: the script runs in a context of its own, its worker scope made of plain
// objects — a cache storage in memory, a network the test answers — and each event handler called as a browser would.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const code = readFileSync(new URL('../../assets/src/service_worker.js', import.meta.url), 'utf8');

const settings = {
    version: 'v2',
    cache: 'phil-pwa',
    scope: '/',
    precache: ['/offline', '/assets/app-1234.css'],
    offline: '/offline',
    rules: [
        { prefix: '/assets/', strategy: 'cache-first' },
        { prefix: '/api/', strategy: 'network-first' },
        { prefix: '/api/live/', strategy: 'cache-first' },
    ],
    exclude: ['/_profiler/'],
    script: '/assets/@phil/pwa-bundle/service_worker-abcd.js',
};

function response(body, { status = 200, type = 'basic', cacheControl = null } = {}) {
    const headers = cacheControl === null ? {} : { 'Cache-Control': cacheControl };
    const answer = new Response(body, { status, headers });
    Object.defineProperty(answer, 'type', { value: type });

    return answer;
}

class MemoryCache {
    entries = new Map();

    key(request) {
        return typeof request === 'string' ? request : request.url;
    }

    async match(request) {
        return this.entries.get(this.key(request))?.clone();
    }

    async put(request, answer) {
        this.entries.set(this.key(request), answer);
    }

    async addAll(requests) {
        this.added = requests;

        for (const request of requests) {
            this.entries.set(request.url, response(`precached ${request.url}`));
        }
    }
}

function worker({ scope = 'https://example.org/', network = async () => response('network') } = {}) {
    const caches = new Map();
    const listeners = {};
    const fetched = [];
    const self = {
        registration: { scope },
        caches: {
            open: async (name) => {
                if (!caches.has(name)) {
                    caches.set(name, new MemoryCache());
                }

                return caches.get(name);
            },
            keys: async () => [...caches.keys()],
            delete: async (name) => caches.delete(name),
        },
        fetch: async (request) => {
            fetched.push(request.url);

            return network(request);
        },
        clients: { claimed: false, claim: async () => (self.clients.claimed = true) },
        skipped: false,
        skipWaiting: () => (self.skipped = true),
        addEventListener: (type, handler) => (listeners[type] = handler),
    };
    const context = vm.createContext({ URL, Request, Response, Promise, self: undefined });
    vm.runInContext(code, context);
    const handlers = context.philPwaInstall(self, settings);

    return { self, caches, listeners, fetched, handlers };
}

async function fetchEvent(listeners, url, { method = 'GET', mode = 'cors' } = {}) {
    const event = { request: { url, method, mode }, respondWith: (answer) => (event.answer = answer) };
    listeners.fetch(event);

    return event.answer === undefined ? undefined : await event.answer;
}

test('it listens to the four events of a service worker', () => {
    const { listeners, handlers } = worker();

    assert.deepEqual(Object.keys(listeners).sort(), ['activate', 'fetch', 'install', 'message']);
    assert.equal(listeners.fetch, handlers.fetch);
});

test('the script starts by itself only inside a registered worker', () => {
    const started = [];
    const context = vm.createContext({
        URL,
        Request,
        Response,
        self: {
            philPwa: settings,
            registration: { scope: 'https://example.org/' },
            addEventListener: (type) => started.push(type),
        },
    });
    vm.runInContext(code, context);
    assert.equal(started.length, 4);

    const bare = vm.createContext({
        URL,
        Request,
        Response,
        self: { philPwa: settings, addEventListener: () => assert.fail('no registration') },
    });
    vm.runInContext(code, bare);
    const none = vm.createContext({ URL, Request, Response });
    vm.runInContext(code, none);
});

test('installing caches the precache list in the cache of the version, past the HTTP cache', async () => {
    const { caches, listeners } = worker();
    const event = { waitUntil: (promise) => (event.promise = promise) };

    listeners.install(event);
    await event.promise;

    const cache = caches.get('phil-pwa-v2');
    assert.deepEqual(
        cache.added.map((request) => [request.url, request.cache]),
        [
            ['https://example.org/offline', 'reload'],
            ['https://example.org/assets/app-1234.css', 'reload'],
        ],
    );
});

test('activating deletes the caches of the other versions of the prefix only, then takes over the pages', async () => {
    const { self, caches, listeners } = worker();

    for (const name of ['phil-pwa-v1', 'phil-pwa-v2', 'phil-pwa-old', 'other-v1', 'phil-pwa']) {
        await self.caches.open(name);
    }

    const event = { waitUntil: (promise) => (event.promise = promise) };
    listeners.activate(event);
    await event.promise;

    assert.deepEqual([...caches.keys()].sort(), ['other-v1', 'phil-pwa', 'phil-pwa-v2']);
    assert.equal(self.clients.claimed, true);
});

test('a waiting worker takes over when the page asks it', () => {
    const { self, listeners } = worker();

    listeners.message({ data: { type: 'other' } });
    listeners.message({ data: null });
    listeners.message({});
    assert.equal(self.skipped, false);

    listeners.message({ data: { type: 'skip-waiting' } });
    assert.equal(self.skipped, true);
});

test('requests it does not handle go to the browser untouched', async () => {
    const { listeners, fetched } = worker({ scope: 'https://example.org/app/' });

    assert.equal(
        await fetchEvent(listeners, 'https://example.org/app/form', { method: 'POST', mode: 'navigate' }),
        undefined,
    );
    assert.equal(await fetchEvent(listeners, 'https://cdn.example.net/app/x.js'), undefined);
    assert.equal(await fetchEvent(listeners, 'https://example.org/other/', { mode: 'navigate' }), undefined);
    assert.equal(await fetchEvent(listeners, 'https://example.org/_profiler/abc', { mode: 'navigate' }), undefined);
    assert.equal(await fetchEvent(listeners, 'https://example.org/app/image.png'), undefined);
    assert.deepEqual(fetched, []);
});

test('a navigation is network-first and keeps a public page for later', async () => {
    const { caches, listeners } = worker();

    const answer = await fetchEvent(listeners, 'https://example.org/news', { mode: 'navigate' });

    assert.equal(await answer.text(), 'network');
    assert.equal(await (await caches.get('phil-pwa-v2').match('https://example.org/news')).text(), 'network');
});

test('a page meant for one person, an error or a page of another origin is not kept', async () => {
    for (const [answer, why] of [
        [response('mine', { cacheControl: 'private, max-age=0' }), 'private'],
        [response('mine', { cacheControl: 'no-cache, NO-STORE' }), 'no-store'],
        [response('missing', { status: 404 }), 'not ok'],
        [response('opaque', { type: 'opaque' }), 'not basic'],
    ]) {
        const { caches, listeners } = worker({ network: async () => answer });
        await fetchEvent(listeners, 'https://example.org/account', { mode: 'navigate' });

        assert.equal(caches.get('phil-pwa-v2'), undefined, why);
    }

    const { caches, listeners } = worker({
        network: async () => response('kept', { cacheControl: 'public, max-age=60' }),
    });
    await fetchEvent(listeners, 'https://example.org/public', { mode: 'navigate' });
    assert.equal(await (await caches.get('phil-pwa-v2').match('https://example.org/public')).text(), 'kept');

    const { caches: privateish, listeners: other } = worker({
        network: async () => response('kept', { cacheControl: 'privateish-extension' }),
    });
    await fetchEvent(other, 'https://example.org/odd', { mode: 'navigate' });
    assert.equal(await (await privateish.get('phil-pwa-v2').match('https://example.org/odd')).text(), 'kept');
});

test('offline, a navigation falls back on the cached page, then on the offline page', async () => {
    let online = true;
    const { listeners } = worker({
        network: async () => {
            if (!online) {
                throw new TypeError('Failed to fetch');
            }

            return response('fresh');
        },
    });
    await fetchEvent(listeners, 'https://example.org/news', { mode: 'navigate' });
    online = false;

    assert.equal(await (await fetchEvent(listeners, 'https://example.org/news', { mode: 'navigate' })).text(), 'fresh');

    const install = { waitUntil: (promise) => (install.promise = promise) };
    listeners.install(install);
    await install.promise;
    assert.equal(
        await (await fetchEvent(listeners, 'https://example.org/never-seen', { mode: 'navigate' })).text(),
        'precached https://example.org/offline',
    );
});

test('offline, without the offline page cached, a navigation is a network error', async () => {
    const { listeners } = worker({
        network: async () => {
            throw new TypeError('Failed to fetch');
        },
    });

    const answer = await fetchEvent(listeners, 'https://example.org/never-seen', { mode: 'navigate' });
    assert.equal(answer.type, 'error');
});

test('offline, a network-first request without a cached answer fails as the network did', async () => {
    const failure = new TypeError('Failed to fetch');
    const { listeners } = worker({
        network: async () => {
            throw failure;
        },
    });

    await assert.rejects(fetchEvent(listeners, 'https://example.org/api/items'), (error) => error === failure);
});

test('a network-first request is answered from the cache when the network fails', async () => {
    let online = true;
    const { listeners, fetched } = worker({
        network: async () => {
            if (!online) {
                throw new TypeError('Failed to fetch');
            }

            return response('items');
        },
    });

    assert.equal(await (await fetchEvent(listeners, 'https://example.org/api/items')).text(), 'items');
    online = false;
    assert.equal(await (await fetchEvent(listeners, 'https://example.org/api/items')).text(), 'items');
    assert.deepEqual(fetched, ['https://example.org/api/items', 'https://example.org/api/items']);
});

test('a cache-first request goes to the network once, then is answered from the cache', async () => {
    const { listeners, fetched } = worker({ network: async () => response('styles') });

    assert.equal(await (await fetchEvent(listeners, 'https://example.org/assets/app-1234.css')).text(), 'styles');
    assert.equal(await (await fetchEvent(listeners, 'https://example.org/assets/app-1234.css')).text(), 'styles');
    // The first rule that matches decides: /api/live/ is under /api/, network-first.
    await fetchEvent(listeners, 'https://example.org/api/live/feed');
    await fetchEvent(listeners, 'https://example.org/api/live/feed');
    assert.deepEqual(fetched, [
        'https://example.org/assets/app-1234.css',
        'https://example.org/api/live/feed',
        'https://example.org/api/live/feed',
    ]);
});
