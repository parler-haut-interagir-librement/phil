import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { test } from 'node:test';

import { fingerprint, fromBase64Url, Journal, Manifest, randomId } from '../../assets/src/lib/offline.js';
import { LocalStore, MemoryStore } from '../../assets/src/lib/store.js';
import { MemoryStorage } from './support/fake-indexeddb.mjs';

const key = Buffer.from('0123456789abcdef0123456789abcdef');
const print = createHmac('sha256', key).update('CODE-1').digest('hex');
const data = {
    format: 1,
    checkpoint: 'hall',
    device: 'd1',
    issuedAt: 1000,
    expiresAt: 2000,
    key: key.toString('base64url'),
    policy: { earlySeconds: 0, antiPassback: true },
    credentials: [[print, 'T-1', 'Élise', null, null, 1, 0, false, false]],
};

test('URL-safe base64 is read without padding', () => {
    assert.deepEqual(Array.from(fromBase64Url('-_8')), [251, 255]);
    assert.deepEqual(Array.from(fromBase64Url('AQ')), [1]);
    assert.deepEqual(Array.from(fromBase64Url('AQID')), [1, 2, 3]);
});

test('the fingerprint is the HMAC-SHA256 of the server', async () => {
    assert.equal(await fingerprint(new Uint8Array(key), 'CODE-1'), print);
    assert.equal(
        await fingerprint(new Uint8Array(key), 'Billet n° 7'),
        createHmac('sha256', key).update('Billet n° 7').digest('hex'),
    );
});

test('a random identifier is 32 hexadecimal digits', () => {
    assert.match(randomId(), /^[0-9a-f]{32}$/);
    assert.equal(randomId({ getRandomValues: (bytes) => bytes.fill(171) }), 'ab'.repeat(16));
});

test('a manifest finds its credentials by the fingerprint of their codes', async () => {
    const manifest = Manifest.parse(data);

    assert.deepEqual(await manifest.find('CODE-1'), {
        print,
        entry: {
            id: 'T-1',
            label: 'Élise',
            validFrom: null,
            validUntil: null,
            maxEntries: 1,
            entries: 0,
            inside: false,
            revoked: false,
        },
    });
    assert.equal((await manifest.find('CODE-2')).entry, null);
    assert.deepEqual(manifest.policy, { earlySeconds: 0, antiPassback: true });
    assert.equal(manifest.isValid(1999), true);
    assert.equal(manifest.isValid(2000), false);
});

test('only a manifest of format 1 is read', () => {
    for (const bad of [
        null,
        'x',
        { ...data, format: 2 },
        { ...data, key: 3 },
        { ...data, expiresAt: '2000' },
        { ...data, credentials: {} },
        { ...data, credentials: [['nothex']] },
        { ...data, key: '%%%' },
    ]) {
        assert.equal(Manifest.parse(bad), null);
    }

    assert.deepEqual(Manifest.parse({ ...data, policy: undefined }).policy, {});
});

test('a manifest is kept once and the attendance the device changes is kept apart', async () => {
    const store = new MemoryStore();
    const manifest = Manifest.parse(data);
    assert.equal(await manifest.save(store), true);
    assert.deepEqual(await store.get('phil-checkin:hall:manifest'), data);

    const { entry } = await manifest.find('CODE-1');
    assert.equal(await manifest.update(print, { ...entry, entries: 1, inside: true }, store), true);
    assert.deepEqual(await store.get('phil-checkin:hall:manifest'), data, 'the manifest itself is not written again');
    assert.equal((await manifest.find('CODE-1')).entry.inside, true);

    const loaded = await Manifest.load(store, 'hall');
    assert.equal((await loaded.find('CODE-1')).entry.inside, true);
    assert.equal(loaded.checkpoint, 'hall');
    assert.equal(await Manifest.load(store, 'stage'), null);
    assert.equal(await Manifest.load(new LocalStore(new MemoryStorage(true)), 'hall'), null);
    assert.equal(await manifest.save(new LocalStore(new MemoryStorage(true))), false);
});

test('a change kept in memory only needs no store', async () => {
    const manifest = Manifest.parse(data);

    assert.equal(await manifest.update(print, { id: 'T-1', entries: 3 }), true);
    assert.deepEqual(manifest.entry(print), { id: 'T-1', entries: 3 });
    assert.equal(manifest.entry('nothing'), null);
});

