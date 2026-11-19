import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/**
 * What a re-render would otherwise lose, and what a page showing a content twice needs: the control that had the focus
 * gets it back, by its `data-phil-focus` key, and the other components of the same content draw themselves again.
 */
export default class extends Controller {
    static values = { content: String, instance: String };

    async connect() {
        this.remember = () => {
            this.focused = this.focusKey();
        };
        this.restore = () => this.focus();
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

        // Only a control of this component: another one of the same content, drawn again, must not take the focus.
        if (!active || !this.element.contains(active)) {
            return null;
        }

        return active.closest?.('[data-phil-focus]')?.dataset.philFocus ?? null;
    }

    focus() {
        if (this.focused) {
            this.element.querySelector(`[data-phil-focus="${CSS.escape(this.focused)}"]`)?.focus();
        }
    }

    refresh({ detail }) {
        if (detail.content === this.contentValue && detail.instance !== this.instanceValue) {
            this.component?.render();
        }
    }
}
