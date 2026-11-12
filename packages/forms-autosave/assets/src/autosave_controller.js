// A form's input kept while it is filled. Each change is saved, once the typing stops, as a draft in the browser's
// local storage under a key the server derived; with the server store, the draft is also sent to the server, by the
// revision read. When the form is shown again, the draft is put back with a notice that can discard it; when the
// server keeps a newer draft than this browser — saved from another device or tab —, a notice offers to use it.
// Passwords, files, CSRF tokens, hidden fields and fields marked sensitive are never kept. Once the form is sent and
// valid, the server says so in a cookie, and the draft is forgotten. The form's tag carries the controller:
//
//     {{ form_start(form) }}   {# with the option autosave: true #}
//         {{ include('@PhilFormsAutosave/status.html.twig') }}

import { Controller } from '@hotwired/stimulus';

import { debounce } from './lib/debounce.js';
import { apply, collect, remount, sameFields } from './lib/fields.js';
import { DraftClient } from './lib/server.js';
import { browserStorage, forgetDone, readDraft, removeDraft, writeDraft } from './lib/storage.js';
import { buildUi, labels } from './lib/ui.js';

const ID = 'phil--forms-autosave--autosave';

export default class extends Controller {
    static values = {
        key: String,
        version: String,
        ttl: { type: Number, default: 604800 },
        debounce: { type: Number, default: 800 },
        serverDebounce: { type: Number, default: 5000 },
        maxBytes: { type: Number, default: 262144 },
        endpoint: String,
        csrf: String,
        invalid: Boolean,
        labels: Object,
    };

    connect() {
        const document = this.element.ownerDocument;
        this.texts = labels(this.labelsValue);

        if (null === this.part('status')) {
            this.element.prepend(buildUi(document, this.texts, ID));
        }

        this.storage = browserStorage(document.defaultView ?? globalThis);
        this.revision = null;
        this.conflict = null;
        forgetDone(this.storage, document);
        this.original = collect(this.element);
        this.local = debounce(() => this.save(), Math.max(100, this.debounceValue));
        this.remote = debounce(() => this.push(), Math.max(1000, this.serverDebounceValue));
        this.client =
            '' === this.endpointValue
                ? null
                : new DraftClient(
                      this.endpointValue,
                      this.csrfValue,
                      (url, init) => globalThis.fetch(url, init),
                      document,
                  );
        this.listeners = [];
        this.listen(this.element, 'input', () => this.local.schedule());
        this.listen(this.element, 'change', () => this.local.schedule());
        this.listen(this.element, 'submit', () => this.flush());
        this.listen(document.defaultView ?? globalThis, 'pagehide', () => this.flush());

        if (null === this.storage) {
            this.say('unavailable');
        }

        const draft = readDraft(this.storage, this.keyValue, this.versionValue, this.ttlValue, Date.now());

        if (this.invalidValue) {
            this.save();
        } else if (null !== draft) {
            this.revision = draft.revision;
            this.restore(draft.fields);
        }

        if (null !== this.client) {
            this.sync(null !== draft || this.invalidValue);
        }
    }

    disconnect() {
        this.flush();
        this.remote.cancel();
        this.listeners.forEach(([target, type, handler]) => {
            target.removeEventListener(type, handler);
        });
        this.listeners = [];
    }

    listen(target, type, handler) {
        target.addEventListener(type, handler);
        this.listeners.push([target, type, handler]);
    }

    /** An element of the status or the notice, from the template or the one the controller added. */
    part(name) {
        return this.element.querySelector(`[data-${ID}-target~="${name}"]`);
    }

    /** Saves at once what waits to be saved. */
    flush() {
        this.local.flush();
    }

    /** Keeps the draft in the browser, with the revision of the server it was read from. */
    keep() {
        return writeDraft(
            this.storage,
            this.keyValue,
            { version: this.versionValue, savedAt: Date.now(), revision: this.revision, fields: collect(this.element) },
            this.maxBytesValue,
        );
    }

    save() {
        const result = this.keep();
        this.say(result);

        if ('saved' === result && null !== this.client) {
            this.remote.schedule();
        }
    }

    async push() {
        const result = await this.client.save(this.revision, this.versionValue, collect(this.element));

        if ('saved' === result.status) {
            this.revision = result.revision;
            this.keep();
            this.say('savedServer');
        } else if ('conflict' === result.status) {
            await this.sync(true);
        } else if ('tooLarge' === result.status) {
            this.say('tooLarge');
        }
    }

    /**
     * Reads the server's draft: put back when the browser had none, told as newer when it is another revision than the
     * one this browser read and holds other values.
     */
    async sync(hasLocal) {
        const copy = await this.client.read();

        if (null === copy || copy.version !== this.versionValue) {
            return;
        }

        if (copy.revision === this.revision || sameFields(copy.fields, collect(this.element))) {
            this.revision = copy.revision;

            return;
        }

        if (!hasLocal) {
            this.revision = copy.revision;
            this.restore(copy.fields);

            return;
        }

        this.conflict = copy;
        this.notice('conflict');
    }

    restore(fields) {
        const changed = apply(this.element, fields);

        if (0 === changed.length) {
            return;
        }

        this.changed(changed);
        this.notice('restored');
        this.dispatch('restored', { detail: { fields } });
    }

    /** Puts the values the page was shown with back, and forgets the draft here and on the server. */
    discard() {
        this.local.cancel();
        this.remote.cancel();
        this.changed(apply(this.element, this.original));
        removeDraft(this.storage, this.keyValue);
        this.client?.discard();
        this.revision = null;
        this.conflict = null;
        this.hide();
        this.say('discarded');
        this.dispatch('discarded');
    }

    /** Keeps the values in the form; after a conflict, they replace the newer draft at the next save. */
    dismiss() {
        if (null !== this.conflict) {
            this.revision = this.conflict.revision;
            this.conflict = null;
            this.save();
        }

        this.hide();
    }

    /** Puts the newer draft of the server in the form. */
    useServer() {
        if (null === this.conflict) {
            return;
        }

        this.revision = this.conflict.revision;
        this.changed(apply(this.element, this.conflict.fields));
        this.conflict = null;
        this.save();
        this.hide();
        this.say('restored');
    }

    /** Tells the page the fields changed, and mounts again the editors around them. */
    changed(elements) {
        for (const element of elements) {
            element.dispatchEvent(new Event('change', { bubbles: true }));
        }

        remount(elements);
    }

    notice(kind) {
        this.part('message').textContent = this.texts[kind];
        this.part('useServer').hidden = 'conflict' !== kind;
        this.part('discard').hidden = 'restored' !== kind;
        this.part('notice').hidden = false;
        this.say(kind);
    }

    hide() {
        this.part('notice').hidden = true;
    }

    say(kind) {
        this.part('status').textContent = this.texts[kind] ?? '';
    }
}