test('a fresh manifest forgets the changes of the one before, but those of credentials looked up', async () => {
    const store = new MemoryStore();
    const old = Manifest.parse(data);
    await old.save(store);
    await old.update(print, { ...old.entry(print), entries: 1, inside: true }, store);
    await old.update('id:S-1', { id: 'S-1', entries: 1, inside: true }, store);

    const fresh = Manifest.parse({ ...data, issuedAt: 1500 });
    await fresh.save(store);

    assert.deepEqual(await store.get('phil-checkin:hall:changes'), { 'id:S-1': { id: 'S-1', entries: 1, inside: true } });
    assert.equal(fresh.entry(print).inside, false);
    assert.equal(fresh.entry('id:S-1').inside, true);
    assert.equal((await Manifest.load(store, 'hall')).entry('id:S-1').entries, 1);
});

test('the scans of the journal are applied again to a fresh manifest', async () => {
    const store = new MemoryStore();
    const manifest = Manifest.parse({
        ...data,
        credentials: [...data.credentials, ['ab'.repeat(32), 'T-2', null, null, null, null, 0, false, false]],
    });
    const applied = [];
    const apply = (entry, outcome) => {
        applied.push([entry.id, outcome]);

        return { ...entry, entries: entry.entries + 1 };
    };

    await manifest.replay(
        [
            { credential: 'T-1', outcome: 'admitted' },
            { credential: 'T-9', outcome: 'admitted' },
            { credential: 'T-2', outcome: 'expired' },
        ],
        apply,
        store,
    );
    await manifest.replay([{ credential: 'T-1', outcome: 'checked_out' }], apply, store);

    assert.deepEqual(applied, [
        ['T-1', 'admitted'],
        ['T-2', 'expired'],
        ['T-1', 'checked_out'],
    ]);
    assert.equal(manifest.entry(print).entries, 2);
    assert.equal((await store.get('phil-checkin:hall:changes'))[print].entries, 2);
});

test('the journal keeps the scans until the server names them', async () => {
    const journal = new Journal(new MemoryStore(), 'hall');

    assert.deepEqual(await journal.all(), []);
    await journal.push({ id: 'a' });
    await journal.push({ id: 'b' });
    await journal.push({ id: 'c' });
    assert.deepEqual(await journal.all(), [{ id: 'a' }, { id: 'b' }, { id: 'c' }]);
    await journal.forget(['a', 'c', 'z']);
    assert.deepEqual(await journal.all(), [{ id: 'b' }]);
});

test('a journal holding garbage starts again empty', async () => {
    const store = new MemoryStore();
    await store.set('phil-checkin:hall:journal', 'garbage');
    const journal = new Journal(store, 'hall');

    assert.deepEqual(await journal.all(), []);
    await journal.forget(['a']);
    assert.deepEqual(await journal.all(), []);
    await store.set('phil-checkin:hall:journal', 'garbage');
    await journal.push({ id: 'a' });
    assert.deepEqual(await journal.all(), [{ id: 'a' }]);
});

test('a journal on a store that refuses loses its scans and throws nothing', async () => {
    const journal = new Journal(new LocalStore(new MemoryStorage(true)), 'hall');

    assert.equal(await journal.push({ id: 'a' }), false);
    assert.deepEqual(await journal.all(), []);
    assert.equal(await journal.forget(['a']), false);
});

test('the attendance answered online is remembered for a credential the manifest holds', async () => {
    const store = new MemoryStore();
    const manifest = Manifest.parse(data);

    assert.equal(await manifest.remember('T-1', { entries: 2, inside: true }, store), true);
    assert.deepEqual([manifest.entry(print).entries, manifest.entry(print).inside, manifest.entry(print).label], [2, true, 'Élise']);
    assert.equal((await store.get('phil-checkin:hall:changes'))[print].entries, 2);
    assert.equal(await manifest.remember('T-9', { entries: 1, inside: true }, store), false);
    assert.equal(await manifest.remember('T-1', { entries: '1', inside: true }), false);
    assert.equal(await manifest.remember('T-1', { entries: 1, inside: 'yes' }), false);
    assert.equal(await manifest.remember('T-1', undefined), false);
    assert.equal(manifest.entry(print).entries, 2);
});
