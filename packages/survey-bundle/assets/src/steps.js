// The pace of a questionnaire, apart from the controller so that Node tests it: which steps are shown, where the next
// and the previous shown step are, and the progress.

// Whether a step is shown: a question the logic does not hide, or a section with at least one such question.
export function isShown(step) {
    if (step.hasAttribute('data-form-logic-field')) {
        return !step.hidden;
    }

    return Array.from(step.querySelectorAll('[data-form-logic-field]')).some((question) => !question.hidden);
}

// The index of the next shown step after `index`, or `index` when none is.
export function nextShown(steps, index) {
    for (let next = index + 1; next < steps.length; next += 1) {
        if (isShown(steps[next])) {
            return next;
        }
    }

    return index;
}

// The index of the previous shown step before `index`, or `index` when none is.
export function previousShown(steps, index) {
    for (let previous = index - 1; previous >= 0; previous -= 1) {
        if (isShown(steps[previous])) {
            return previous;
        }
    }

    return index;
}

// The first shown step at or after `index`, else the last shown before it, else 0: where the respondent stays when the
// logic hides the step they were on.
export function settle(steps, index) {
    if (index < steps.length && isShown(steps[index])) {
        return index;
    }

    const next = nextShown(steps, index);

    return next !== index ? next : previousShown(steps, Math.min(index, steps.length));
}

// The position of the step among the shown ones, from 1, and how many are shown.
export function position(steps, index) {
    const shown = steps.map((step, at) => (isShown(step) ? at : -1)).filter((at) => at >= 0);
    const at = shown.indexOf(index);

    return { current: at + 1, total: shown.length };
}

// The progress, a whole percentage: the steps reached over the steps shown.
export function percent(current, total) {
    return 0 === total ? 0 : Math.round((current * 100) / total);
}
