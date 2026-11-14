import { Controller } from '@hotwired/stimulus';

/** Whether a click fell outside a box, on the backdrop of a modal dialog. */
export function isOutside({ clientX, clientY }, { top, right, bottom, left }) {
    return clientX < left || clientX > right || clientY < top || clientY > bottom;
}

/** Whether the browser runs the `command` and `commandfor` of a button itself: the invokers of HTML. */
export function hasInvokers() {
    return typeof HTMLButtonElement !== 'undefined' && 'commandForElement' in HTMLButtonElement.prototype;
}

/** Whether the browser closes a dialog on a click on its backdrop itself, as `closedby="any"` asks. */
export function hasLightDismiss() {
    return typeof HTMLDialogElement !== 'undefined' && 'closedBy' in HTMLDialogElement.prototype;
}

/*
 * The fallback of a lightbox. Its buttons carry `command` and `commandfor` and its dialog `closedby="any"`: where the
 * browser runs them, the controller does nothing. Where it does not, the controller opens the dialog as a modal from
 * the thumbnail, closes it from its button, and on a click on its backdrop.
 */
export default class extends Controller {
    static targets = ['dialog'];

    open() {
        if (!hasInvokers() && !this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    close() {
        if (!hasInvokers() && this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    closeOnClickOutside(event) {
        if (!hasLightDismiss() && event.target === this.dialogTarget && isOutside(event, this.dialogTarget.getBoundingClientRect())) {
            this.dialogTarget.close();
        }
    }
}
