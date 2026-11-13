import assert from 'node:assert/strict';
import { test } from 'node:test';

import { IndexedStore, LocalStore, MemoryStore, migrate, openStore } from '../../assets/src/lib/store.js';
import { fakeIndexedDB, MemoryStorage } from './support/fake-indexeddb.mjs';

async function exercise(store) {
    assert.equal(await store.get('a'), null);
    assert.equal(await store.set('a', { n: 1, list: [1, 2] }), true);
    assert.deepEqual(await store.get('a'), { n: 1, list: [1, 2] });
    assert.equal(await store.update('a', (value) => ({ ...value, n: value.n + 1 })), true);
    assert.deepEqual(await store.get('a'), { n: 2, list: [1, 2] });
    assert.equal(await store.update('b', (value) => [...(value ?? []), 'x']), true);
    assert.deepEqual(await store.get('b'), ['x']);
    await store.remove('a');
    assert.equal(await store.get('a'), null);
}

test('IndexedDB is the store a window offers first', async () => {
    const indexedDB = fakeIndexedDB();
    const store = await openStore({ indexedDB, localStorage: new MemoryStorage() });

    assert.ok(store instanceof IndexedStore);
    assert.equal(store.kind, 'indexeddb');
    assert.deepEqual(indexedDB.opened, [['phil-checkin', 1]]);
    await exercise(store);
});

test('a value read from IndexedDB is a copy', async () => {
    const store = await IndexedStore.open(fakeIndexedDB());
    const value = { n: 1 };
    await store.set('a', value);
    value.n = 2;

    assert.deepEqual(await store.get('a'), { n: 1 });
});

test('IndexedDB opened a second time keeps its object store', async () => {
    const indexedDB = fakeIndexedDB();
    await (await IndexedStore.open(indexedDB)).set('a', 1);

    assert.equal(await (await IndexedStore.open(indexedDB)).get('a'), 1);
});

test('writes IndexedDB refuses, as a full quota does, answer false and throw nothing', async () => {
    const store = await IndexedStore.open(fakeIndexedDB({ refuseWrites: true }));

    assert.equal(await store.set('a', 1), false);
    assert.equal(await store.update('a', () => 1), false);
    await store.remove('a');
    assert.equal(await store.get('a'), null);
});

test('a transaction that cannot start answers what failed', async () => {
    const store = new IndexedStore({
        transaction() {
            throw new Error('closed');
        },
    });

    assert.equal(await store.get('a'), null);
    assert.equal(await store.set('a', 1), false);
});

test('a transaction in error answers what failed', async () => {
    const store = new IndexedStore({
        transaction() {
            const transaction = { objectStore: () => ({ put() {} }) };
            setTimeout(() => transaction.onerror(), 0);

            return transaction;
        },
    });

    assert.equal(await store.set('a', 1), false);
});

test('localStorage is the store when IndexedDB refuses to open or is missing', async () => {
    for (const window of [
        { indexedDB: fakeIndexedDB({ refuseOpen: true }), localStorage: new MemoryStorage() },
        { indexedDB: fakeIndexedDB({ throwOnOpen: true }), localStorage: new MemoryStorage() },
        { localStorage: new MemoryStorage() },
        {
            get indexedDB() {
                throw new Error('denied');
            },
            localStorage: new MemoryStorage(),
        },
    ]) {
        const store = await openStore(window);

        assert.ok(store instanceof LocalStore);
        assert.equal(store.kind, 'local');
    }

    await exercise(new LocalStore(new MemoryStorage()));
});

test('a blocked opening is a refusal', async () => {
    const request = {};
    const opening = IndexedStore.open({ open: () => request });
    request.onblocked();

    assert.equal(await opening, null);
});

test("the page's memory is the store when nothing else is granted", async () => {
    for (const window of [
        { localStorage: new MemoryStorage(true) },
        {
            get localStorage() {
                throw new Error('denied');
            },
        },
        {},
        null,
    ]) {
        assert.ok((await openStore(window)) instanceof MemoryStore);
    }

    const store = new MemoryStore();
    assert.equal(store.kind, 'memory');
    await exercise(store);
});

