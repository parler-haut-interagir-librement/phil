/*
 * The Stimulus controller of phil/privacy-bundle's consent, on the banner or the page of the choices:
 *
 *     <aside data-controller="phil--privacy-bundle--consent"
 *            data-phil--privacy-bundle--consent-granted-value="[]"
 *            data-phil--privacy-bundle--consent-dismiss-value="true">
 *         <form method="post" action="…" data-action="submit->phil--privacy-bundle--consent#submit">…</form>
 *     </aside>
 *
 * The forms work without it. With it, a decision is sent in the background and the person stays where they are: the
 * banner closes, the `phil--privacy-bundle--consent:changed` event gives the categories in effect, and the
 * `<template data-privacy-consent="analytics">` of a newly granted category come to life. A consent withdrawn reloads
 * the page, the only way to stop what a script has already started. When the request fails, the form is submitted the
 * ordinary way.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { granted: Array, dismiss: Boolean };

    async submit(event) {
        if (this.submittingNatively) {
            return;
        }

        event.preventDefault();
        const form = event.target;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form, event.submitter ?? undefined),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`The consent was refused with status ${response.status}.`);
            }

            this.apply((await response.json()).granted);
        } catch {
            this.submittingNatively = true;
            form.requestSubmit(event.submitter ?? undefined);
        }
    }

    apply(granted) {
        const withdrawn = this.grantedValue.some((category) => !granted.includes(category));

        this.grantedValue = granted;
        this.dispatch('changed', { detail: { granted } });

        if (withdrawn) {
            window.location.reload();

            return;
        }

        this.activate(granted);

        if (this.dismissValue) {
            this.element.hidden = true;
        }
    }

    activate(granted) {
        for (const template of document.querySelectorAll('template[data-privacy-consent]')) {
            if (granted.includes(template.dataset.privacyConsent)) {
                template.replaceWith(document.importNode(template.content, true));
            }
        }
    }
}
