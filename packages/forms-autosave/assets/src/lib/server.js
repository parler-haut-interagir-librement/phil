// The drafts kept on the server, through the bundle's endpoints: one URL per draft, read with GET, saved with PUT by
// the revision read, discarded with DELETE. Each request carries the controller's header; those that change a draft
// carry a CSRF token. A failed request is answered as such, never thrown.

import { csrfHeaders } from './csrf.js';
import { isFields } from './storage.js';

export const HEADER = 'X-Phil-Forms-Autosave';

/** A draft as the server answers it, or null when the body is not one. */
export function parseServerDraft(body) {
    if (
        null === body ||
        'object' !== typeof body ||
        !Number.isInteger(body.revision) ||
        body.revision < 1 ||
        'string' !== typeof body.version ||
        !isFields(body.fields)
    ) {
        return null;
    }

    return { revision: body.revision, version: body.version, fields: body.fields };
}

export class DraftClient {
    constructor(endpoint, csrf, fetcher, doc = globalThis.document, random = globalThis.crypto) {
        this.endpoint = endpoint;
        this.csrf = csrf;
        this.fetcher = fetcher;
        this.doc = doc;
        this.random = random;
    }

    /** The draft kept, or null when none is, or the server cannot be read. */
    async read() {
        try {
            const response = await this.request('GET');

            return 200 === response.status ? parseServerDraft(await response.json()) : null;
        } catch {
            return null;
        }
    }

    /**
     * Saves the fields in place of the revision read: `{status: 'saved', revision}`, `{status: 'conflict', revision}`
     * when another was saved meanwhile, `{status: 'tooLarge'}`, or `{status: 'failed'}`.
     */
    async save(revision, version, fields) {
        try {
            const response = await this.request('PUT', { revision, version, fields });
            const body = 200 === response.status || 409 === response.status ? await response.json() : null;

            if (200 === response.status && Number.isInteger(body?.revision)) {
                return { status: 'saved', revision: body.revision };
            }

            if (409 === response.status) {
                return { status: 'conflict', revision: Number.isInteger(body?.revision) ? body.revision : null };
            }

            return { status: 413 === response.status ? 'tooLarge' : 'failed' };
        } catch {
            return { status: 'failed' };
        }
    }

    /** Discards the draft kept; whether the server did. */
    async discard() {
        try {
            return 204 === (await this.request('DELETE', null, true)).status;
        } catch {
            return false;
        }
    }

    request(method, body = null, keepalive = false) {
        const headers = { [HEADER]: '1', Accept: 'application/json' };

        if ('GET' !== method) {
            Object.assign(headers, csrfHeaders(this.csrf, this.doc, this.random));
        }

        if (null !== body) {
            headers['Content-Type'] = 'application/json';
        }

        return this.fetcher(this.endpoint, {
            method,
            headers,
            body: null === body ? undefined : JSON.stringify(body),
            credentials: 'same-origin',
            cache: 'no-store',
            keepalive,
        });
    }
}
