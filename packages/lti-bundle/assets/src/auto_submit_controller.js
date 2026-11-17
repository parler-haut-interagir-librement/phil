/*
 * The Stimulus controller of phil/lti-bundle that posts the form of a launch, or of a deep linking response, to the
 * other side as soon as it connects, for a page of the application's own whose form does not post itself: the button of
 * the form stays for a reader without scripts, and is disabled once the form leaves, so that it is not posted twice. A
 * form the inline script of the bundle's page already sent carries `data-phil-lti-submitted` and is left alone.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        if (this.element.dataset.philLtiSubmitted) {
            return;
        }

        this.element.dataset.philLtiSubmitted = '1';
        const button = this.element.querySelector('[type="submit"]');

        if (button) {
            button.disabled = true;
        }

        this.element.submit();
    }
}
