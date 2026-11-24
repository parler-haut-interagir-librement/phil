import { Controller } from '@hotwired/stimulus';

/*
 * The choice of the colour scheme: it checks the stored choice, shows the group, and writes each new choice in the
 * storage of the browser and on the root, `data-theme`, which phil-ux reads; the system's removes both. A choice made
 * in another tab follows here.
 */
export const THEMES = ['light', 'dark'];

/** The stored scheme, `light` or `dark`, else `system`; `system` too where the storage cannot be read. */
export function stored(key) {
    try {
        const theme = globalThis.localStorage.getItem(key);

        return THEMES.includes(theme) ? theme : 'system';
    } catch {
        return 'system';
    }
}

/** Writes the scheme on the root, or takes it away for the system's. */
export function apply(theme) {
    const dataset = document.documentElement.dataset;

    if (THEMES.includes(theme)) {
        dataset.theme = theme;
    } else {
        delete dataset.theme;
    }
}

export default class extends Controller {
    static targets = ['option'];

    static values = { key: { type: String, default: 'theme' } };

    #follow = (event) => {
        if (event.key === this.keyValue) {
            this.#show(stored(this.keyValue));
        }
    };

    connect() {
        this.#show(stored(this.keyValue));
        this.element.hidden = false;
        window.addEventListener('storage', this.#follow);
    }

    disconnect() {
        window.removeEventListener('storage', this.#follow);
    }

    choose(event) {
        const theme = THEMES.includes(event.target.value) ? event.target.value : 'system';

        try {
            if (theme === 'system') {
                globalThis.localStorage.removeItem(this.keyValue);
            } else {
                globalThis.localStorage.setItem(this.keyValue, theme);
            }
        } catch {
            // The storage is refused: the choice holds for this page alone.
        }

        apply(theme);
        this.dispatch('change', { detail: { theme } });
    }

    #show(theme) {
        apply(theme);

        for (const option of this.optionTargets) {
            option.checked = option.value === theme;
        }
    }
}
