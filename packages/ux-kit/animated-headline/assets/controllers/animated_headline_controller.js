import { Controller } from '@hotwired/stimulus';

/**
 * The longest run, in milliseconds, of motion that starts by itself without a way to pause it: WCAG 2.2.2 (Pause,
 * Stop, Hide) asks for one beyond five seconds, so a headline played through once ends within this budget.
 */
export const BUDGET = 4800;

/**
 * Reveals the words of a headline through a clip that closes to a caret and opens on the next word.
 *
 * One word is revealed once, then stands still. Several words are played through once, back to the word the server
 * marked as current, the whole run fitted within `BUDGET`; with `loop`, they keep cycling, and the template renders a
 * pause/play button, hidden until the controller shows and drives it. The cycle also holds while the headline is
 * hovered or holds the focus, and while the document is hidden; a held headline shows its current word fully open.
 *
 * Under prefers-reduced-motion, nothing moves: the current word stays, and the controller stops if the reader turns
 * the preference on while the page is open. The words are hidden from screen readers by the template, which gives them
 * the whole headline at once, so nothing is announced as it changes.
 *
 * Each move of the clip ends on `transitionend`, or after `duration` if the event never comes (an interrupted
 * transition, a hidden tab), so the clip is never left closed.
 *
 * The state lives in classes the stylesheet reads: `isRevealing` on the clip while the controller drives it,
 * `isPulsing` while a word rests between two moves, `isCurrent` on the word shown; the clip's width is
 * `--phil-reveal-size`.
 *
 * @value pause Milliseconds a word rests before the clip closes on it.
 * @value duration Milliseconds the clip takes to close, and as many to open.
 * @value loop Whether the words keep cycling, with a pause/play button, instead of playing through once.
 * @value pauseLabel The button's text while the words cycle.
 * @value playLabel The button's text while the reader has paused them.
 * @target reveal The clip around the stacked words.
 * @target word One of the words, in the order they are shown.
 * @target toggle The pause/play button, rendered hidden with `loop`.
 */
export default class extends Controller {
    static values = {
        pause: { type: Number, default: 2500 },
        duration: { type: Number, default: 600 },
        loop: { type: Boolean, default: false },
        pauseLabel: { type: String, default: 'Pause animation' },
        playLabel: { type: String, default: 'Play animation' },
    };

    static targets = ['reveal', 'word', 'toggle'];

    connect() {
        this.home = Math.max(0, this.wordTargets.findIndex((word) => word.classList.contains('isCurrent')));
        this.index = this.home;
        this.timer = null;
        this.ending = null;
        this.running = false;
        this.opened = false;
        this.reasons = new Set(document.hidden ? ['hidden'] : []);
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.onMotionChange = () => (this.motion.matches ? this.stop() : this.start());
        this.onResize = () => this.running && this.opened && this.size(this.width(this.index));
        this.onVisibility = () => this.hold('hidden', document.hidden);
        this.onEnter = () => this.hold('hover', true);
        this.onLeave = () => this.hold('hover', false);
        this.onFocusIn = () => this.hold('focus', true);
        this.onFocusOut = (event) => this.hold('focus', this.element.contains(event.relatedTarget ?? null));
        this.motion.addEventListener('change', this.onMotionChange);
        window.addEventListener('resize', this.onResize);
        document.addEventListener('visibilitychange', this.onVisibility);
        this.element.addEventListener('mouseenter', this.onEnter);
        this.element.addEventListener('mouseleave', this.onLeave);
        this.element.addEventListener('focusin', this.onFocusIn);
        this.element.addEventListener('focusout', this.onFocusOut);

        if (!this.motion.matches) {
            this.start();
        }
    }

    disconnect() {
        this.stop();
        // Back to the server's word, so that a reconnection, or a snapshot of the page, starts from it.
        if (this.wordTargets.length > 0) {
            this.show(this.home);
        }

        this.motion.removeEventListener('change', this.onMotionChange);
        window.removeEventListener('resize', this.onResize);
        document.removeEventListener('visibilitychange', this.onVisibility);
        this.element.removeEventListener('mouseenter', this.onEnter);
        this.element.removeEventListener('mouseleave', this.onLeave);
        this.element.removeEventListener('focusin', this.onFocusIn);
        this.element.removeEventListener('focusout', this.onFocusOut);
    }

    /**
     * The button's action: pauses the cycle, or plays it again at once, even under the pointer or the focus that
     * pressed it.
     */
    toggle() {
        if (this.reasons.has('user')) {
            this.reasons.delete('hover');
            this.reasons.delete('focus');
            this.hold('user', false);
        } else {
            this.hold('user', true);
        }

        this.label();
    }

