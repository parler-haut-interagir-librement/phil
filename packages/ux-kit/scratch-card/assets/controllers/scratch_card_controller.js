import { Controller } from '@hotwired/stimulus';

/** The radius of what a stroke scratches away, in pixels. */
export const RADIUS = 18;

/** The grid the share scratched away is counted on: so many columns by so many rows of the cover. */
export const COLUMNS = 12;
export const ROWS = 8;

/** The share of the cover that reveals the prize when `threshold` is no number, and its bounds, in percent. */
export const THRESHOLD = 50;
export const LOWEST = 10;
export const HIGHEST = 90;

/** How long the rest of the cover takes to fade once the prize is revealed, in milliseconds. */
export const FADE = 300;

/** What the controller sets on its canvas through the CSSOM: over the prize, scratched by a finger without scrolling. */
export const STAGE = { position: 'absolute', inset: '0', width: '100%', height: '100%', 'touch-action': 'none', cursor: 'crosshair' };

/** The DOM event that plays the `Celebration` of the page. */
export const CELEBRATE = 'celebrate';

/** The share of the cover, from 0.1 to 0.9, that reveals the prize, from a threshold in percent. */
export function share(threshold) {
    const percent = Number(threshold);

    return (Number.isFinite(percent) ? Math.min(HIGHEST, Math.max(LOWEST, percent)) : THRESHOLD) / 100;
}

/**
 * The cells of the grid of a cover of `width` by `height` whose centre lies under a stroke at `x`, `y`, in pixels of
 * the cover, each by its index, row after row; none outside the cover.
 */
export function cellsUnder(x, y, radius, width, height, columns = COLUMNS, rows = ROWS) {
    const cells = [];

    if (!(width > 0 && height > 0) || !Number.isFinite(x) || !Number.isFinite(y)) {
        return cells;
    }

    const cellWidth = width / columns;
    const cellHeight = height / rows;

    for (let row = 0; row < rows; row++) {
        for (let column = 0; column < columns; column++) {
            const dx = (column + 0.5) * cellWidth - x;
            const dy = (row + 0.5) * cellHeight - y;

            if (dx * dx + dy * dy <= radius * radius) {
                cells.push(row * columns + column);
            }
        }
    }

    return cells;
}

/** A text as a screen reader says it: its runs of white space as one space, trimmed. */
export function spoken(text) {
    return String(text ?? '')
        .replace(/\s+/g, ' ')
        .trim();
}

/*
 * A scratch card: a cover painted on a canvas over the prize, in the colour of the token `color`, the prize hidden from
 * screen readers and the keyboard while it is covered. A pointer pressed and moved scratches the cover away; once the
 * share `threshold` of its grid is scratched, or by the action `reveal` of its button, the prize is revealed: the rest
 * of the cover fades, at once where motion is reduced, the prize is said in the polite live region, `message` or its own
 * text, the focus goes to the prize when it was on the button, the event `scratch-card:revealed` is dispatched, and with
 * an `effect`, the `Celebration` of the page plays it.
 *
 * @value message What is said once the prize is revealed; its text when empty.
 * @value threshold The share of the cover, in percent, that reveals the prize once scratched away.
 * @value effect The effect the `Celebration` of the page plays then; none when empty.
 * @value color The custom property the cover is painted in.
 * @target prize What the cover hides.
 * @target stage The template of the canvas.
 * @target button The button that reveals the prize at once.
 * @target message The live region that says the prize.
 */
export default class extends Controller {
    static targets = ['prize', 'stage', 'button', 'message'];

    static values = {
        message: String,
        threshold: { type: Number, default: THRESHOLD },
        effect: String,
        color: String,
    };

    connect() {
        this.cover = null;
        this.brush = null;
        this.pressing = false;
        this.scratched = new Set();
        this.revealed = false;
        this.fading = null;

        if (!this.hasPrizeTarget) {
            return;
        }

        this.paint();
    }

    disconnect() {
        clearTimeout(this.fading);
        this.fading = null;
        this.cover?.remove();
        this.cover = null;
    }

    /** A pointer pressed on the cover: it scratches until released. */
    press(event) {
        this.pressing = true;

        try {
            this.cover?.setPointerCapture?.(event.pointerId);
        } catch {
            // A pointer the canvas cannot capture still scratches while it stays over it.
        }

        this.scratch(event);
    }

