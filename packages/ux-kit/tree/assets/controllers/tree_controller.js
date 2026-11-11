import { Controller } from '@hotwired/stimulus';

/** How long the letters of a search at the keyboard add up, in milliseconds. */
export const TYPING = 500;

const ITEM = '[role="treeitem"]';

/*
 * A tree on the WAI-ARIA pattern: one stop of the Tab key, the arrows mirrored right to left, a branch opened and
 * closed in place, Home, End, a search by first letters, Enter and the Space bar choosing. `tree:select` carries the
 * value of the item chosen.
 */
export default class extends Controller {
    static targets = ['item', 'toggle', 'link'];

    #typed = '';
    #typing = null;

    connect() {
        const first = this.itemTargets.find((item) => item.getAttribute('aria-selected') === 'true') ?? this.itemTargets[0];
        this.#rove(first);
    }

    disconnect() {
        clearTimeout(this.#typing);
    }

    navigate(event) {
        const item = event.target.closest(ITEM);

        if (!item || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        if (this.#handle(event.key, item)) {
            event.preventDefault();
            event.stopPropagation();
        }
    }

    activate(event) {
        const item = event.target.closest(ITEM);

        if (!item) {
            return;
        }

        this.#focus(item);

        if (this.toggleTargets.includes(event.target)) {
            this.#flip(item);

            return;
        }

        this.#choose(item);
        this.#flip(item);
    }

    #handle(key, item) {
        const rtl = getComputedStyle(this.element).direction === 'rtl';
        const open = rtl ? 'ArrowLeft' : 'ArrowRight';
        const close = rtl ? 'ArrowRight' : 'ArrowLeft';
        const visible = this.#visible();
        const index = visible.indexOf(item);

        if (key === 'ArrowDown') {
            this.#focus(visible[index + 1]);
        } else if (key === 'ArrowUp') {
            this.#focus(visible[index - 1]);
        } else if (key === 'Home') {
            this.#focus(visible[0]);
        } else if (key === 'End') {
            this.#focus(visible[visible.length - 1]);
        } else if (key === open) {
            this.#forward(item, visible[index + 1]);
        } else if (key === close) {
            this.#back(item);
        } else if (key === 'Enter' || key === ' ') {
            this.#enter(item);
        } else if (key.length === 1) {
            this.#search(key, visible, index);
        } else {
            return false;
        }

        return true;
    }

    #forward(item, next) {
        const expanded = item.getAttribute('aria-expanded');

        if (expanded === 'false') {
            item.setAttribute('aria-expanded', 'true');
        } else if (expanded === 'true') {
            this.#focus(next);
        }
    }

    #back(item) {
        if (item.getAttribute('aria-expanded') === 'true') {
            item.setAttribute('aria-expanded', 'false');
        } else {
            this.#focus(item.parentElement.closest(ITEM));
        }
    }

    #enter(item) {
        const link = this.linkTargets.find((candidate) => candidate.closest(ITEM) === item);

        if (link) {
            link.click();
        } else {
            this.#choose(item);
            this.#flip(item);
        }
    }

    #search(key, visible, index) {
        clearTimeout(this.#typing);
        this.#typed += key.toLowerCase();
        this.#typing = setTimeout(() => {
            this.#typed = '';
        }, TYPING);

        const ordered = [...visible.slice(index + 1), ...visible.slice(0, index + 1)];
        const found = ordered.find((item) => this.#name(item).startsWith(this.#typed));
        this.#focus(found);
    }

    #name(item) {
        return item.textContent.trim().toLowerCase();
    }

    #visible() {
        return this.itemTargets.filter((item) => !item.parentElement.closest(`${ITEM}[aria-expanded="false"]`));
    }

    #flip(item) {
        const expanded = item.getAttribute('aria-expanded');

        if (expanded !== null) {
            item.setAttribute('aria-expanded', String(expanded !== 'true'));
        }
    }

    #choose(item) {
        for (const other of this.itemTargets) {
            other.setAttribute('aria-selected', String(other === item));
        }

        this.dispatch('select', { detail: { value: item.dataset.value } });
    }

    #focus(item) {
        if (item) {
            this.#rove(item);
            item.focus();
        }
    }

    #rove(current) {
        for (const item of this.itemTargets) {
            item.tabIndex = item === current ? 0 : -1;
        }
    }
}
