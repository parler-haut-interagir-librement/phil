// The part of IndexedDB the store uses, in memory: one database of one object store, requests answered in a
// microtask and transactions completed once their requests are, writes refused on demand as a full quota refuses them.
export function fakeIndexedDB({ refuseOpen = false, throwOnOpen = false, refuseWrites = false } = {}) {
    const values = new Map();
    const stores = new Set();
    const opened = [];

    const database = {
        createObjectStore(name) {
            stores.add(name);
        },
        transaction(name, mode) {
            if (!stores.has(name)) {
                throw new Error(`No object store ${name}.`);
            }

            const transaction = {};
            let failed = false;
            const finish = () =>
                setTimeout(() => (failed ? transaction.onabort?.() : transaction.oncomplete?.()), 0);
            const write = (change) => {
                if ('readwrite' !== mode) {
                    throw new Error('A read-only transaction writes nothing.');
                }

                if (refuseWrites) {
                    failed = true;

                    return;
                }

                change();
            };

            transaction.objectStore = () => ({
                get(key) {
                    const request = {};
                    queueMicrotask(() => {
                        request.result = values.has(key) ? structuredClone(values.get(key)) : undefined;
                        request.onsuccess?.();
                    });

                    return request;
                },
                put(value, key) {
                    write(() => values.set(key, structuredClone(value)));
                },
                delete(key) {
                    write(() => values.delete(key));
                },
            });
            finish();

            return transaction;
        },
    };

    return {
        values,
        opened,
        open(name, version) {
            if (throwOnOpen) {
                throw new Error('SecurityError');
            }

            opened.push([name, version]);
            const request = {};
            queueMicrotask(() => {
                if (refuseOpen) {
                    request.onerror?.();

                    return;
                }

                request.result = database;

                if (0 === stores.size) {
                    request.onupgradeneeded?.();
                }

                request.onsuccess?.();
            });

            return request;
        },
    };
}

/** `localStorage` in memory, or one that refuses everything. */
export class MemoryStorage {
    constructor(refuse = false) {
        this.items = new Map();
        this.refuse = refuse;
    }

    getItem(key) {
        if (this.refuse) {
            throw new Error('denied');
        }

        return this.items.has(key) ? this.items.get(key) : null;
    }

    setItem(key, value) {
        if (this.refuse) {
            throw new Error('denied');
        }

        this.items.set(key, String(value));
    }

    removeItem(key) {
        if (this.refuse) {
            throw new Error('denied');
        }

        this.items.delete(key);
    }

    get length() {
        if (this.refuse) {
            throw new Error('denied');
        }

        return this.items.size;
    }

    key(index) {
        return [...this.items.keys()][index] ?? null;
    }
}
