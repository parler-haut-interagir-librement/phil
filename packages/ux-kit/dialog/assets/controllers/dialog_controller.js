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

/** Whether a click came from a button the browser runs itself. */
const invokedNatively = (event) => hasInvokers() && Boolean(event?.currentTarget?.hasAttribute?.('commandfor'));

/*
 * A modal on the native dialog element, the browser making the page inert, trapping the focus, closing on Escape and
 * giving the focus back. HTML first: the trigger and the close buttons carry `command` and `commandfor`, the dialog
 * `closedby="any"`, and where the browser runs them the controller leaves them to it. It stands in where the browser
 * does not, opening and closing from those buttons and on a click on the backdrop; everywhere, it opens the dialog
 * from its value, says on the trigger whether it is open, and reopens it when Turbo or a Live Component puts it back
 * in the page.
 */
export default class extends Controller {
    static targets = ['trigger', 'dialog'];

    static values = { open: { type: Boolean, default: false } };

    #wasOpen = false;

    connect() {
        if (this.openValue || this.#wasOpen) {
            this.open();
        }

        this.#wasOpen = false;
    }

    disconnect() {
        // A dialog taken out of the page comes back open but no longer modal.
        this.#wasOpen = this.dialogTarget.open;

        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    open(event) {
        if (!this.dialogTarget.open && !invokedNatively(event)) {
            this.dialogTarget.showModal();
        }

        this.#expanded(true);
    }

    close(event) {
        if (this.dialogTarget.open && !invokedNatively(event)) {
            this.dialogTarget.close();
        }

        this.#expanded(false);
    }

    closed() {
        this.#expanded(false);
    }

    closeOnClickOutside(event) {
        if (hasLightDismiss()) {
            return;
        }

        if (event.target === this.dialogTarget && isOutside(event, this.dialogTarget.getBoundingClientRect())) {
            this.close();
        }
    }

    #expanded(open) {
        if (this.hasTriggerTarget) {
            this.triggerTarget.setAttribute('aria-expanded', String(open));
        }
    }
}
