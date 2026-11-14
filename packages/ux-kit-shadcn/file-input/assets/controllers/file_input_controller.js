import { Controller } from '@hotwired/stimulus';

/** Whether a file is of the types the attribute `accept` writes: a `.extension`, a `type/*` or a whole media type. */
export function accepted(file, accept) {
    if (accept === '') {
        return true;
    }

    const name = file.name.toLowerCase();
    const type = file.type.toLowerCase();

    return accept
        .toLowerCase()
        .split(',')
        .map((token) => token.trim())
        .some((token) => {
            if (token.startsWith('.')) {
                return name.endsWith(token);
            }

            return token.endsWith('/*') ? type.startsWith(token.slice(0, -1)) : type === token;
        });
}

/** A size in the unit that suits it, in the language of the page. */
export function sizeText(bytes, locale) {
    const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte'];
    const power = Math.min(units.length - 1, Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)));

    return new Intl.NumberFormat(locale, { style: 'unit', unit: units[power], maximumFractionDigits: 1 }).format(
        bytes / 1024 ** power,
    );
}

/*
 * The list of what a file field holds, and the drop on its zone. Whatever comes in, by the dialog or by a drop, goes
 * through the same sieve: the types, the size and the number the field takes.
 */
export default class extends Controller {
    static targets = ['zone', 'input', 'list', 'error', 'clear'];

    static classes = ['item', 'dragging'];

    static values = {
        maxSize: { type: Number, default: 0 },
        rejectedLabel: String,
    };

    over(event) {
        event.preventDefault();

        if (!this.inputTarget.disabled) {
            this.zoneTarget.classList.add(...this.draggingClasses);
        }
    }

    leave() {
        this.zoneTarget.classList.remove(...this.draggingClasses);
    }

    drop(event) {
        event.preventDefault();
        this.leave();

        if (this.inputTarget.disabled) {
            return;
        }

        this.inputTarget.files = this.#transfer([...event.dataTransfer.files]);
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    list() {
        const input = this.inputTarget;
        const given = [...input.files];
        const fitting = given.filter(
            (file) => accepted(file, input.accept) && (this.maxSizeValue === 0 || file.size <= this.maxSizeValue),
        );
        const kept = input.multiple ? fitting : fitting.slice(0, 1);

        if (kept.length !== given.length) {
            input.files = this.#transfer(kept);
        }

        this.errorTarget.textContent =
            kept.length === given.length
                ? ''
                : this.rejectedLabelValue.replace('{count}', String(given.length - kept.length));
        this.#show(kept);
    }

    clear() {
        this.inputTarget.value = '';
        this.errorTarget.textContent = '';
        this.#show([]);
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.inputTarget.focus();
    }

    #transfer(files) {
        const transfer = new DataTransfer();

        for (const file of files) {
            transfer.items.add(file);
        }

        return transfer.files;
    }

    #show(files) {
        const locale = document.documentElement.lang || undefined;

        this.listTarget.replaceChildren(
            ...files.map((file) => {
                const item = document.createElement('li');
                item.classList.add(...this.itemClasses);
                item.textContent = `${file.name} (${sizeText(file.size, locale)})`;

                return item;
            }),
        );
        this.clearTarget.hidden = files.length === 0;
    }
}
