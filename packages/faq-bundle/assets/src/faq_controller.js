import { Controller } from '@hotwired/stimulus';
import { fold, MIN_COUNTED, matches, normalize, terms } from './lib/search.js';
import { remember, voted } from './lib/voted.js';

/**
 * The questions of a page, once JavaScript runs: the search filters them as one types, the question named by the
 * address opens, and a vote is sent without leaving the page. A search that still finds nothing after a pause is
 * counted, normalized, by the same route as the server's: the search only, no identifier.
 */
export default class extends Controller {
    static targets = ['input', 'question', 'section', 'status', 'none'];

    static values = {
        unansweredUrl: String,
        token: String,
        foundNone: String,
        foundOne: String,
        foundMany: String,
        thanks: String,
        failed: String,
        delay: { type: Number, default: 1500 },
    };

    connect() {
        this.texts = new Map(this.questionTargets.map((question) => [question, fold(question.textContent)]));
        this.counted = new Set();
        this.timer = null;
        this.onHash = () => this.openFromHash();
        this.window()?.addEventListener('hashchange', this.onHash);
        this.openFromHash();

        for (const anchor of voted(this.storage())) {
            const form = this.element.querySelector(`form[data-anchor="${CSS.escape(anchor)}"]`);

            if (form) {
                this.thank(form);
            }
        }
    }

    disconnect() {
        this.window()?.removeEventListener('hashchange', this.onHash);
        clearTimeout(this.timer);
    }

    filter() {
        const query = normalize(this.inputTarget.value);
        const words = terms(query);
        let found = 0;

        for (const question of this.questionTargets) {
            const match = matches(this.texts.get(question) ?? '', words);
            question.hidden = !match;
            found += match ? 1 : 0;
        }

        for (const section of this.sectionTargets) {
            section.hidden = !section.querySelector('details:not([hidden])');
        }

        const none = words.length > 0 && found === 0;
        this.noneTarget.hidden = !none;
        this.statusTarget.textContent = words.length === 0 ? '' : this.summary(found, this.inputTarget.value.trim());
        clearTimeout(this.timer);

        if (none) {
            this.timer = setTimeout(() => this.count(query), this.delayValue);
        }

        return found;
    }

    submit(event) {
        event.preventDefault();

        if (this.filter() === 0) {
            clearTimeout(this.timer);
            this.count(normalize(this.inputTarget.value));
        }
    }

    async vote(event) {
        event.preventDefault();
        const form = event.target;
        const body = new FormData(form, event.submitter ?? undefined);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (response.ok || response.status === 409) {
                remember(this.storage(), form.dataset.anchor);
                this.thank(form);

                return;
            }
        } catch {
            // The network is down: the reader is told below.
        }

        this.say(form, this.failedValue, 'alert');
    }

    count(query) {
        if (query.length < MIN_COUNTED || this.counted.has(query) || this.unansweredUrlValue === '') {
            return;
        }

        this.counted.add(query);
        const body = new FormData();
        body.append('q', query);
        body.append('_token', this.tokenValue);
        fetch(this.unansweredUrlValue, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(
            () => {},
        );
    }

    openFromHash() {
        const anchor = decodeURIComponent((this.window()?.location.hash ?? '').slice(1));
        const question = this.questionTargets.find((one) => one.id === anchor);

        if (question) {
            question.open = true;
        }
    }

    thank(form) {
        this.say(form, this.thanksValue, 'status');
    }

    say(form, message, role) {
        const fieldset = form.querySelector('fieldset') ?? form;
        const legend = fieldset.querySelector('legend');
        const line = form.ownerDocument.createElement('p');
        line.setAttribute('role', role);
        line.textContent = message;
        fieldset.replaceChildren(...(legend ? [legend] : []), line);
    }

    summary(found, query) {
        const template = [this.foundNoneValue, this.foundOneValue][found] ?? this.foundManyValue;

        return template.replaceAll('%query%', query).replaceAll('%count%', String(found));
    }

    window() {
        return this.element.ownerDocument?.defaultView ?? null;
    }

    storage() {
        try {
            return this.window()?.localStorage ?? null;
        } catch {
            return null;
        }
    }
}
