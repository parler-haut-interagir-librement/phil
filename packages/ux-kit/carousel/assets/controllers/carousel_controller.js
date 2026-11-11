import { Controller } from '@hotwired/stimulus';

/** The direction a key moves in, along the axis of the carousel and the direction of the page; 0 for any other key. */
export function direction(key, { vertical = false, rtl = false } = {}) {
    if (vertical) {
        return { ArrowDown: 1, ArrowUp: -1 }[key] ?? 0;
    }

    return { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[key] ?? 0;
}

/** Where a scroll stands: at the start, at the end, and on which of `count` slides of `size` pixels. */
export function standing(position, max, size, count) {
    const distance = Math.abs(position);

    return {
        atStart: distance <= 1,
        atEnd: distance >= max - 1,
        index: size > 0 ? Math.min(count - 1, Math.max(0, Math.round(distance / size))) : 0,
    };
}

/*
 * The buttons, the arrows and the automatic play of a carousel whose slides are scrolled and snapped by the browser.
 * Without JavaScript the buttons stay hidden and the slides still scroll. Automatic play holds while the carousel is
 * hovered or holds the focus and while the page is hidden, and never runs under `prefers-reduced-motion`.
 */
export default class extends Controller {
    static targets = ['viewport', 'previous', 'next', 'toggle'];

    static values = {
        orientation: { type: String, default: 'horizontal' },
        options: { type: Object, default: {} },
        autoplay: { type: Number, default: 0 },
        pauseLabel: { type: String, default: '' },
        playLabel: { type: String, default: '' },
    };

    #timer = null;
    #holds = new Set();
    #paused = false;
    #index = null;

    connect() {
        for (const button of [...this.previousTargets, ...this.nextTargets]) {
            button.hidden = false;
        }

        this.update();

        if (this.#canPlay) {
            for (const toggle of this.toggleTargets) {
                toggle.hidden = false;
            }

            this.#play();
        }
    }

    disconnect() {
        this.#stop();
    }

    scrollPrev() {
        this.#step(-1, this.#loop);
    }

    scrollNext() {
        this.#step(1, this.#loop);
    }

    handleKeydown(event) {
        const moved = direction(event.key, { vertical: this.#vertical, rtl: this.#rtl });

        if (moved !== 0) {
            event.preventDefault();
            this.#step(moved, this.#loop);
        }
    }

    update() {
        const { atStart, atEnd, index } = this.#standing;

        for (const button of this.previousTargets) {
            button.disabled = atStart && !this.#loop;
        }

        for (const button of this.nextTargets) {
            button.disabled = atEnd && !this.#loop;
        }

        if (index !== this.#index) {
            this.#index = index;
            this.dispatch('select', { detail: { index, count: this.#slides.length } });
        }
    }

    hold(event) {
        this.#holds.add(event.type === 'mouseenter' ? 'pointer' : 'focus');
        this.#stop();
    }

    release(event) {
        if (event.type === 'focusout' && this.element.contains(event.relatedTarget)) {
            return;
        }

        this.#holds.delete(event.type === 'mouseleave' ? 'pointer' : 'focus');
        this.#play();
    }

    toggleAutoplay() {
        this.#paused = !this.#paused;

        for (const toggle of this.toggleTargets) {
            toggle.textContent = this.#paused ? this.playLabelValue : this.pauseLabelValue;
        }

        this.#paused ? this.#stop() : this.#play();
    }

    get #vertical() {
        return this.orientationValue === 'vertical';
    }

    get #rtl() {
        return getComputedStyle(this.element).direction === 'rtl';
    }

    get #loop() {
        return Boolean(this.optionsValue.loop);
    }

    get #reduced() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    get #canPlay() {
        return this.autoplayValue > 0 && !this.#reduced;
    }

    get #slides() {
        return [...(this.viewportTarget.firstElementChild?.children ?? [])];
    }

    get #slideSize() {
        const [first, second] = this.#slides;
        const distance =
            first && second
                ? Math.abs(this.#vertical ? second.offsetTop - first.offsetTop : second.offsetLeft - first.offsetLeft)
                : 0;

        return distance > 0
            ? distance
            : this.#vertical
              ? this.viewportTarget.clientHeight
              : this.viewportTarget.clientWidth;
    }

    get #standing() {
        const viewport = this.viewportTarget;

        return this.#vertical
            ? standing(
                  viewport.scrollTop,
                  viewport.scrollHeight - viewport.clientHeight,
                  this.#slideSize,
                  this.#slides.length,
              )
            : standing(
                  viewport.scrollLeft,
                  viewport.scrollWidth - viewport.clientWidth,
                  this.#slideSize,
                  this.#slides.length,
              );
    }

    #step(moved, wrap) {
        const viewport = this.viewportTarget;
        const { atStart, atEnd } = this.#standing;
        const behavior = this.#reduced ? 'auto' : 'smooth';
        const sign = !this.#vertical && this.#rtl ? -1 : 1;

        if (wrap && moved > 0 && atEnd) {
            viewport.scrollTo({ top: 0, left: 0, behavior });
        } else if (wrap && moved < 0 && atStart) {
            viewport.scrollTo(
                this.#vertical
                    ? { top: viewport.scrollHeight, behavior }
                    : { left: sign * viewport.scrollWidth, behavior },
            );
        } else {
            const distance = moved * this.#slideSize;
            viewport.scrollBy(this.#vertical ? { top: distance, behavior } : { left: sign * distance, behavior });
        }
    }

    #play() {
        this.#stop();

        if (this.#canPlay && !this.#paused && this.#holds.size === 0) {
            this.#timer = setInterval(() => {
                if (!document.hidden) {
                    this.#step(1, true);
                }
            }, this.autoplayValue);
        }
    }

    #stop() {
        clearInterval(this.#timer);
        this.#timer = null;
    }
}
