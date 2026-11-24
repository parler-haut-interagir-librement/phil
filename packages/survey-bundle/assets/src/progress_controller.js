// The progress controller, registered as `phil--survey-bundle--progress`, on the questionnaire's form beside
// phil/form-logic's `logic` controller. Without it, every question is on one page; with it, the questionnaire is shown
// a question or a section at a time — its `pacing` value — with Previous and Next, the progress bar, and the questions
// the logic hides skipped. Next checks the controls of the step first, as the browser would on sending.
//
//     <form data-controller="phil--form-logic--logic phil--survey-bundle--progress"
//           data-phil--survey-bundle--progress-pacing-value="section">
//         <progress data-phil--survey-bundle--progress-target="bar" hidden></progress>
//         <fieldset data-phil--survey-bundle--progress-target="section">
//             <div data-form-logic-field="age" data-phil--survey-bundle--progress-target="question">…</div>
//         </fieldset>
//         <button type="button" hidden data-phil--survey-bundle--progress-target="previous" data-action="…#previous">
//         <button type="button" hidden data-phil--survey-bundle--progress-target="next" data-action="…#next">
//         <button type="submit" data-phil--survey-bundle--progress-target="submit">
//     </form>
//
// The logic hides with the `hidden` attribute; the controller sets the steps it puts away `inert` and out of view
// instead, so that the two never undo each other.

import { Controller } from '@hotwired/stimulus';

import { nextShown, percent, position, previousShown, settle } from './steps.js';

export default class extends Controller {
    static targets = ['section', 'question', 'bar', 'previous', 'next', 'submit'];

    static values = { pacing: { type: String, default: 'section' } };

    connect() {
        if (!['question', 'section'].includes(this.pacingValue) || 0 === this.steps().length) {
            return;
        }

        this.index = settle(this.steps(), 0);
        this.refresh = () => this.render(false);
        this.element.addEventListener('phil--form-logic--logic:change', this.refresh);
        this.render(false);
    }

    disconnect() {
        if (undefined === this.refresh) {
            return;
        }

        this.element.removeEventListener('phil--form-logic--logic:change', this.refresh);
        for (const step of this.steps()) {
            this.away(step, false);
        }
    }

    next() {
        const step = this.steps()[this.index];
        const invalid = Array.from(step.querySelectorAll('input, select, textarea')).find(
            (control) => !control.disabled && !control.checkValidity(),
        );

        if (undefined !== invalid) {
            invalid.reportValidity();

            return;
        }

        this.index = nextShown(this.steps(), this.index);
        this.render(true);
    }

    previous() {
        this.index = previousShown(this.steps(), this.index);
        this.render(true);
    }

    steps() {
        return 'question' === this.pacingValue ? this.questionTargets : this.sectionTargets;
    }

    render(focus) {
        const steps = this.steps();
        this.index = settle(steps, this.index);
        steps.forEach((step, at) => {
            this.away(step, at !== this.index);
        });

        const { current, total } = position(steps, this.index);
        const last = current >= total;

        if (this.hasBarTarget) {
            this.barTarget.hidden = false;
            this.barTarget.value = percent(current, total);
            this.barTarget.textContent = `${current} / ${total}`;
        }

        if (this.hasPreviousTarget) {
            this.previousTarget.hidden = current <= 1;
        }

        if (this.hasNextTarget) {
            this.nextTarget.hidden = last;
        }

        if (this.hasSubmitTarget) {
            this.submitTarget.hidden = !last;
        }

        if (focus) {
            const step = steps[this.index];
            step.setAttribute('tabindex', '-1');
            step.focus();
        }
    }

    away(step, away) {
        step.inert = away;
        step.style.display = away ? 'none' : '';
    }
}
