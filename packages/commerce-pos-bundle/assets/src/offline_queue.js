/*
 * The offline queue of phil/commerce-pos-bundle: the sales the main register rings up wait in the browser's
 * IndexedDB until the server takes them, in the order they were rung up.
 *
 * A sale the server answers with 2xx is done, imported now or before. A sale it answers with a 4xx is set aside among
 * the rejected, for someone to look at: sending it again would change nothing. No answer — the network, a redirect to
 * a login page, a status of 0 —, a 401, 408 or 429, or a 5xx stops the flush: the sale and those after it wait for
 * the next one, and nothing is lost while the cashier signs in again.
 *
 * A sale is removed by its id once answered, never by its place in the queue: two tabs flushing the same database send
 * a sale twice at worst — which the server recognises —, never drop one unsent.
 */

/** The answers that say nothing of the sale: try again later. */
const RETRY = new Set([401, 408, 429]);

/** A new sale id: the order's number on the server, and what makes sending it twice harmless. */
export function newSaleId(cryptoApi = globalThis.crypto) {
    return cryptoApi.randomUUID();
}

/** Resolves with a request's result, rejects with its error. */
function settled(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

/**
 * The sales kept in an IndexedDB database of their own: `pending`, in the order they were queued, and `rejected`, each
 * with the status the server answered. The database survives a reload, a closed tab and a restarted tablet.
 */
export class IndexedDbStore {
    /**
     * @param {string}     name      the database's name
     * @param {IDBFactory} indexedDb the browser's `indexedDB`
     */
    constructor(name, indexedDb = globalThis.indexedDB) {
        this.name = name;
        this.indexedDb = indexedDb;
        this.database = null;
    }

    open() {
        this.database ??= new Promise((resolve, reject) => {
            const request = this.indexedDb.open(this.name, 1);
            request.onupgradeneeded = () => {
                const database = request.result;
                database
                    .createObjectStore('pending', { keyPath: 'seq', autoIncrement: true })
                    .createIndex('id', 'id', { unique: true });
                database.createObjectStore('rejected', { keyPath: 'seq', autoIncrement: true });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        return this.database;
    }

    async store(name, mode) {
        return (await this.open()).transaction(name, mode).objectStore(name);
    }

    /** The sales waiting, oldest first. */
    async all() {
        return (await settled((await this.store('pending', 'readonly')).getAll())).map((row) => row.sale);
    }

    /** Queues a sale; a sale already waiting under its id stays as it is. */
    async add(sale) {
        const store = await this.store('pending', 'readwrite');

        if ((await settled(store.index('id').getKey(sale.id))) === undefined) {
            await settled(store.add({ id: sale.id, sale }));
        }
    }

    /** Removes a sale by its id, wherever it is in the queue. */
    async remove(id) {
        const store = await this.store('pending', 'readwrite');
        const key = await settled(store.index('id').getKey(id));

        if (key !== undefined) {
            await settled(store.delete(key));
        }
    }

    async reject(sale, status) {
        await settled((await this.store('rejected', 'readwrite')).add({ sale, status }));
    }

    /** The sales the server refused, each with the status it answered. */
    async rejected() {
        return (await settled((await this.store('rejected', 'readonly')).getAll())).map(({ sale, status }) => ({
            sale,
            status,
        }));
    }
}

export class OfflineQueue {
    /**
     * @param {{all: Function, add: Function, remove: Function, reject: Function, rejected: Function}} store where the
     *        sales wait: an `IndexedDbStore`
     * @param {(sale: object) => Promise<{status: number}>} send posts one sale; a status of 0 is no answer
     */
    constructor(store, send) {
        this.store = store;
        this.send = send;
        this.flushing = null;
    }

    /** The sales waiting, oldest first. */
    pending() {
        return this.store.all();
    }

    /** The sales the server refused, each with the status it answered. */
    rejected() {
        return this.store.rejected();
    }

    enqueue(sale) {
        if (sale === null || typeof sale !== 'object' || typeof sale.id !== 'string' || sale.id === '') {
            return Promise.reject(new TypeError('A sale is an object with a text id: give it one from newSaleId().'));
        }

        return this.store.add(sale);
    }

    /**
     * Sends the waiting sales one by one; a second call while a flush runs waits for that one.
     *
     * @returns {Promise<{sent: number, rejected: number, left: number}>}
     */
    flush() {
        this.flushing ??= this.run().finally(() => {
            this.flushing = null;
        });

        return this.flushing;
    }

    async run() {
        let sent = 0;
        let rejected = 0;

        for (const sale of await this.pending()) {
            let response;

            try {
                response = await this.send(sale);
            } catch {
                break;
            }

            const status = response.status;

            if (status < 200 || (status >= 300 && status < 400) || status >= 500 || RETRY.has(status)) {
                break;
            }

            if (status >= 400) {
                await this.store.reject(sale, status);
                rejected++;
            } else {
                sent++;
            }

            await this.store.remove(sale.id);
        }

        return { sent, rejected, left: (await this.pending()).length };
    }
}