    start() {
        if (!this.hasRevealTarget || 0 === this.wordTargets.length) {
            return;
        }

        const count = this.wordTargets.length;
        const cycles = this.loopValue && count > 1;
        const { pause, duration } = timing(count, this.pauseValue, this.durationValue, cycles);
        this.restTime = pause;
        this.moveTime = duration;
        this.moves = count < 2 ? 0 : cycles ? Infinity : count;
        this.running = true;

        if (cycles && this.hasToggleTarget) {
            this.toggleTarget.hidden = false;
            this.label();
        }

        this.revealTarget.style.setProperty('--phil-reveal-duration', `${this.moveTime}ms`);
        this.revealTarget.classList.add('isRevealing');
        this.show(this.home);

        if (this.reasons.size > 0) {
            this.openFully();

            return;
        }

        this.size(0);
        // Reading the width commits the closed clip, so that the next width is a transition, not a jump.
        void this.revealTarget.offsetWidth;
        this.open();
    }

    stop() {
        this.cancel();
        this.running = false;
        this.opened = false;

        if (this.hasToggleTarget) {
            this.toggleTarget.hidden = true;
        }

        if (this.hasRevealTarget) {
            this.revealTarget.classList.remove('isRevealing', 'isPulsing');
            this.revealTarget.style.removeProperty('--phil-reveal-size');
            this.revealTarget.style.removeProperty('--phil-reveal-duration');
        }
    }

    /**
     * Adds or removes one reason to hold the current word still; the cycle goes on once none is left.
     */
    hold(reason, on) {
        const held = this.reasons.size > 0;
        on ? this.reasons.add(reason) : this.reasons.delete(reason);

        if (!this.running || held === this.reasons.size > 0) {
            return;
        }

        if (held) {
            this.rest();
        } else {
            this.openFully();
        }
    }

    open() {
        this.size(this.width(this.index));
        this.afterMove(() => this.rest());
    }

    /**
     * The current word fully open and nothing scheduled: what a held headline shows.
     */
    openFully() {
        this.cancel();
        this.revealTarget.classList.remove('isPulsing');
        this.size(this.width(this.index));
        this.opened = true;
    }

    rest() {
        this.opened = true;

        if (this.moves <= 0) {
            this.stop();

            return;
        }

        this.revealTarget.classList.add('isPulsing');
        this.later(this.restTime, () => this.close());
    }

    close() {
        this.opened = false;
        this.revealTarget.classList.remove('isPulsing');
        this.size(0);
        this.afterMove(() => {
            this.moves -= 1;
            this.show(next(this.index, this.wordTargets.length));
            this.open();
        });
    }

    show(index) {
        this.wordTargets.forEach((word, position) => word.classList.toggle('isCurrent', position === index));
        this.index = index;
    }

    size(pixels) {
        this.revealTarget.style.setProperty('--phil-reveal-size', 0 === pixels ? '0px' : `calc(${pixels}px + var(--phil-reveal-caret, 0px))`);
    }

    width(index) {
        return this.wordTargets[index].offsetWidth;
    }

    label() {
        if (this.hasToggleTarget) {
            this.toggleTarget.textContent = this.reasons.has('user') ? this.playLabelValue : this.pauseLabelValue;
        }
    }

    /**
     * Calls back once the clip's transition ends, or after the move's duration if `transitionend` never fires.
     */
    afterMove(callback) {
        this.cancel();
        const reveal = this.revealTarget;
        const done = () => {
            this.cancel();
            callback();
        };
        const onEnd = (event) => event.target === reveal && done();
        reveal.addEventListener('transitionend', onEnd);
        this.ending = () => reveal.removeEventListener('transitionend', onEnd);
        this.timer = setTimeout(done, this.moveTime);
    }

    later(delay, callback) {
        this.cancel();
        this.timer = setTimeout(callback, delay);
    }

    cancel() {
        clearTimeout(this.timer);
        this.timer = null;
        this.ending?.();
        this.ending = null;
    }
}

/**
 * The word after `index` among `count`, back to the first after the last; null when there is none to show.
 */
export function next(index, count) {
    return count < 2 ? null : (index + 1) % count;
}

/**
 * The rest and move times of a run: as given when it cycles, its pause button at hand; otherwise both scaled down so
 * that the entrance, then a rest, a close and an open per word back to the first, fit within `budget`.
 */
export function timing(count, pause, duration, cycles, budget = BUDGET) {
    if (cycles) {
        return { pause, duration };
    }

    const moves = count < 2 ? 0 : count;
    const total = (1 + 2 * moves) * duration + moves * pause;
    const scale = total > budget ? budget / total : 1;

    return { pause: Math.floor(pause * scale), duration: Math.floor(duration * scale) };
}
