// The endpoints of the bundle, called for one resource, each request with the controller's header and, unless it only
// reads the presence, a new CSRF token: each answer is `{ok, status, body}`, a failed request `{ok: false, status: 0, body: null}`, so that the
// controller never throws for the network.

import { csrfHeaders } from './csrf.js';

export const HEADER = 'X-Phil-Edit-Lock';

const NONE = Object.freeze({ ok: false, status: 0, body: null });

export class LeaseClient {
    /** `config` holds `resource`, `urls` and `csrfCookie`, as the bundle's `ClientConfig` gives them; `fetcher` is `fetch`. */
    constructor(config, fetcher, doc = globalThis.document) {
        this.resource = 'string' === typeof config.resource ? config.resource : null;
        this.urls = null !== config.urls && 'object' === typeof config.urls ? config.urls : null;
        this.csrfCookie = config.csrfCookie ?? null;
        this.fetcher = fetcher;
        this.document = doc;
    }

    get enabled() {
        return null !== this.resource && null !== this.urls;
    }

    acquire(part) {
        return this.post('acquire', { part });
    }

    heartbeat(part) {
        return this.post('heartbeat', { part });
    }

    release(part) {
        return this.post('release', { part });
    }

    takeOver(part) {
        return this.post('takeOver', { part });
    }

    /** Gives up every lease of the writer on the resource; `keepalive` lets it finish while the page closes. */
    leave(keepalive = false) {
        return this.post('leave', {}, keepalive);
    }

    save(block, list, after) {
        return this.post('save', { block, list, after });
    }

    remove(id) {
        return this.post('remove', { id });
    }

    presence() {
        if (!this.enabled || 'string' !== typeof this.urls.presence) {
            return Promise.resolve(NONE);
        }

        return this.send(
            this.urls.presence +
                (this.urls.presence.includes('?') ? '&' : '?') +
                'resource=' +
                encodeURIComponent(this.resource),
            {
                method: 'GET',
                headers: { [HEADER]: '1', Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
    }

    post(name, body, keepalive = false) {
        if (!this.enabled || 'string' !== typeof this.urls[name]) {
            return Promise.resolve(NONE);
        }

        return this.send(this.urls[name], {
            method: 'POST',
            headers: {
                [HEADER]: '1',
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...csrfHeaders(this.csrfCookie, this.document),
            },
            body: JSON.stringify({ resource: this.resource, ...body }),
            credentials: 'same-origin',
            keepalive,
        });
    }

    async send(url, init) {
        let response;

        try {
            response = await this.fetcher(url, init);
        } catch {
            return NONE;
        }

        let body = null;

        try {
            const text = await response.text();
            body = '' === text ? null : JSON.parse(text);
        } catch {
            body = null;
        }

        return { ok: response.ok, status: response.status, body };
    }
}
