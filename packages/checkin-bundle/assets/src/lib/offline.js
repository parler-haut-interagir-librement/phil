// What a device keeps to check in without the network: the manifest of its checkpoint, whose credentials are found by
// the HMAC-SHA256 of a code under the manifest's key, the attendance the device changed since, and the journal of the
// scans decided offline. All live in a store of `store.js` — IndexedDB when the browser has it —, under the
// checkpoint's name; a store that refuses only loses them.
//
// The manifest is written once, when it is downloaded; each scan decided offline then writes only the entry it
// changed, and the journal, each in one transaction.

const FORMAT = 1;
const HEX = /^[0-9a-f]{64}$/;

/** The bytes of URL-safe base64 without padding. */
export function fromBase64Url(text) {
    const base64 = text.replaceAll('-', '+').replaceAll('_', '/');
    const binary = atob(base64 + '='.repeat((4 - (base64.length % 4)) % 4));

    return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

/** The HMAC-SHA256 of a code under a key, as 64 hexadecimal digits, as `CredentialCode::fingerprint()` computes it. */
export async function fingerprint(key, code, subtle = globalThis.crypto.subtle) {
    const imported = await subtle.importKey('raw', key, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
    const signature = new Uint8Array(await subtle.sign('HMAC', imported, new TextEncoder().encode(code)));

    return Array.from(signature, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/** A new identifier of 128 random bits, as 32 hexadecimal digits: a scan's or a device's. */
export function randomId(random = globalThis.crypto) {
    const bytes = new Uint8Array(16);
    random.getRandomValues(bytes);

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/**
 * The manifest of one checkpoint: its credentials by fingerprint, the policy, until when it may be used, and the
 * attendance the device has changed since it was issued.
 */
export class Manifest {
    constructor(data, changes = {}) {
        this.data = data;
        this.key = fromBase64Url(data.key);
        this.entries = new Map();
        this.ids = null;

        for (const [
            print,
            id,
            label,
            validFrom,
            validUntil,
            maxEntries,
            entries,
            inside,
            revoked,
        ] of data.credentials) {
            this.entries.set(print, { id, label, validFrom, validUntil, maxEntries, entries, inside, revoked });
        }

        for (const [print, entry] of Object.entries(changes ?? {})) {
            this.entries.set(print, entry);
        }
    }

    /** A manifest as the server gives it, or null when it is not one of format 1. */
    static parse(data, changes = {}) {
        if (
            null === data ||
            'object' !== typeof data ||
            FORMAT !== data.format ||
            'string' !== typeof data.key ||
            !Number.isInteger(data.expiresAt) ||
            !Array.isArray(data.credentials) ||
            !data.credentials.every((credential) => Array.isArray(credential) && HEX.test(String(credential[0])))
        ) {
            return null;
        }

        try {
            return new Manifest(data, changes);
        } catch {
            return null;
        }
    }

    /** The manifest a device kept for a checkpoint, with the attendance it changed since, or null. */
    static async load(store, checkpoint) {
        const data = await store.get(`phil-checkin:${checkpoint}:manifest`);

        return null === data ? null : Manifest.parse(data, await store.get(`phil-checkin:${checkpoint}:changes`));
    }

    /**
     * Keeps the manifest as downloaded, and forgets the attendance changed on the one before — except that of the
     * credentials a listener of `phil-checkin:lookup` answered, kept under `id:` keys, which no manifest carries.
     */
    async save(store) {
        const kept = await store.set(`phil-checkin:${this.data.checkpoint}:manifest`, this.data);
        let looked = {};
        await store.update(`phil-checkin:${this.data.checkpoint}:changes`, (changes) => {
            looked = Object.fromEntries(Object.entries(changes ?? {}).filter(([print]) => print.startsWith('id:')));

            return looked;
        });

        for (const [print, entry] of Object.entries(looked)) {
            this.entries.set(print, entry);
        }

        return kept;
    }

    get checkpoint() {
        return this.data.checkpoint;
    }

    get policy() {
        return this.data.policy ?? {};
    }

    isValid(now) {
        return now < this.data.expiresAt;
    }

    /** The fingerprint of a code and the entry it finds, the entry null for a code the manifest does not hold. */
    async find(code, subtle = globalThis.crypto.subtle) {
        const print = await fingerprint(this.key, code, subtle);

        return { print, entry: this.entries.get(print) ?? null };
    }

    /** The entry kept under a key, or null. */
    entry(print) {
        return this.entries.get(print) ?? null;
    }

    /** Changes an entry in memory and, given a store, keeps the change there. */
    async update(print, entry, store = null) {
        this.entries.set(print, entry);

        return (
            null === store ||
            store.update(`phil-checkin:${this.data.checkpoint}:changes`, (changes) => ({
                ...(changes ?? {}),
                [print]: entry,
            }))
        );
    }

    /** The key of a credential's entry by its identifier, or undefined. */
    printOf(id) {
        if (null === this.ids) {
            this.ids = new Map();

            for (const [print, entry] of this.entries) {
                this.ids.set(entry.id, print);
            }
        }

        return this.ids.get(id);
    }

    /**
     * Takes the attendance the server answered to a scan made online, so that the device decides on it once the
     * network is gone; a credential the manifest does not hold is left alone.
     */
    async remember(id, attendance, store = null) {
        const print = this.printOf(id);

        if (undefined === print || !Number.isInteger(attendance?.entries) || 'boolean' !== typeof attendance?.inside) {
            return false;
        }

        return this.update(
            print,
            { ...this.entries.get(print), entries: attendance.entries, inside: attendance.inside },
            store,
        );
    }

    /**
     * Applies again the scans of a journal not yet synchronised to a fresh manifest, which the server issued without
     * them, so that a holder admitted offline is not admitted again. `apply` is the policy's.
     */
    async replay(scans, apply, store = null) {
        for (const scan of scans) {
            const print = this.printOf(scan.credential);

            if (undefined !== print) {
                await this.update(print, apply(this.entries.get(print), scan.outcome), store);
            }
        }
    }
}

/** The scans decided offline, oldest first, until the server's report names them. */
export class Journal {
    constructor(store, checkpoint) {
        this.store = store;
        this.key = `phil-checkin:${checkpoint}:journal`;
    }

    async all() {
        const scans = await this.store.get(this.key);

        return Array.isArray(scans) ? scans : [];
    }

    async push(scan) {
        return this.store.update(this.key, (scans) => [...(Array.isArray(scans) ? scans : []), scan]);
    }

    async forget(ids) {
        const named = new Set(ids);

        return this.store.update(this.key, (scans) =>
            (Array.isArray(scans) ? scans : []).filter((scan) => !named.has(scan.id)),
        );
    }
}
