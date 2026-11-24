/*
 * The Stimulus controller of phil/email-address-bundle, on the email field the bundle's EmailAddressType renders:
 *
 *     <input type="email" data-controller="phil--email-address-bundle--email-address"
 *            data-action="blur->phil--email-address-bundle--email-address#check"
 *            data-phil--email-address-bundle--email-address-url-value="/email-address/domains.json"
 *            data-phil--email-address-bundle--email-address-suggestion-value="Did you mean %address%?"
 *            data-phil--email-address-bundle--email-address-use-value="Use it"
 *            data-phil--email-address-bundle--email-address-role-value="This address reaches a shared mailbox…">
 *
 * When the person leaves the field, it reads the lists of the route — once per page, whatever the fields — and
 * writes in a live region after the field the address meant, with a button that puts it in the field, or a warning
 * about a role address. It never changes the field on its own, and the form is sent as typed. It dispatches `hint`
 * with what it found, `applied` when the person takes the suggestion, and `failed` when the lists cannot be read.
 */
import { Controller } from '@hotwired/stimulus';
import { hint } from './email_address.js';

const lists = new Map();

/**
 * The lists of a URL, read once and shared by every field of the page.
 */
export function load(url) {
    if (!lists.has(url)) {
        lists.set(
            url,
            fetch(url, { headers: { Accept: 'application/json' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`The email lists answered ${response.status}.`);
                    }

                    return response.json();
                })
                .catch((error) => {
                    lists.delete(url);
                    throw error;
                }),
        );
    }

    return lists.get(url);
}

export default class extends Controller {
    static values = { url: String, suggestion: String, use: String, role: String };

    region = null;

    connect() {
        const document = this.element.ownerDocument;
        this.region = document.createElement('p');
        this.region.className = 'phil-email-address-hint';
        this.region.setAttribute('role', 'status');
        this.region.setAttribute('aria-live', 'polite');
        this.region.id = `${this.element.id || 'email'}-hint`;
        this.element.after(this.region);
        this.element.setAttribute(
            'aria-describedby',
            [this.element.getAttribute('aria-describedby'), this.region.id].filter(Boolean).join(' '),
        );
    }

    disconnect() {
        this.region?.remove();
        this.region = null;
    }

    async check() {
        const value = this.element.value;

        try {
            const found = value.trim() === '' ? null : hint(value, await load(this.urlValue));

            if (value === this.element.value) {
                this.show(found);
            }
        } catch (error) {
            this.dispatch('failed', { detail: error });
        }
    }

    show(found) {
        if (this.region === null) {
            return;
        }

        this.region.replaceChildren();

        if (found?.kind === 'suggestion') {
            const document = this.element.ownerDocument;
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = this.useValue;
            button.addEventListener('click', () => this.apply(found.address));
            this.region.append(this.suggestionValue.replace('%address%', found.address), ' ', button);
        } else if (found?.kind === 'role') {
            this.region.append(this.roleValue);
        }

        this.dispatch('hint', { detail: found });
    }

    apply(address) {
        this.element.value = address;
        this.region?.replaceChildren();
        this.element.focus();
        this.dispatch('applied', { detail: address });
    }
}
