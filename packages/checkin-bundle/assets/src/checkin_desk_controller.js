// The check-in desk of one checkpoint: a code typed or read by a scanner is sent to the server, which decides and
// records it; when the network is down, the code is decided from the offline manifest of the checkpoint and kept in
// a journal sent once the network is back. A camera scanner, or any other reader, hands its codes over by
// dispatching `phil-checkin:code` with `{code}` on the element. Offline, a code the manifest does not hold is offered
// to the page by `phil-checkin:lookup`, whose listener — a reader of signed codes, say — may answer with the entry it
// stands for through `detail.respond(entry)`, an entry or the promise of one.
//
// What the device keeps — its identifier, the manifest, the attendance it changed and the journal — goes to IndexedDB,
// or to `localStorage`, or to the memory of the page, the first the browser grants: see `lib/store.js`.
//
//     <form data-controller="phil--checkin-bundle--checkin-desk"
//           data-phil--checkin-bundle--checkin-desk-config-value="{{ config|json_encode }}"
//           data-action="phil--checkin-bundle--checkin-desk#submit">
//         <input data-phil--checkin-bundle--checkin-desk-target="code" autocomplete="off" autofocus>
//         <select data-phil--checkin-bundle--checkin-desk-target="direction">…</select>
//         <output data-phil--checkin-bundle--checkin-desk-target="result" role="status"></output>
//         <p data-phil--checkin-bundle--checkin-desk-target="state"></p>
//     </form>

import { Controller } from '@hotwired/stimulus';

import { csrfHeaders } from './lib/csrf.js';
import { apply, decide } from './lib/decide.js';
import { translator } from './lib/i18n.js';
import { Journal, Manifest, randomId } from './lib/offline.js';
import { openStore } from './lib/store.js';

const HEADER = 'X-Phil-Checkin';
const DEVICE = 'phil-checkin:device';
const DEVICE_PATTERN = /^[A-Za-z0-9._:-]{1,64}$/;
const REFRESH = 60_000;
const BATCH = 500;

/** Whether a listener's answer to `phil-checkin:lookup` has the shape of a manifest entry. */
function isEntry(entry) {
    const instant = (value) => null === value || undefined === value || Number.isInteger(value);

    return (
        null !== entry &&
        'object' === typeof entry &&
        'string' === typeof entry.id &&
        '' !== entry.id &&
        (null === entry.label || undefined === entry.label || 'string' === typeof entry.label) &&
        instant(entry.validFrom) &&
        instant(entry.validUntil) &&
        (null === entry.maxEntries || undefined === entry.maxEntries || Number.isInteger(entry.maxEntries))
    );
}

export default class extends Controller {
    static targets = ['code', 'direction', 'result', 'state'];

    static values = { config: Object };

    connect() {
        const config = this.configValue ?? {};
        const window = this.element.ownerDocument.defaultView;
        this.config = config;
        this.checkpoint = String(config.checkpoint ?? '');
        this.t = translator(config.locale ?? this.element.ownerDocument.documentElement.lang, config.messages ?? {});
        this.manifest = null;
        this.pending = 0;
        this.onCode = (event) => this.check(String(event.detail?.code ?? ''));
        this.onOnline = () => this.refresh();
        this.element.addEventListener('phil-checkin:code', this.onCode);
        window?.addEventListener('online', this.onOnline);
        window?.addEventListener('offline', this.onOnline);
        this.ready = this.start(window);
    }

    disconnect() {
        clearInterval(this.timer);
        this.element.removeEventListener('phil-checkin:code', this.onCode);
        this.element.ownerDocument.defaultView?.removeEventListener('online', this.onOnline);
        this.element.ownerDocument.defaultView?.removeEventListener('offline', this.onOnline);
    }

    /** Opens the store, reads what the device kept, then refreshes every minute. */
    async start(window) {
        this.store = await openStore(window);
        this.element.dataset.store = this.store.kind;
        this.device = await this.deviceId(this.config.device);
        this.journal = new Journal(this.store, this.checkpoint);
        this.manifest = this.config.offline ? await Manifest.load(this.store, this.checkpoint) : null;
        this.pending = (await this.journal.all()).length;
        this.timer = setInterval(() => this.refresh(), REFRESH);
        await this.refresh();
    }

    submit(event) {
        event.preventDefault();

        if (!this.hasCodeTarget) {
            return;
        }

        const code = this.codeTarget.value.trim();
        this.codeTarget.value = '';
        this.codeTarget.focus();
        this.check(code);
    }

    async check(code) {
        if ('' === code) {
            return;
        }

        await this.ready;
        const direction = this.hasDirectionTarget && 'out' === this.directionTarget.value ? 'out' : 'in';
        const id = randomId();
        const answer = await this.send('scan', {
            code,
            checkpoint: this.checkpoint,
            device: this.device,
            direction,
            id,
        });

        if (null !== answer) {
            if ('string' === typeof answer.credential?.id) {
                await this.manifest?.remember(answer.credential.id, answer.attendance, this.store);
            }

            this.show(answer.outcome ?? (503 === answer.status ? 'busy' : 'refused'), answer, false);

            return;
        }

        await this.checkOffline(code, direction, id);
    }

