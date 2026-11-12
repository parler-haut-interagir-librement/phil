import { Controller } from '@hotwired/stimulus';
import { fillForm } from './lib/csrf.js';

/**
 * The explorer: switches between the grid and the list without reloading, remembers the choice in the browser, asks
 * before a file is deleted, and gives each form a new CSRF token as it is sent. Without JavaScript, the links and forms
 * do the same through the server, the forms checked by their origin alone.
 */
export default class extends Controller {
    static targets = ['items'];
    static values = { view: String };

    initialize() {
        this.token = (event) => fillForm(event.target, this.element.ownerDocument);
    }

    connect() {
        this.element.addEventListener('submit', this.token, true);

        try {
            const stored = window.localStorage.getItem('phil-media-view');

            if (('grid' === stored || 'list' === stored) && stored !== this.viewValue) {
                this.apply(stored);
            }
        } catch {
            // Storage refused: the server's view stays.
        }
    }

    disconnect() {
        this.element.removeEventListener('submit', this.token, true);
    }

    show(event) {
        event.preventDefault();
        this.apply(event.currentTarget.dataset.view);

        try {
            window.localStorage.setItem('phil-media-view', this.viewValue);
        } catch {
            // Storage refused: the choice lasts for this page only.
        }
    }

    confirm(event) {
        if (!window.confirm(event.currentTarget.dataset.confirm)) {
            event.preventDefault();
        }
    }

    apply(view) {
        this.viewValue = view;

        if (this.hasItemsTarget) {
            this.itemsTarget.classList.toggle('phil-media-grid', 'grid' === view);
            this.itemsTarget.classList.toggle('phil-media-list', 'list' === view);
        }

        this.element.querySelectorAll('[data-view]').forEach((link) => {
            link.toggleAttribute('aria-current', link.dataset.view === view);
        });
        this.element.querySelectorAll('input[name="view"]').forEach((input) => {
            input.value = view;
        });
    }
}