test('a localStorage that refuses or holds garbage loses its values and throws nothing', async () => {
    const refusing = new LocalStore(new MemoryStorage(true));

    assert.equal(await refusing.set('a', 1), false);
    assert.equal(await refusing.get('a'), null);
    await refusing.remove('a');

    const storage = new MemoryStorage();
    storage.setItem('a', '{not json');
    assert.equal(await new LocalStore(storage).get('a'), null);
});

test('what a device kept in localStorage moves to IndexedDB when it opens', async () => {
    const indexedDB = fakeIndexedDB();
    const localStorage = new MemoryStorage();
    const manifest = { format: 1, checkpoint: 'hall', expiresAt: 2_000_000_000, key: 'a2V5', credentials: [] };
    localStorage.setItem('phil-checkin:device', JSON.stringify('door-1'));
    localStorage.setItem('phil-checkin:hall:manifest', JSON.stringify(manifest));
    localStorage.setItem('phil-checkin:hall:journal', JSON.stringify([{ id: 'old-1' }, { id: 'both' }]));
    localStorage.setItem('phil-checkin:hall:changes', '{not json');
    localStorage.setItem('theme', JSON.stringify('dark'));

    // IndexedDB already holds a newer journal, and a device of its own.
    const before = await IndexedStore.open(indexedDB);
    await before.set('phil-checkin:hall:journal', [{ id: 'both' }, { id: 'new-1' }]);
    await before.set('phil-checkin:device', 'door-2');

    const store = await openStore({ indexedDB, localStorage });

    assert.equal(store.kind, 'indexeddb');
    assert.deepEqual(await store.get('phil-checkin:hall:manifest'), manifest);
    assert.deepEqual(await store.get('phil-checkin:hall:journal'), [{ id: 'old-1' }, { id: 'both' }, { id: 'new-1' }]);
    assert.equal(await store.get('phil-checkin:device'), 'door-2');
    assert.equal(await store.get('phil-checkin:hall:changes'), null);
    assert.deepEqual([...localStorage.items.keys()], ['theme'], 'only what is not the controller stays');
    assert.equal(await migrate(store, localStorage), 0, 'nothing is left to move');
});

test('a value IndexedDB refuses stays in localStorage for the next opening', async () => {
    const localStorage = new MemoryStorage();
    localStorage.setItem('phil-checkin:hall:journal', JSON.stringify([{ id: 'old-1' }]));
    localStorage.setItem('phil-checkin:hall:manifest', 'null');

    const refusing = await IndexedStore.open(fakeIndexedDB({ refuseWrites: true }));
    assert.equal(await migrate(refusing, localStorage), 0);
    assert.deepEqual([...localStorage.items.keys()], ['phil-checkin:hall:journal']);

    const store = await IndexedStore.open(fakeIndexedDB());
    assert.equal(await migrate(store, localStorage), 1);
    assert.deepEqual(await store.get('phil-checkin:hall:journal'), [{ id: 'old-1' }]);
    assert.equal(localStorage.items.size, 0);
});

test('a journal joined to garbage keeps its scans, and a refusing localStorage moves nothing', async () => {
    const store = await IndexedStore.open(fakeIndexedDB());
    await store.set('phil-checkin:hall:journal', 'garbage');
    const localStorage = new MemoryStorage();
    localStorage.setItem('phil-checkin:hall:journal', JSON.stringify({ not: 'a list' }));
    localStorage.setItem('phil-checkin:door:journal', JSON.stringify([{ id: 'old-1' }]));

    assert.equal(await migrate(store, localStorage), 2);
    assert.deepEqual(await store.get('phil-checkin:hall:journal'), []);
    assert.deepEqual(await store.get('phil-checkin:door:journal'), [{ id: 'old-1' }]);

    assert.equal(await migrate(store, new MemoryStorage(true)), 0);
    assert.equal(await (await openStore({ indexedDB: fakeIndexedDB(), localStorage: new MemoryStorage(true) })).kind, 'indexeddb');
});