    /** Decides a code from the manifest, keeps it in the journal, and shows it as decided offline. */
    async checkOffline(code, direction, id) {
        const now = Math.floor(Date.now() / 1000);

        if (null === this.manifest || !this.manifest.isValid(now)) {
            this.show('offlineNoManifest', {}, true);

            return;
        }

        const found = await this.manifest.find(code);
        const { print, entry } = null === found.entry ? await this.lookup(code, found) : found;

        if (null === entry) {
            this.show('unknown', {}, true);

            return;
        }

        const outcome = decide(entry, this.manifest.policy, direction, now);
        const kept =
            false !== (await this.manifest.update(print, apply(entry, outcome), this.store)) &&
            false !== (await this.journal.push({ id, credential: entry.id, direction, outcome, scannedAt: now }));
        this.pending = (await this.journal.all()).length;
        this.show(outcome, { credential: { id: entry.id, label: entry.label ?? null }, attendance: entry }, true, kept);
        this.state();
    }

    /**
     * Offers a code the manifest does not hold to the page's listeners of `phil-checkin:lookup`; the entry the first
     * one answers, with the attendance the device kept for it, or the manifest's miss.
     */
    async lookup(code, miss) {
        let answer = null;
        const respond = (entry) => {
            answer ??= Promise.resolve(entry);
        };
        this.element.dispatchEvent(
            new CustomEvent('phil-checkin:lookup', { detail: { code, checkpoint: this.checkpoint, respond } }),
        );

        const entry = null === answer ? null : await answer.catch(() => null);

        if (!isEntry(entry)) {
            return miss;
        }

        const print = `id:${entry.id}`;
        const kept = this.manifest.entry(print);

        return {
            print,
            entry: {
                label: null,
                validFrom: null,
                validUntil: null,
                maxEntries: 1,
                revoked: false,
                ...entry,
                entries: kept?.entries ?? 0,
                inside: kept?.inside ?? false,
            },
        };
    }

    /** Sends the journal, then downloads a fresh manifest, when the network is there. */
    async refresh() {
        if (undefined === this.journal) {
            return;
        }

        if (this.config.offline) {
            const scans = (await this.journal.all()).slice(0, BATCH);

            if (scans.length > 0) {
                const answer = await this.send('sync', { checkpoint: this.checkpoint, device: this.device, scans });

                if (Array.isArray(answer?.scans)) {
                    await this.journal.forget(answer.scans.map((scan) => scan.id));
                }
            }

            const query = new URLSearchParams({ checkpoint: this.checkpoint, device: this.device });
            const manifest = Manifest.parse(await this.send('manifest', null, query));

            if (null !== manifest) {
                await manifest.save(this.store);
                await manifest.replay(await this.journal.all(), apply, this.store);
                this.manifest = manifest;
            }
        }

        this.pending = (await this.journal.all()).length;
        this.state();
    }

    /** Asks an endpoint; the JSON answered with its status, or null when the network or the endpoint is missing. */
    async send(endpoint, body, query = null) {
        const url = this.config.urls?.[endpoint];

        if ('string' !== typeof url || false === globalThis.navigator?.onLine) {
            return null;
        }

        const headers = { Accept: 'application/json', [HEADER]: '1' };
        const init = { method: 'GET', headers, credentials: 'same-origin' };

        if (null !== body) {
            Object.assign(headers, { 'Content-Type': 'application/json' }, csrfHeaders(this.config.csrfCookie));
            Object.assign(init, { method: 'POST', body: JSON.stringify(body) });
        }

        try {
            const response = await globalThis.fetch(null === query ? url : `${url}?${query}`, init);

            return response.status >= 500 && 503 !== response.status
                ? null
                : { ...(await response.json()), status: response.status };
        } catch {
            return null;
        }
    }

    show(key, answer, offline, kept = true) {
        const lastEntryAt = answer.attendance?.lastEntryAt;
        const time = Number.isInteger(lastEntryAt) ? new Date(lastEntryAt * 1000).toLocaleTimeString() : null;
        const text =
            'already_admitted' === key && null !== time ? this.t('already_admitted_at', { time }) : this.t(key);
        const label = answer.credential?.label;

        this.element.dataset.outcome = key;

        if (this.hasResultTarget) {
            this.resultTarget.textContent = [
                text,
                label,
                offline ? `(${this.t('decidedOffline')})` : null,
                kept ? null : this.t('notKept'),
            ]
                .filter(Boolean)
                .join(' — ');
        }

        this.dispatch('checked', { detail: { outcome: key, offline, kept, credential: answer.credential ?? null } });
    }

    state() {
        this.element.dataset.pending = String(this.pending);

        if (!this.hasStateTarget) {
            return;
        }

        const online = false !== globalThis.navigator?.onLine;
        this.stateTarget.textContent = [
            this.t(online ? 'online' : 'offline'),
            this.pending > 0 ? this.t('pending', { count: this.pending }) : null,
        ]
            .filter(Boolean)
            .join(' · ');
    }

    /** The device's identifier: the one configured, or the one drawn once and kept in the store. */
    async deviceId(given) {
        if ('string' === typeof given && DEVICE_PATTERN.test(given)) {
            return given;
        }

        const kept = await this.store.get(DEVICE);

        if ('string' === typeof kept && DEVICE_PATTERN.test(kept)) {
            return kept;
        }

        const drawn = randomId();
        await this.store.set(DEVICE, drawn);

        return drawn;
    }
}
