/*
 * The Stimulus controller of phil/saml-sp-bundle that posts the form carrying a SAML message to the identity provider
 * as soon as it connects. The button of the form stays for a reader without scripts, and is disabled once the form
 * leaves, so that the message is not posted twice.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        if (this.element.dataset.philSamlSubmitted) {
            return;
        }

        this.element.dataset.philSamlSubmitted = '1';
        const button = this.element.querySelector('[type="submit"]');

        if (button) {
            button.disabled = true;
        }

        this.element.submit();
    }
}
