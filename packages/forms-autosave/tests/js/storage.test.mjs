import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    browserStorage,
    DONE_COOKIE,
    doneKeys,
    forgetDone,
    parseDraft,
    readDraft,
    removeDraft,
    storageKey,
    writeDraft,
} from '../../assets/src/lib/storage.js';
import { FakeStorage } from './fakes.mjs';

const KEY = 'abcdefghijklmnopqrstuvwxyz012345';
const NOW = 1_800_000_000_000;

function stored(draft) {
    return JSON.stringify({ v: 1, version: 'v1', savedAt: NOW - 1000, revision: null, fields: { a: ['1'] }, ...draft });
}

const refusing = {
    getItem() {
        throw new Error('SecurityError');
    },
    setItem() {
        throw new Error('QuotaExceededError');
    },
    removeItem() {
        throw new Error('SecurityError');
    },
};

test('a draft is kept under a prefixed key', () => {
    assert.equal(storageKey(KEY), `phil-autosave:${KEY}`);
});

test('the storage of the page, or null when the browser refuses it', () => {
    const storage = new FakeStorage();
    assert.equal(browserStorage({ localStorage: storage }), storage);
    assert.equal(browserStorage({}), null);
    assert.equal(
        browserStorage({
            get localStorage() {
                throw new Error('SecurityError');
            },
        }),
        null,
    );
});

test('a stored draft is read back only when it is one', () => {
    assert.deepEqual(parseDraft(stored({ revision: 3 })), { version: 'v1', savedAt: NOW - 1000, revision: 3, fields: { a: ['1'] } });

    for (const json of [
        'not json',
        'null',
        '"text"',
        stored({ v: 2 }),
        stored({ version: 7 }),
        stored({ savedAt: 'yesterday' }),
        stored({ savedAt: null }),
        stored({ revision: 0 }),
        stored({ revision: 1.5 }),
        stored({ revision: '1' }),
        stored({ fields: null }),
        stored({ fields: [] }),
        stored({ fields: 'a' }),
        stored({ fields: { a: '1' } }),
        stored({ fields: { a: [1] } }),
    ]) {
        assert.equal(parseDraft(json), null, json);
    }
});

test('a draft of another structure, expired, from the future or broken is removed when read', () => {
    const ttl = 3600;

    for (const [json, kept] of [
        [stored({}), true],
        [stored({ savedAt: NOW - ttl * 1000 }), true],
        [stored({ savedAt: NOW - ttl * 1000 - 1 }), false],
        [stored({ savedAt: NOW + 60000 }), true],
        [stored({ savedAt: NOW + 60001 }), false],
        [stored({ version: 'v0' }), false],
        ['broken', false],
    ]) {
        const storage = new FakeStorage({ [storageKey(KEY)]: json });
        const draft = readDraft(storage, KEY, 'v1', ttl, NOW);

        assert.equal(null !== draft, kept, json);
        assert.equal(storage.entries.has(storageKey(KEY)), kept, json);
    }

    assert.equal(readDraft(new FakeStorage(), KEY, 'v1', ttl, NOW), null);
    assert.equal(readDraft(null, KEY, 'v1', ttl, NOW), null);
    assert.equal(readDraft(refusing, KEY, 'v1', ttl, NOW), null);
});

test('a draft is written under its key, refused above the size, or when the browser refuses', () => {
    const storage = new FakeStorage();
    const draft = { version: 'v1', savedAt: NOW, revision: 2, fields: { a: ['é'] } };
    const json = JSON.stringify({ v: 1, ...draft });
    const bytes = new TextEncoder().encode(json).length;

    assert.equal(writeDraft(storage, KEY, draft, bytes), 'saved');
    assert.equal(storage.getItem(storageKey(KEY)), json);
    assert.equal(bytes, json.length + 1, 'counted in bytes, not characters');
    assert.equal(writeDraft(storage, KEY, draft, bytes - 1), 'tooLarge');
    assert.equal(storage.getItem(storageKey(KEY)), null, 'the older draft is removed');
    assert.equal(writeDraft(null, KEY, draft, bytes), 'unavailable');
    assert.equal(writeDraft(refusing, KEY, draft, bytes), 'unavailable');
});

test('removing never throws', () => {
    const storage = new FakeStorage({ [storageKey(KEY)]: 'x' });
    removeDraft(storage, KEY);
    assert.equal(storage.entries.size, 0);
    removeDraft(null, KEY);
    removeDraft(refusing, KEY);
});

test('the keys the server said were sent are read from the cookie', () => {
    const other = 'ABCDEFGHIJKLMNOP';

    assert.deepEqual(doneKeys(`a=b; ${DONE_COOKIE}=${KEY}.${other}.bad!.short; c=d`), [KEY, other]);
    assert.deepEqual(doneKeys(`${DONE_COOKIE}=${encodeURIComponent(`${KEY}.${other}`)}`), [KEY, other]);
    assert.deepEqual(doneKeys(`x${DONE_COOKIE}=${KEY}`), []);
    assert.deepEqual(doneKeys(`${DONE_COOKIE}=%E0%A4%A`), []);
    assert.deepEqual(doneKeys(''), []);
    assert.deepEqual(doneKeys(undefined), []);
});

test('the drafts of sent forms are forgotten, then the cookie', () => {
    const other = 'ABCDEFGHIJKLMNOP';
    const storage = new FakeStorage({ [storageKey(KEY)]: 'x', [storageKey(other)]: 'y', [storageKey('kept-kept-kept-k')]: 'z' });
    const doc = { cookie: `${DONE_COOKIE}=${KEY}.${other}` };

    assert.deepEqual(forgetDone(storage, doc), [KEY, other]);
    assert.deepEqual([...storage.entries.keys()], [storageKey('kept-kept-kept-k')]);
    assert.equal(doc.cookie, `${DONE_COOKIE}=; path=/; max-age=0; samesite=lax`);

    const untouched = { cookie: 'a=b' };
    assert.deepEqual(forgetDone(storage, untouched), []);
    assert.equal(untouched.cookie, 'a=b');
});
