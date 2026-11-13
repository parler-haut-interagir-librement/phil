// The requests of the comments controller to the bundle's endpoints, each with the controller's own header and, when it
// changes something, a new CSRF token; an answer is `{ok, status, data}`, `data` the JSON body or null.

import { csrfHeaders } from './csrf.js';

const HEADERS = { 'X-Phil-Comments': '1', Accept: 'application/json' };

export class CommentsClient {
    constructor(config, fetcher, doc = globalThis.document) {
        this.urls = config.urls ?? {};
        this.subject = config.subject ?? '';
        this.csrfCookie = config.csrfCookie ?? null;
        this.fetch = fetcher;
        this.document = doc;
    }

    /**
     * The headers of a request: the controller's own and, for a request that changes something, a new CSRF token when
     * the server named its cookie. A `GET` only reads, so it carries none.
     */
    headers(changes = true) {
        return changes ? { ...HEADERS, ...csrfHeaders(this.csrfCookie, this.document) } : { ...HEADERS };
    }

    /** The URL of an endpoint, the identifier of a thread or comment put in place of `__id__`; null when not configured. */
    url(name, id = null) {
        const url = this.urls[name];

        if ('string' !== typeof url) {
            return null;
        }

        return null === id ? url : url.replace('__id__', encodeURIComponent(id));
    }

    async send(url, body = undefined) {
        if (null === url) {
            return { ok: false, status: 0, data: null };
        }

        const init =
            undefined === body
                ? { method: 'GET', headers: this.headers(false) }
                : {
                      method: 'POST',
                      headers: { ...this.headers(), 'Content-Type': 'application/json' },
                      body: JSON.stringify(body),
                  };

        try {
            const response = await this.fetch(url, { credentials: 'same-origin', ...init });
            let data = null;

            try {
                data = await response.json();
            } catch {
                data = null;
            }

            return { ok: response.ok, status: response.status, data };
        } catch {
            return { ok: false, status: 0, data: null };
        }
    }

    threads() {
        const url = this.url('threads');

        return this.send(
            null === url ? null : url + (url.includes('?') ? '&' : '?') + 'subject=' + encodeURIComponent(this.subject),
        );
    }

    open(anchor, body, suggestion = null) {
        return this.send(this.url('open'), { subject: this.subject, anchor, body, suggestion });
    }

    reply(thread, body) {
        return this.send(this.url('reply', thread), { body });
    }

    act(action, thread) {
        return this.send(this.url(action, thread), {});
    }

    edit(comment, body) {
        return this.send(this.url('edit', comment), { body });
    }

    remove(comment) {
        return this.send(this.url('delete', comment), {});
    }
}
