/*
 * The Stimulus controller of phil/analytics-bundle's collection, on the page's body:
 *
 *     <body data-controller="phil--analytics-bundle--collect"
 *           data-phil--analytics-bundle--collect-endpoint-value="https://stats.example.com/collect">
 *
 * Each time it connects — the page loads, or Turbo renders the next one —, it sends one page view to the endpoint,
 * then the events of the page, and a second send when the page is left, with how far it was read and for how long;
 * all unless the browser asks not to be tracked. It sets no cookie and stores nothing. The page it last saw is kept in
 * memory for the life of the document only: it is the referrer of the next page Turbo renders.
 */
import { Controller } from '@hotwired/stimulus';
import { clickEventsOf, EngagementMeter, eventOf, pageView, privacyRequested, scrollPercent, send } from './collect.js';

/** The address of the page this document last showed, `null` before the first: what Turbo navigates away from. */
let previousPage = null;

export default class extends Controller {
    static values = {
        endpoint: String,
        verdict: String,
        clicks: { type: String, default: 'aggregate' },
        downloads: { type: Array, default: [] },
        dimensions: { type: Object, default: {} },
        status: { type: Number, default: 200 },
        searchTerm: { type: String, default: '' },
        searchResults: { type: Number, default: -1 },
    };

    #view = null;
    #meter = null;
    #frame = 0;
    #downloads = new Set();

    connect() {
        const previous = previousPage;
        previousPage = globalThis.location?.href ?? null;

        if (privacyRequested(globalThis.navigator)) {
            return;
        }

        this.#view = pageView(
            globalThis.location,
            globalThis.document,
            this.verdictValue,
            previous,
            this.dimensionsValue,
        );
        this.#push(this.#view);

        if (this.statusValue === 404) {
            this.#report('not_found');
        }

        if (this.searchTermValue !== '') {
            this.#report('search', {
                term: this.searchTermValue,
                ...(this.searchResultsValue < 0 ? {} : { results: this.searchResultsValue > 0 }),
            });
        }

        if (this.clicksValue !== 'off') {
            this.#downloads = new Set(this.downloadsValue);
            this.element.addEventListener('click', this.#onClick);
            this.element.addEventListener('auxclick', this.#onClick);
        }

        this.element.addEventListener('submit', this.#onSubmit);
        this.#meter = new EngagementMeter(performance.now(), globalThis.document.visibilityState !== 'hidden');
        this.#measure();
        globalThis.addEventListener('scroll', this.#onScroll, { passive: true });
        globalThis.addEventListener('pagehide', this.#leave);
        globalThis.document.addEventListener('visibilitychange', this.#onVisibility);
    }

    disconnect() {
        this.#leave();

        if (this.#view === null) {
            return;
        }

        this.element.removeEventListener('click', this.#onClick);
        this.element.removeEventListener('auxclick', this.#onClick);
        this.element.removeEventListener('submit', this.#onSubmit);
        globalThis.removeEventListener('scroll', this.#onScroll);
        globalThis.removeEventListener('pagehide', this.#leave);
        globalThis.document.removeEventListener('visibilitychange', this.#onVisibility);
    }

    #push(body) {
        send(this.endpointValue, body, globalThis.navigator);
    }

    #report(event, fields = {}) {
        this.#push(eventOf(this.#view, event, fields));
    }

    #measure() {
        this.#meter?.scrolled(scrollPercent(globalThis, globalThis.document.documentElement));
    }

    #onClick = (event) => {
        if (event.type === 'auxclick' && event.button !== 1) {
            return;
        }

        for (const [name, fields] of clickEventsOf(event.target, globalThis.location, this.#downloads)) {
            this.#report(name, fields);
        }
    };

    #onSubmit = (event) => {
        const form = event.target?.closest?.('form[data-phil-analytics-form]');

        if (form) {
            this.#report('form_submit', { form: form.dataset.philAnalyticsForm });
        }
    };

    #onScroll = () => {
        if (this.#frame !== 0) {
            return;
        }

        this.#frame = globalThis.requestAnimationFrame(() => {
            this.#frame = 0;
            this.#measure();
        });
    };

    #onVisibility = () => {
        this.#meter?.visibility(globalThis.document.visibilityState !== 'hidden', performance.now());
    };

    /** The second send of a page: once, whether the page is left for good or Turbo replaces it. */
    #leave = () => {
        if (this.#meter === null) {
            return;
        }

        const meter = this.#meter;
        this.#meter = null;
        this.#report('engagement', meter.report(performance.now()));
    };
}
