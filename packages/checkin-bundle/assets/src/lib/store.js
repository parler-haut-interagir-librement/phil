// Where a device keeps what it needs offline. IndexedDB first: a manifest of 100 000 credentials weighs megabytes,
// more than the few the browser grants `localStorage`, and a scan recorded there is written in one transaction even
// with two tabs open on the same checkpoint. When IndexedDB is missing or refuses to open — some private windows,
// blocked site data —, `localStorage`; and when that refuses too, the memory of the page, lost on reload.
//
// Every store answers the same promises: `get(key)` the value or null, `set(key, value)` and `update(key, change)`
// true when the value is kept, `remove(key)`. None throws: a store that refuses only loses what it was given.
//
// What a device kept in `localStorage` before IndexedDB opened for it — a browser that refused it then, or an older
// copy of the controller — moves to IndexedDB the first time it opens: a journal not yet sent is joined to the one in
// IndexedDB, scan by scan, and anything else is kept unless IndexedDB already holds a newer value.

const DATABASE = 'phil-checkin';
const VERSION = 1;
const STORE = 'values';
const PREFIX = 'phil-checkin:';
const JOURNAL = ':journal';

/** The memory of the page. */
export class MemoryStore {
    constructor() {
        this.kind = 'memory';
        this.values = new Map();
    }

    async get(key) {
        return this.values.has(key) ? structuredClone(this.values.get(key)) : null;
    }

    async set(key, value) {
        this.values.set(key, structuredClone(value));

        return true;
    }

    async update(key, change) {
        return this.set(key, change(await this.get(key)));
    }

    async remove(key) {
        this.values.delete(key);
    }
}

/** `localStorage`, values written as JSON. */
export class LocalStore {
    constructor(storage) {
        this.kind = 'local';
        this.storage = storage;
    }

    async get(key) {
        try {
            return JSON.parse(this.storage.getItem(key) ?? 'null');
        } catch {
            return null;
        }
    }

    async set(key, value) {
        try {
            this.storage.setItem(key, JSON.stringify(value));

            return true;
        } catch {
            return false;
        }
    }

    async update(key, change) {
        return this.set(key, change(await this.get(key)));
    }

    async remove(key) {
        try {
            this.storage.removeItem(key);
        } catch {
            // Nothing to lose.
        }
    }
}

/** One object store of an IndexedDB database, values kept as structured clones. */
export class IndexedStore {
    constructor(database) {
        this.kind = 'indexeddb';
        this.database = database;
    }

    /** The store of the database, created at its first opening; null when IndexedDB is missing or refuses. */
    static open(indexedDB, name = DATABASE) {
        return new Promise((resolve) => {
            try {
                const request = indexedDB.open(name, VERSION);
                request.onupgradeneeded = () => request.result.createObjectStore(STORE);
                request.onsuccess = () => resolve(new IndexedStore(request.result));
                request.onerror = () => resolve(null);
                request.onblocked = () => resolve(null);
            } catch {
                resolve(null);
            }
        });
    }

    async get(key) {
        return this.transaction(
            'readonly',
            (store, done) => {
                const request = store.get(key);
                request.onsuccess = () => done(request.result ?? null);
            },
            null,
        );
    }

    async set(key, value) {
        return this.transaction(
            'readwrite',
            (store, done) => {
                store.put(value, key);
                done(true);
            },
            false,
        );
    }

    /** Reads, changes and writes a value in one transaction, so that two tabs never lose each other's change. */
    async update(key, change) {
        return this.transaction(
            'readwrite',
            (store, done) => {
                const request = store.get(key);
                request.onsuccess = () => {
                    store.put(change(request.result ?? null), key);
                    done(true);
                };
            },
            false,
        );
    }

    async remove(key) {
        await this.transaction(
            'readwrite',
            (store, done) => {
                store.delete(key);
                done(true);
            },
            false,
        );
    }

    /**
     * Runs a step in a transaction; its answer once the transaction completes, `failed` when it aborts or throws —
     * a full quota among others.
     */
    transaction(mode, step, failed) {
        return new Promise((resolve) => {
            let answer = failed;

            try {
                const transaction = this.database.transaction(STORE, mode);
                transaction.oncomplete = () => resolve(answer);
                transaction.onerror = () => resolve(failed);
                transaction.onabort = () => resolve(failed);
                step(transaction.objectStore(STORE), (value) => {
                    answer = value;
                });
            } catch {
                resolve(failed);
            }
        });
    }
}

/** The keys of the controller in a `localStorage`; none when it refuses to list them. */
function keysOf(storage) {
    try {
        return Array.from({ length: storage.length }, (_, index) => storage.key(index)).filter(
            (key) => 'string' === typeof key && key.startsWith(PREFIX),
        );
    } catch {
        return [];
    }
}

/** The value IndexedDB keeps when a value of `localStorage` joins it. */
function join(key, current, moved) {
    if (!key.endsWith(JOURNAL)) {
        return current ?? moved;
    }

    const scans = Array.isArray(current) ? current : [];
    const known = new Set(scans.map((scan) => scan?.id));

    // The scans of `localStorage` were decided before those of IndexedDB: the journal stays oldest first.
    return [...(Array.isArray(moved) ? moved : []).filter((scan) => !known.has(scan?.id)), ...scans];
}

/**
 * Moves the values of the controller kept in a `localStorage` to an IndexedDB store, each removed from `localStorage`
 * once IndexedDB keeps it; a value that is not JSON is dropped. The number of values moved.
 */
export async function migrate(indexed, storage) {
    let moved = 0;

    for (const key of keysOf(storage)) {
        const value = await new LocalStore(storage).get(key);

        if (null !== value && !(await indexed.update(key, (current) => join(key, current, value)))) {
            continue;
        }

        await new LocalStore(storage).remove(key);
        moved += null === value ? 0 : 1;
    }

    return moved;
}

/** The best store the window offers: IndexedDB, then `localStorage`, then the memory of the page. */
export async function openStore(window) {
    let indexedDB = null;
    let storage = null;

    try {
        indexedDB = window?.indexedDB ?? null;
    } catch {
        indexedDB = null;
    }

    const indexed = null === indexedDB ? null : await IndexedStore.open(indexedDB);

    try {
        storage = window?.localStorage ?? null;
        storage?.getItem(DATABASE);
    } catch {
        storage = null;
    }

    if (null !== indexed) {
        if (null !== storage) {
            await migrate(indexed, storage);
        }

        return indexed;
    }

    return null === storage ? new MemoryStore() : new LocalStore(storage);
}
