/*
 * The Stimulus controller of phil/onboarding-bundle that walks a user through the steps of a flow, as the bundle's
 * template `@PhilOnboarding/_tour.html.twig` renders them: one `<template>` per step, with its identifier, its state,
 * the CSS selector of its target and the URLs that mark it done or skipped; a panel that is a non-modal dialog; and a
 * status that screen readers announce.
 *
 * The tour starts at the first pending step, when the page loads unless `autostart` is false, and whenever the window
 * receives `phil-onboarding:start`. Each step is shown in the panel, which takes the focus; its target, when the page
 * has it, is highlighted and scrolled into view — at once when the reader asks for reduced motion, by the system or by
 * the `data-motion="reduce"` of the root element, smoothly otherwise.
 *
 * - Next marks a pending step done, when it is ticked by hand, then shows the next step, or ends on the last one.
 * - Previous shows the step before. Skip marks the step skipped, when it may be skipped, then moves on.
 * - End dismisses the flow for good. Escape only closes the tour for this page.
 * - The right and left arrows move between steps, when the focus is not in a field.
 *
 * The focus returns where it was when the tour closes. The changes are sent with the CSRF token in a header; one that
 * fails dispatches `failed` and the tour goes on. The controller dispatches `shown`, `closed` and `finished` too.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['step', 'panel', 'content', 'progress', 'previous', 'next', 'skip', 'status'];

    static values = {
        flow: String,
        token: String,
        header: String,
        dismissUrl: String,
        autostart: { type: Boolean, default: true },
        progress: String,
        next: String,
        finish: String,
    };

    connect() {
        this.index = this.firstPending();

        if (this.autostartValue && this.stepTargets.some((step) => step.dataset.state === 'pending')) {
            this.start();
        }
    }

    disconnect() {
        this.unhighlight();
    }

    start() {
        if (this.stepTargets.length === 0) {
            return;
        }

        this.returnFocus = globalThis.document?.activeElement ?? null;
        this.panelTarget.hidden = false;
        this.show(this.index ?? this.firstPending());
    }

    firstPending() {
        return Math.max(
            0,
            this.stepTargets.findIndex((step) => step.dataset.state === 'pending'),
        );
    }

    show(index) {
        const last = this.stepTargets.length - 1;
        this.index = Math.min(Math.max(index, 0), last);
        const step = this.stepTargets[this.index];

        this.contentTarget.replaceChildren(step.content.cloneNode(true));
        const heading = this.contentTarget.querySelector('h2, h3');
        if (heading) {
            heading.id = this.panelTarget.getAttribute('aria-labelledby');
        }

        const progress = this.progressValue
            .replace('%current%', String(this.index + 1))
            .replace('%total%', String(this.stepTargets.length));
        this.progressTarget.textContent = progress;
        this.previousTarget.disabled = this.index === 0;
        this.nextTarget.textContent = this.index === last ? this.finishValue : this.nextValue;
        this.skipTarget.hidden = !step.dataset.skipUrl;
        this.statusTarget.textContent = `${progress}: ${heading?.textContent?.trim() ?? ''}`;

        this.highlight(step.dataset.selector);
        this.panelTarget.focus({ preventScroll: true });
        this.dispatch('shown', { detail: { flow: this.flowValue, step: step.dataset.id } });
    }

    next(event) {
        event?.preventDefault();
        const step = this.stepTargets[this.index];

        if (step.dataset.doneUrl) {
            this.send(step.dataset.doneUrl);
            this.settle(step, 'completed');
        }

        this.advance();
    }

    previous(event) {
        event?.preventDefault();
        this.show(this.index - 1);
    }

    skip(event) {
        event?.preventDefault();
        const step = this.stepTargets[this.index];

        if (step.dataset.skipUrl) {
            this.send(step.dataset.skipUrl);
            this.settle(step, 'skipped');
        }

        this.advance();
    }

    end(event) {
        event?.preventDefault();
        this.send(this.dismissUrlValue);
        this.close();
    }

    close() {
        this.panelTarget.hidden = true;
        this.unhighlight();
        this.returnFocus?.focus?.({ preventScroll: true });
        this.dispatch('closed', { detail: { flow: this.flowValue } });
    }

    keydown(event) {
        if (this.panelTarget.hidden || event.defaultPrevented) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
        } else if (!this.inField(event.target) && event.key === 'ArrowRight') {
            event.preventDefault();
            this.next();
        } else if (!this.inField(event.target) && event.key === 'ArrowLeft') {
            event.preventDefault();
            this.previous();
        }
    }

    advance() {
        if (this.index < this.stepTargets.length - 1) {
            this.show(this.index + 1);

            return;
        }

        this.close();
        this.dispatch('finished', { detail: { flow: this.flowValue } });
    }

    settle(step, state) {
        step.dataset.state = state;
        step.dataset.doneUrl = '';
        step.dataset.skipUrl = '';
    }

    highlight(selector) {
        this.unhighlight();
        const target = this.find(selector);

        if (!target) {
            return;
        }

        target.classList.add('phil-onboarding-highlight');
        target.scrollIntoView({ block: 'center', behavior: this.reducedMotion() ? 'auto' : 'smooth' });
        this.highlighted = target;
    }

    unhighlight() {
        this.highlighted?.classList.remove('phil-onboarding-highlight');
        this.highlighted = null;
    }

    find(selector) {
        if (!selector) {
            return null;
        }

        try {
            return globalThis.document?.querySelector(selector) ?? null;
        } catch {
            return null;
        }
    }

    send(url) {
        return globalThis
            .fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', [this.headerValue]: this.tokenValue },
            })
            .then((response) => {
                if (!response.ok) {
                    this.dispatch('failed', { detail: { url, status: response.status } });
                }
            })
            .catch(() => this.dispatch('failed', { detail: { url, status: 0 } }));
    }

    inField(element) {
        return ['INPUT', 'TEXTAREA', 'SELECT'].includes(element?.tagName) || element?.isContentEditable === true;
    }

    reducedMotion() {
        return (
            globalThis.document?.documentElement?.dataset?.motion === 'reduce' ||
            (globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false)
        );
    }
}
