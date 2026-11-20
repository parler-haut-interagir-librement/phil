// The offline queue, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { IndexedDbStore, OfflineQueue, newSaleId } from '../../assets/src/offline_queue.js';
import OfflineQueueController from '../../assets/src/offline_queue_controller.js';
import { FailingIndexedDb, FakeIndexedDb } from './fake-indexeddb.mjs';

const store = (indexedDb = new FakeIndexedDb(), name = 'q') => new IndexedDbStore(name, indexedDb);

const answering = (statuses, seen = []) => async (sale) => {
    seen.push(sale.id);
    const status = statuses.shift();

    if (status === undefined) {
        throw new TypeError('Failed to fetch');
    }

    return { status };
};

const queueOf = async (send, ids, where = store()) => {
    const queue = new OfflineQueue(where, send);

    for (const id of ids) {
        await queue.enqueue({ id });
    }

    return queue;
};

test('a sale id is a random UUID', () => {
    assert.equal(newSaleId({ randomUUID: () => 'id-1' }), 'id-1');
    assert.match(newSaleId(), /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
});

test('the store keeps its sales in an IndexedDB database of version 1, opened once', async () => {
    const indexedDb = new FakeIndexedDb();
    const kept = store(indexedDb, 'sales');
    await kept.add({ id: 'a', total: 1 });
    await kept.add({ id: 'b' });
    await kept.add({ id: 'a', total: 2 });

    assert.deepEqual(await kept.all(), [{ id: 'a', total: 1 }, { id: 'b' }]);
    assert.deepEqual(indexedDb.opened, [['sales', 1]]);
    assert.deepEqual(await store(indexedDb, 'sales').all(), [{ id: 'a', total: 1 }, { id: 'b' }]);
    assert.deepEqual(await store(indexedDb, 'other').all(), []);

    await kept.remove('a');
    await kept.remove('missing');
    assert.deepEqual(await kept.all(), [{ id: 'b' }]);

    await kept.reject({ id: 'c' }, 422);
    assert.deepEqual(await kept.rejected(), [{ sale: { id: 'c' }, status: 422 }]);
});

test('a database that cannot open fails the store', async () => {
    await assert.rejects(store(new FailingIndexedDb()).all(), { name: 'UnknownError' });
});

test('a sale without a text id is refused', async () => {
    const queue = new OfflineQueue(store(), answering([]));

    for (const sale of [null, 'a', {}, { id: 1 }, { id: '' }]) {
        await assert.rejects(queue.enqueue(sale), TypeError);
    }

    assert.deepEqual(await queue.pending(), []);
});

test('sales are sent in the order they were rung up, and removed once taken', async () => {
    const seen = [];
    const queue = await queueOf(answering([201, 200], seen), ['a', 'b']);

    assert.deepEqual(await queue.flush(), { sent: 2, rejected: 0, left: 0 });
    assert.deepEqual(seen, ['a', 'b']);
    assert.deepEqual(await queue.pending(), []);
});

test('no answer stops the flush, and the sales wait', async () => {
    const seen = [];
    const queue = await queueOf(answering([201], seen), ['a', 'b', 'c']);

    assert.deepEqual(await queue.flush(), { sent: 1, rejected: 0, left: 2 });
    assert.deepEqual(await queue.pending(), [{ id: 'b' }, { id: 'c' }]);
    assert.deepEqual(seen, ['a', 'b']);
});

test('a server error stops the flush too', async () => {
    const queue = await queueOf(answering([500, 201]), ['a', 'b']);

    assert.deepEqual(await queue.flush(), { sent: 0, rejected: 0, left: 2 });
    assert.deepEqual(await queue.flush(), { sent: 1, rejected: 0, left: 1 });
});

test('a refused sale is set aside with its status, and the others go on', async () => {
    const queue = await queueOf(answering([409, 422, 299, 403]), ['a', 'b', 'c', 'd']);

    assert.deepEqual(await queue.flush(), { sent: 1, rejected: 3, left: 0 });
    assert.deepEqual(await queue.rejected(), [
        { sale: { id: 'a' }, status: 409 },
        { sale: { id: 'b' }, status: 422 },
        { sale: { id: 'd' }, status: 403 },
    ]);
});

test('a status of 499 is a refusal, 500 is not', async () => {
    const queue = await queueOf(answering([499, 500]), ['a', 'b']);

    assert.deepEqual(await queue.flush(), { sent: 0, rejected: 1, left: 1 });
});

test('no answer, a redirect, a sign-in or a busy server keeps the sale waiting', async () => {
    for (const status of [0, 199, 302, 399, 401, 408, 429]) {
        const queue = await queueOf(answering([status, 201]), ['a', 'b']);

        assert.deepEqual(await queue.flush(), { sent: 0, rejected: 0, left: 2 }, String(status));
        assert.deepEqual(await queue.rejected(), [], String(status));
    }

    const queue = await queueOf(answering([400, 200]), ['a', 'b']);
    assert.deepEqual(await queue.flush(), { sent: 1, rejected: 1, left: 0 });
});

test('two tabs flushing one database never drop a sale unsent', async () => {
    const indexedDb = new FakeIndexedDb();
    const seen = [];
    let release;
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    const slow = new OfflineQueue(store(indexedDb), async (sale) => {
        seen.push(['slow', sale.id]);
        await gate;

        return { status: 201 };
    });
    const fast = new OfflineQueue(store(indexedDb), async (sale) => {
        seen.push(['fast', sale.id]);

        return { status: 201 };
    });
    await slow.enqueue({ id: 'a' });
    await slow.enqueue({ id: 'b' });

    const slowFlush = slow.flush();
    await fast.flush();
    await fast.enqueue({ id: 'c' });
    release();
    await slowFlush;
    await fast.flush();

    assert.deepEqual(seen.filter(([, id]) => id === 'c'), [['fast', 'c']]);
    assert.deepEqual(await fast.pending(), []);
});

test('two flushes at once send each sale once', async () => {
    const seen = [];
    const queue = await queueOf(answering([201, 201], seen), ['a']);

    const [first, second] = await Promise.all([queue.flush(), queue.flush()]);

    assert.deepEqual(first, second);
    assert.deepEqual(seen, ['a']);
    assert.equal(queue.flushing, null);
    await queue.enqueue({ id: 'b' });
    assert.deepEqual(await queue.flush(), { sent: 1, rejected: 0, left: 0 });
});

test('the controller queues a sale, sends it, and shows what waits', async () => {
    const listeners = {};
    const posted = [];
    const dispatched = [];
    const indexedDb = new FakeIndexedDb();
    globalThis.window = {
        indexedDB: indexedDb,
        addEventListener: (type, listener) => {
            listeners[type] = listener;
        },
        removeEventListener: (type, listener) => {
            if (listeners[type] === listener) {
                delete listeners[type];
            }
        },
        fetch: async (url, options) => {
            posted.push([url, options]);

            return { status: 201, json: async () => ({ id: JSON.parse(options.body).id, discrepancy: 0 }) };
        },
    };

    assert.deepEqual(OfflineQueueController.targets, ['count']);
    assert.deepEqual(OfflineQueueController.values, { url: String, database: { type: String, default: 'phil-commerce-pos-offline-sales' } });

    const controller = new OfflineQueueController();
    controller.urlValue = '/register/offline-sales';
    controller.databaseValue = 'k';
    controller.hasCountTarget = true;
    controller.countTarget = { textContent: '' };
    controller.dispatch = (name, options) => dispatched.push([name, options.detail]);

    controller.connect();
    await controller.queue.flush();
    const result = await controller.sell({ detail: { sale: { id: 'a', total: 100 } } });

    assert.deepEqual(result, { sent: 1, rejected: 0, left: 0 });
    assert.equal(controller.countTarget.textContent, '0');
    assert.deepEqual(posted, [['/register/offline-sales', {
        method: 'POST',
        headers: { 'content-type': 'application/json', accept: 'application/json' },
        body: '{"id":"a","total":100}',
        credentials: 'same-origin',
        redirect: 'manual',
    }]]);
    assert.deepEqual(dispatched.at(-1), ['flushed', { sent: 1, rejected: 0, left: 0 }]);
    assert.equal(typeof listeners.online, 'function');

    globalThis.window.fetch = async () => {
        throw new TypeError('offline');
    };
    await controller.sell({ detail: { sale: { id: 'b' } } });
    assert.equal(controller.countTarget.textContent, '1');
    assert.deepEqual(await store(indexedDb, 'k').all(), [{ id: 'b' }]);

    globalThis.window.fetch = async () => ({ status: 200, json: async () => ({ id: 'b' }) });
    await listeners.online();
    assert.equal(controller.countTarget.textContent, '0');

    controller.hasCountTarget = false;
    assert.deepEqual(await controller.flush(), { sent: 0, rejected: 0, left: 0 });

    controller.disconnect();
    assert.equal(listeners.online, undefined);
    delete globalThis.window;
});

test('the controller keeps a sale whose answer is a redirect or does not name it', async () => {
    const answers = [
        { type: 'opaqueredirect', status: 0 },
        { type: 'basic', status: 200, redirected: true, json: async () => ({ id: 'a' }) },
        { type: 'basic', status: 200, json: async () => { throw new SyntaxError('<html>'); } },
        { type: 'basic', status: 201, json: async () => ({ id: 'other' }) },
        { type: 'basic', status: 201, json: async () => null },
        { type: 'basic', status: 201, json: async () => 'a' },
    ];
    globalThis.window = {
        indexedDB: new FakeIndexedDb(),
        addEventListener: () => {},
        removeEventListener: () => {},
        fetch: async () => answers.shift(),
    };
    const controller = new OfflineQueueController();
    controller.urlValue = '/register/offline-sales';
    controller.databaseValue = 'k';
    controller.hasCountTarget = false;
    controller.dispatch = () => {};
    controller.queue = new OfflineQueue(controller.store(), (sale) => controller.post(sale));
    await controller.queue.enqueue({ id: 'a' });

    for (let i = 0; i < 6; i++) {
        assert.deepEqual(await controller.flush(), { sent: 0, rejected: 0, left: 1 }, String(i));
    }

    globalThis.window.fetch = async () => ({ type: 'basic', status: 422, json: async () => ({ error: 'no' }) });
    assert.deepEqual(await controller.flush(), { sent: 0, rejected: 1, left: 0 });
    delete globalThis.window;
});
