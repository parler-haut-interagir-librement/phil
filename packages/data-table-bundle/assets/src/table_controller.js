import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/**
 * What a re-render of the table would otherwise lose: where the keyboard was. The control that had the focus gets it
 * back, by its `data-phil-focus` key; a control the server asks to focus, `autofocus`, goes first. Escape leaves the
 * cell being edited.
 *
 * The question of an action comes as a dialog drawn open, which a browser without JavaScript shows in the page; the
 * controller makes it modal, so that the page is inert behind it. Escape answers it as Cancel does, and once it is
 * gone the focus goes back to the button that asked.
 */
export default class extends Controller {
    static targets = ['cancel', 'dialog', 'dismiss'];

    async connect() {
        this.returnTo = null;
        this.remember = () => {
            this.focused = this.focusKey();
        };
        this.restore = () => {
            this.showDialog();
            this.focus();
        };
        this.showDialog();
        this.component = await getComponent(this.element);
        this.component.on('render:started', this.remember);
        this.component.on('render:finished', this.restore);
    }

    disconnect() {
        this.component?.off('render:started', this.remember);
        this.component?.off('render:finished', this.restore);
    }

    focusKey() {
        const active = this.element.ownerDocument.activeElement;

        return active?.closest?.('[data-phil-focus]')?.dataset.philFocus ?? null;
    }

    /**
     * The dialog of a question, drawn open, made modal; the control that asked is remembered for when it is gone.
     */
    showDialog() {
        if (!this.hasDialogTarget) {
            return;
        }

        const dialog = this.dialogTarget;

        if (dialog.open && typeof dialog.showModal === 'function' && !dialog.matches(':modal')) {
            this.returnTo = this.returnTo ?? this.focused ?? null;
            dialog.close();
            dialog.showModal();
        }
    }

    focus() {
        const asked = this.element.querySelector('[autofocus]');
        const key = (this.hasDialogTarget ? null : this.returnTo) ?? this.focused;
        const target = asked ?? (key ? this.element.querySelector(`[data-phil-focus="${CSS.escape(key)}"]`) : null);

        if (!this.hasDialogTarget) {
            this.returnTo = null;
        }

        target?.focus();
    }

    cancel(event) {
        event.preventDefault();

        if (this.hasCancelTarget) {
            this.cancelTarget.click();
        }
    }

    /**
     * Escape on the dialog: the question is dismissed as Cancel dismisses it, through the component.
     */
    dismiss(event) {
        event.preventDefault();

        if (this.hasDismissTarget) {
            this.dismissTarget.click();
        }
    }
}
