import { Controller } from '@hotwired/stimulus';

/*
 * The button that takes a tag away. The event `tag:remove` can be cancelled, for a server to answer first. The focus
 * goes to the tag beside it, or to the list once the last one is gone.
 */
export default class extends Controller {
    static targets = ['remove'];

    static values = { name: String };

    connect() {
        this.removeTarget.hidden = false;
    }

    remove() {
        const event = this.dispatch('remove', { cancelable: true, detail: { name: this.nameValue } });

        if (event.defaultPrevented) {
            return;
        }

        const parent = this.element.parentElement;
        const beside = this.#button(this.element.nextElementSibling) ?? this.#button(this.element.previousElementSibling);

        this.element.remove();

        if (beside) {
            beside.focus();
        } else if (parent) {
            parent.tabIndex = -1;
            parent.focus();
        }
    }

    #button(tag) {
        return tag?.querySelector(`[data-${this.identifier}-target~="remove"]`) ?? null;
    }
}