    /** A pointer moved over the cover while pressed: what lies under it is scratched away, and counted. */
    scratch(event) {
        if (!this.pressing || null === this.cover || null === this.brush || this.revealed) {
            return;
        }

        const box = this.cover.getBoundingClientRect();
        const x = event.clientX - box.left;
        const y = event.clientY - box.top;

        this.brush.globalCompositeOperation = 'destination-out';
        this.brush.beginPath();
        this.brush.arc(x, y, RADIUS, 0, Math.PI * 2);
        this.brush.fill();

        for (const cell of cellsUnder(x, y, RADIUS, box.right - box.left, box.bottom - box.top)) {
            this.scratched.add(cell);
        }

        if (this.scratched.size >= share(this.thresholdValue) * COLUMNS * ROWS) {
            this.reveal();
        }
    }

    release() {
        this.pressing = false;
    }

    /** The action of its button, and the end of the scratching: the prize is revealed, said, and may be celebrated. */
    reveal() {
        if (this.revealed || !this.hasPrizeTarget) {
            return;
        }

        this.revealed = true;
        this.pressing = false;
        this.uncover();
        this.prizeTarget.removeAttribute('aria-hidden');
        this.prizeTarget.inert = false;

        if (this.hasButtonTarget) {
            const focused = document.activeElement === this.buttonTarget;
            this.buttonTarget.hidden = true;

            if (focused) {
                this.prizeTarget.setAttribute('tabindex', '-1');
                this.prizeTarget.focus();
            }
        }

        if (this.hasMessageTarget) {
            this.messageTarget.textContent = '' !== this.messageValue ? this.messageValue : spoken(this.prizeTarget.textContent);
        }

        this.dispatch('revealed');

        if ('' !== this.effectValue) {
            // The prize is said here: the celebration draws, and says only words of its own.
            window.dispatchEvent(new CustomEvent(CELEBRATE, { detail: { effect: this.effectValue } }));
        }
    }

    /** Whether motion is reduced, by the system or by `data-motion="reduce"` on the element or an ancestor. */
    reduced() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches || null !== this.element.closest('[data-motion="reduce"]');
    }

    /** The cover painted over the prize, the prize hidden from screen readers and the keyboard, the button shown. */
    paint() {
        const model = this.hasStageTarget ? this.stageTarget.content.firstElementChild : null;
        const cover = null === model ? document.createElement('canvas') : model.cloneNode(true);
        const context = cover.getContext?.('2d') ?? null;

        if (null === context) {
            return;
        }

        cover.setAttribute('aria-hidden', 'true');

        for (const [property, value] of Object.entries(STAGE)) {
            cover.style.setProperty(property, value);
        }

        this.prizeTarget.style.setProperty('position', 'relative');
        this.prizeTarget.append(cover);

        const box = this.prizeTarget.getBoundingClientRect();
        const width = Math.max(1, box.right - box.left);
        const height = Math.max(1, box.bottom - box.top);
        const ratio = Math.min(2, window.devicePixelRatio || 1);
        cover.width = Math.round(width * ratio);
        cover.height = Math.round(height * ratio);
        context.scale(ratio, ratio);
        context.fillStyle = this.colour(cover);
        context.fillRect(0, 0, width, height);

        this.cover = cover;
        this.brush = context;
        this.prizeTarget.setAttribute('aria-hidden', 'true');
        this.prizeTarget.inert = true;

        if (this.hasButtonTarget) {
            this.buttonTarget.hidden = false;
        }
    }

    /** The rest of the cover goes: fading where motion is allowed, at once where it is reduced. */
    uncover() {
        const cover = this.cover;
        this.cover = null;
        this.brush = null;

        if (null === cover) {
            return;
        }

        if (this.reduced()) {
            cover.remove();

            return;
        }

        cover.style.setProperty('transition', `opacity ${FADE}ms ease-out`);
        cover.style.setProperty('opacity', '0');
        cover.style.setProperty('pointer-events', 'none');
        this.fading = setTimeout(() => {
            this.fading = null;
            cover.remove();
        }, FADE);
    }

    /** The colour of the token `color`, resolved on the cover through the CSSOM; grey where none resolves. */
    colour(cover) {
        if (!/^--[A-Za-z0-9_-]+$/.test(this.colorValue ?? '')) {
            return 'grey';
        }

        cover.style.setProperty('color', `var(${this.colorValue})`);
        const colour = getComputedStyle(cover).color;
        cover.style.removeProperty('color');

        return colour || 'grey';
    }
}
