import { Controller } from '@hotwired/stimulus';

/**
 * The attribute a choice writes on the root element, as the server writes it: `data-<name>` with the value chosen, or
 * no attribute for the default, which leaves the stylesheet to the system's media queries. `null` when the input is
 * not a checked choice of a field that names an attribute.
 */
export function written(input) {
    const field = input?.closest?.('[data-attribute]');

    if (!field || input.type !== 'radio' || !input.checked) {
        return null;
    }

    const name = field.getAttribute('data-attribute');

    return {
        name: `data-${name}`,
        value: input.value === field.getAttribute('data-default') ? null : input.value,
    };
}

/*
 * A display preference applied to the page as soon as it is chosen, before the form is saved, so that the reader sees
 * what they choose. Without JavaScript, the choice applies once saved.
 */
export default class extends Controller {
    apply(event) {
        const attribute = written(event.target);

        if (attribute === null) {
            return;
        }

        const root = this.element.ownerDocument.documentElement;

        if (attribute.value === null) {
            root.removeAttribute(attribute.name);
        } else {
            root.setAttribute(attribute.name, attribute.value);
        }
    }
}
