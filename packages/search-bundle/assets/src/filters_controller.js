import { Controller } from '@hotwired/stimulus';
import { formUrl, isPlainClick, isSameOrigin } from './lib/navigation.js';

/**
 * The filters of a results page, on a plain `GET` form and plain links that work alone. With this controller, a box
 * ticked applies its filter at once, a link removing a filter is followed in place, and the page is fetched and drawn
 * again without being reloaded: the filters, and the results whose element the `results` value names. The count of
 * results is written into the live region, which stays in the page so that a screen reader says it; the focus comes
 * back to the box or the link that had it, else to the filters, when it was among them. The address changes with the filters, and going back
 * draws the previous ones. When the page cannot be fetched, the browser opens it as it would without the controller.
 */
export default class extends Controller {
    static targets = ['form', 'status', 'submit'];

    static values = { results: String };

    connect() {
        for (const button of this.submitTargets) {
            button.hidden = true;
        }

        this.element.dataset.enhanced = 'true';
    }

    disconnect() {
        this.pending?.abort();
    }

    change() {
        this.load(formUrl(this.formTarget.getAttribute('action'), new FormData(this.formTarget), window.location));
    }

    submit(event) {
        event.preventDefault();
        this.change();
    }

    follow(event) {
        if (!isPlainClick(event)) {
            return;
        }

        event.preventDefault();
        this.load(new URL(event.currentTarget.getAttribute('href'), window.location.href));
    }

    restore() {
        this.load(new URL(window.location.href), false);
    }

    async load(url, remember = true) {
        if (!isSameOrigin(url, window.location)) {
            window.location.assign(url.href);

            return;
        }

        const active = this.element.ownerDocument.activeElement;
        // The focus is given back only when it was among the filters: '' for the filters themselves, null for elsewhere.
        const focused = active !== null && this.element.contains(active) ? active.id : null;
        this.pending?.abort();
        const pending = new AbortController();
        this.pending = pending;
        this.element.setAttribute('aria-busy', 'true');
        let page;

        try {
            const response = await fetch(url.href, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                signal: pending.signal,
            });

            if (!response.ok) {
                throw new Error(`The results page answered ${response.status}.`);
            }

            page = new DOMParser().parseFromString(await response.text(), 'text/html');
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.assign(url.href);
            }

            return;
        } finally {
            if (this.pending === pending) {
                this.element.removeAttribute('aria-busy');
            }
        }

        const filters = page.getElementById(this.element.id);

        if (filters === null) {
            window.location.assign(url.href);

            return;
        }

        if (remember) {
            window.history.pushState({}, '', url.href);
        }

        this.draw(page, filters, focused);
    }

    draw(page, filters, focused) {
        const status = this.statusTarget;
        const next = filters.querySelector(`[data-${this.identifier}-target~="status"]`);
        const children = [...filters.childNodes]
            .filter((child) => child !== next)
            .map((child) => this.element.ownerDocument.importNode(child, true));

        for (const child of [...this.element.childNodes]) {
            if (child !== status) {
                child.remove();
            }
        }

        status.after(...children);

        if (this.resultsValue) {
            const current = this.element.ownerDocument.getElementById(this.resultsValue);
            const results = page.getElementById(this.resultsValue);

            if (current !== null && results !== null) {
                current.replaceWith(this.element.ownerDocument.importNode(results, true));
            }
        }

        if (page.title) {
            this.element.ownerDocument.title = page.title;
        }

        for (const button of this.submitTargets) {
            button.hidden = true;
        }

        status.textContent = next?.textContent.trim() ?? '';

        if (focused !== null) {
            const target = focused === '' ? null : this.element.ownerDocument.getElementById(focused);
            (target !== null && this.element.contains(target) ? target : this.element).focus();
        }
    }
}
