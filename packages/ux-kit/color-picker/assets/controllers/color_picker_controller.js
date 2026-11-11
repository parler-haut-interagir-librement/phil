import { Controller } from '@hotwired/stimulus';

/** A colour as `#rrggbb`, from three or six hexadecimal digits with or without `#`; null for anything else. */
export function normalize(text) {
    const digits = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(text.trim())?.[1].toLowerCase();

    if (digits === undefined) {
        return null;
    }

    return `#${digits.length === 3 ? [...digits].map((digit) => digit + digit).join('') : digits}`;
}

/*
 * Keeps the colour well, its text and its swatches in step. The well stays the field of the form; the text refuses
 * what is not a colour, and says so with `aria-invalid`.
 */
export default class extends Controller {
    static targets = ['well', 'text', 'swatch'];

    connect() {
        this.textTarget.hidden = false;

        for (const swatch of this.swatchTargets) {
            swatch.hidden = false;
            swatch.style.backgroundColor = normalize(this.#colour(swatch)) ?? 'transparent';
        }

        this.#mark();
    }

    pick() {
        this.textTarget.value = this.wellTarget.value;
        this.textTarget.removeAttribute('aria-invalid');
        this.#mark();
    }

    type() {
        const colour = normalize(this.textTarget.value);

        if (colour === null) {
            this.textTarget.setAttribute('aria-invalid', 'true');
        } else {
            this.#set(colour);
        }
    }

    choose(event) {
        const colour = normalize(event.params.color);

        if (colour !== null) {
            this.#set(colour);
        }
    }

    #set(colour) {
        this.wellTarget.value = colour;
        this.wellTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.wellTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    #mark() {
        for (const swatch of this.swatchTargets) {
            swatch.setAttribute('aria-pressed', String(normalize(this.#colour(swatch)) === this.wellTarget.value));
        }
    }

    #colour(swatch) {
        return swatch.getAttribute(`data-${this.identifier}-color-param`) ?? '';
    }
}
