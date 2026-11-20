import { Controller } from '@hotwired/stimulus';

/**
 * Chooses a product of a group: one selector per axis — radio buttons or a `<select>`, each marked `data-axis` with the
 * code of its axis —, and the members of the group, as `ProductGroups::page()` gives them, in the `members` value.
 *
 * Each value that no member sells with the values chosen on the other axes is disabled: a colour not made in the size
 * chosen cannot be picked. Once every axis has a value, the SKU of that member goes into the `sku` target, each
 * `mockup` target — an image marked `data-face` — shows the member's mockup of its face under the `images` base URL,
 * and the `unavailable` target is hidden; a combination nobody sells empties the SKU and shows it. Every change
 * dispatches `change`, its detail giving the SKU, or `null`, and the values chosen.
 *
 * It changes properties and the `hidden` and `disabled` attributes only, never a `style` attribute, so the page keeps
 * the default content security policy.
 */
export default class extends Controller {
    static targets = ['sku', 'mockup', 'unavailable'];
    static values = { members: Array, language: String, images: String };

    connect() {
        this.update();
    }

    update() {
        const chosen = this.chosen();
        const members = this.membersValue.filter((member) => (member.language ?? '') === this.languageValue);

        for (const selector of this.selectors()) {
            const axis = selector.dataset.axis;
            const options = selector.tagName === 'SELECT' ? Array.from(selector.options) : [selector];

            for (const option of options) {
                if (option.value !== '') {
                    option.disabled = !members.some((member) =>
                        this.matches(member, { ...chosen, [axis]: option.value }),
                    );
                }
            }
        }

        const complete = this.axes().every((axis) => chosen[axis] !== undefined);
        const member = complete ? members.find((candidate) => this.matches(candidate, chosen)) : undefined;

        this.show(member, complete);
        this.dispatch('change', { detail: { sku: member?.sku ?? null, values: chosen } });
    }

    show(member, complete) {
        if (this.hasSkuTarget) {
            this.skuTarget.value = member?.sku ?? '';
        }

        if (this.hasUnavailableTarget) {
            this.unavailableTarget.hidden = !complete || member !== undefined;
        }

        for (const image of this.mockupTargets) {
            const path = member?.mockups?.[image.dataset.face];

            if (path) {
                image.src = this.imagesValue + path;
            }

            image.hidden = !path;
        }
    }

    matches(member, values) {
        return Object.entries(values).every(([axis, code]) => member.values[axis] === code);
    }

    chosen() {
        const chosen = {};

        for (const selector of this.selectors()) {
            const checked = selector.tagName === 'SELECT' || selector.checked;

            if (checked && selector.value !== '') {
                chosen[selector.dataset.axis] = selector.value;
            }
        }

        return chosen;
    }

    axes() {
        return [...new Set(this.selectors().map((selector) => selector.dataset.axis))];
    }

    selectors() {
        return Array.from(this.element.querySelectorAll('[data-axis]'));
    }
}
