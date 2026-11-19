/*
 * The Stimulus controller of phil/commerce-pos-bundle's offline queue, on the main register's screen — the only one
 * that sells without the network; a mobile register waits for it instead:
 *
 *     <div data-controller="phil--commerce-pos-bundle--offline-queue"
 *          data-phil--commerce-pos-bundle--offline-queue-url-value="/register/offline-sales"
 *          data-action="pos:sale->phil--commerce-pos-bundle--offline-queue#sell">
 *         <span data-phil--commerce-pos-bundle--offline-queue-target="count"></span>
 *     </div>
 *
 * The register's own script dispatches a `pos:sale` event with the sale in `detail.sale`, its id from `newSaleId()`.
 * The sale is queued at once in the browser's IndexedDB, in the database the `database` value names, then sent; the
 * queue is flushed again when the page connects and whenever the browser comes back online. After each flush, the
 * controller dispatches `flushed` with the counts, and shows how many sales wait in its `count` target.
 *
 * A request is never redirected: a session that expired answers with a redirect to the login page, which counts as no
 * answer, and the sale waits. A 2xx counts as done only when its JSON body names the sale's id, as the server's does.
 */
import { Controller } from '@hotwired/stimulus';
import { IndexedDbStore, OfflineQueue } from './offline_queue.js';

export default class extends Controller {
    static values = { url: String, database: { type: String, default: 'phil-commerce-pos-offline-sales' } };

    static targets = ['count'];

    connect() {
        this.queue = new OfflineQueue(this.store(), (sale) => this.post(sale));
        this.onOnline = () => this.flush();
        this.window().addEventListener('online', this.onOnline);
        this.flush();
    }

    disconnect() {
        this.window().removeEventListener('online', this.onOnline);
    }

    async sell(event) {
        await this.queue.enqueue(event.detail.sale);

        return this.flush();
    }

    async flush() {
        const result = await this.queue.flush();

        if (this.hasCountTarget) {
            this.countTarget.textContent = String(result.left);
        }

        this.dispatch('flushed', { detail: result });

        return result;
    }

    async post(sale) {
        const response = await this.window().fetch(this.urlValue, {
            method: 'POST',
            headers: { 'content-type': 'application/json', accept: 'application/json' },
            body: JSON.stringify(sale),
            credentials: 'same-origin',
            redirect: 'manual',
        });

        if (response.type === 'opaqueredirect' || response.redirected) {
            return { status: 0 };
        }

        if (response.status >= 200 && response.status < 300 && !(await this.confirms(response, sale))) {
            return { status: 0 };
        }

        return { status: response.status };
    }

    async confirms(response, sale) {
        try {
            const body = await response.json();

            return body !== null && typeof body === 'object' && body.id === sale.id;
        } catch {
            return false;
        }
    }

    store() {
        return new IndexedDbStore(this.databaseValue, this.window().indexedDB);
    }

    window() {
        return globalThis.window;
    }
}
