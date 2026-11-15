/*
 * The Stimulus controller of phil/lti-bundle that posts the form of a launch, or of a deep linking response, to the
 * other side as soon as it connects: the button of the form stays for a reader without scripts, and is disabled once
 * the form leaves, so that it is not posted twice.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const button = this.element.querySelector('[type="submit"]');

        if (button) {
            button.disabled = true;
        }

        this.element.submit();
    }
}
