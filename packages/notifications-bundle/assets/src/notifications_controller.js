// The notification centre: the bell's button opens and closes the panel, its forms are sent in the background with the
// centre's CSRF token, and the recipient's Mercure topic brings the new unread count and the toasts as they come.
// Without JavaScript, the forms still post and come back to the page.
//
//     <twig:PhilNotifications:Bell />
//     <twig:PhilNotifications:Toasts />

import { Controller } from '@hotwired/stimulus';

import { dismissDelay, parseUpdate, toastElement } from './lib/updates.js';

export default class extends Controller {
    static targets = ['button', 'panel', 'count', 'countLabel'];

    static values = { config: Object };

    connect() {
        const config = this.configValue ?? {};
        const document = this.element.ownerDocument;
        this.config = config;
        this.window = document.defaultView;
        this.region = document.querySelector('[data-phil-notifications-toasts]');
        this.reducedMotion = this.window?.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        this.onSubmit = (event) => this.submit(event);
        this.onClick = (event) => this.dismiss(event);
        this.element.addEventListener('submit', this.onSubmit);
        this.region?.addEventListener('click', this.onClick);
        this.source = null;

        if ('string' === typeof config.hub && 'function' === typeof globalThis.EventSource) {
            this.source = new globalThis.EventSource(config.hub, { withCredentials: true });
            this.source.onmessage = (event) => this.receive(event.data);
        }
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit);
        this.region?.removeEventListener('click', this.onClick);
        this.source?.close();
    }

    toggle() {
        const open = 'true' !== this.buttonTarget.getAttribute('aria-expanded');
        this.buttonTarget.setAttribute('aria-expanded', String(open));
        this.panelTarget.hidden = !open;
    }

    receive(data) {
        const update = parseUpdate(data);

        if (null === update) {
            return;
        }

        this.count(update.unread);

        if (null !== update.toast && this.region) {
            const toast = toastElement(this.element.ownerDocument, update.toast, this.config.dismiss ?? 'Dismiss');
            toast.classList.toggle('phil-notifications-toast--still', this.reducedMotion);
            this.region.append(toast);
            this.window?.setTimeout(() => toast.remove(), dismissDelay(this.reducedMotion));
        }
    }

    async submit(event) {
        const form = event.target;

        if (!(form instanceof this.window.HTMLFormElement) || !form.hasAttribute('data-phil-notifications-form')) {
            return;
        }

        event.preventDefault();
        const headers = { Accept: 'application/json' };
        headers[this.config.header ?? 'X-Phil-Notifications-Token'] = this.config.token ?? '';
        const response = await globalThis.fetch(form.action, {
            method: 'POST',
            headers,
            body: new this.window.FormData(form),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const body = await response.json();
        this.count(body.unread);
        const item = form.closest('[data-notification-id]');

        if (form.action.endsWith('/archive')) {
            item?.remove();
        } else if (item) {
            item.classList.remove('phil-notifications-item--unread');
            form.remove();
        } else {
            for (const unread of this.element.querySelectorAll('.phil-notifications-item--unread')) {
                unread.classList.remove('phil-notifications-item--unread');
            }
        }
    }

    dismiss(event) {
        const button = event.target.closest?.('[data-phil-notifications-dismiss]');
        button?.closest('.phil-notifications-toast')?.remove();
    }

    count(unread) {
        if (!Number.isInteger(unread) || !this.hasCountTarget) {
            return;
        }

        this.countTarget.textContent = String(unread);
        this.countTarget.hidden = 0 === unread;
    }
}
