// An IndexedDB as small as the offline queue uses: object stores with an auto-incremented key path, unique indexes,
// and requests that settle on a later microtask, as the browser's do. Databases are shared by name, as two tabs share
// theirs.
function request(work) {
    const pending = { result: undefined, error: null, onsuccess: null, onerror: null, onupgradeneeded: null };
    queueMicrotask(() => {
        try {
            pending.result = work(pending);
            pending.onsuccess?.();
        } catch (error) {
            pending.error = error;
            pending.onerror?.();
        }
    });

    return pending;
}

class FakeStore {
    constructor(keyPath) {
        this.keyPath = keyPath;
        this.rows = new Map();
        this.next = 1;
        this.indexes = {};
    }

    createIndex(name, keyPath, { unique }) {
        this.indexes[name] = { keyPath, unique };

        return {};
    }

    getAll() {
        return request(() => [...this.rows.keys()].sort((a, b) => a - b).map((key) => structuredClone(this.rows.get(key))));
    }

    add(value) {
        return request(() => {
            for (const { keyPath, unique } of Object.values(this.indexes)) {
                if (unique && [...this.rows.values()].some((row) => row[keyPath] === value[keyPath])) {
                    throw new DOMException('Key already exists in the index.', 'ConstraintError');
                }
            }

            const key = this.next++;
            this.rows.set(key, { ...structuredClone(value), [this.keyPath]: key });

            return key;
        });
    }

    delete(key) {
        return request(() => {
            this.rows.delete(key);
        });
    }

    index(name) {
        const { keyPath } = this.indexes[name];

        return {
            getKey: (value) => request(() => [...this.rows.entries()].find(([, row]) => row[keyPath] === value)?.[0]),
        };
    }
}

class FakeDatabase {
    constructor() {
        this.stores = {};
    }

    createObjectStore(name, { keyPath }) {
        this.stores[name] = new FakeStore(keyPath);

        return this.stores[name];
    }

    transaction(name) {
        return { objectStore: () => this.stores[name] };
    }
}

export class FakeIndexedDb {
    constructor() {
        this.databases = new Map();
        this.opened = [];
    }

    open(name, version) {
        this.opened.push([name, version]);

        return request((pending) => {
            if (!this.databases.has(name)) {
                const database = new FakeDatabase();
                this.databases.set(name, database);
                pending.result = database;
                pending.onupgradeneeded?.();
            }

            return this.databases.get(name);
        });
    }
}

export class FailingIndexedDb {
    open() {
        return request(() => {
            throw new DOMException('The user denied the storage.', 'UnknownError');
        });
    }
}
