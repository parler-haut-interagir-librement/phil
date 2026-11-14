// Shows and hides the fields of a form as it is filled, by the same logic the server judges with. The form carries the
// controller and the logic's steps, as `FormLogic::toArray()` gives them; each field's wrapper names its field, and an
// element may show a computed variable:
//
//     <form data-controller="phil--form-logic--logic"
//           data-phil--form-logic--logic-steps-value="{{ logic.toArray()|json_encode }}">
//         <div data-form-logic-field="guest_name">…</div>
//         <output data-form-logic-output="total"></output>
//     </form>
//
// A hidden field's wrapper gets the `hidden` attribute and its controls are disabled, so the browser does not send
// them; the server judges the answer again whatever it receives. A rule the controller cannot read leaves every field
// shown.

import { Controller } from '@hotwired/stimulus';

import { judge } from './logic.js';

const LIST_NAME = /\[\]$/;
const KEYED_NAME = /^[^[\]]+\[([^[\]]+)\](?:\[\])?$/;

// The name a control answers, `entry[guest_name]` and `guest_name[]` read as `guest_name`.
export function fieldName(control) {
    const name = control.name ?? '';
    const keyed = KEYED_NAME.exec(name);

    return null === keyed ? name.replace(LIST_NAME, '') : keyed[1];
}

// The answer as the form holds it: a lone checkbox as true or false, a group of checkboxes or a multiple select as the
// list of its values, a radio group as its checked value, an empty text as no value.
export function collect(form) {
    const answer = {};
    const lists = new Set();
    const controls = Array.from(form.elements ?? []);

    for (const control of controls) {
        const name = fieldName(control);

        if ('' === name || control.disabled || ['submit', 'button', 'reset', 'file'].includes(control.type)) {
            continue;
        }

        const group = controls.filter((other) => other.type === control.type && fieldName(other) === name);

        if ('checkbox' === control.type && 1 === group.length && !LIST_NAME.test(control.name)) {
            answer[name] = Boolean(control.checked);
        } else if ('checkbox' === control.type || (control.multiple && control.options)) {
            if (!lists.has(name)) {
                lists.add(name);
                answer[name] = [];
            }

            const values =
                'checkbox' === control.type
                    ? control.checked
                        ? [control.value]
                        : []
                    : Array.from(control.options)
                          .filter((option) => option.selected)
                          .map((option) => option.value);
            answer[name].push(...values);
        } else if ('radio' === control.type) {
            answer[name] = control.checked ? control.value : (answer[name] ?? null);
        } else {
            answer[name] = '' === control.value ? null : control.value;
        }
    }

    return answer;
}

export default class extends Controller {
    static values = { steps: Array };

    connect() {
        this.refresh = () => this.apply();
        this.element.addEventListener('input', this.refresh);
        this.element.addEventListener('change', this.refresh);
        this.apply();
    }

    disconnect() {
        this.element.removeEventListener('input', this.refresh);
        this.element.removeEventListener('change', this.refresh);
    }

    apply() {
        let verdict;

        try {
            verdict = judge(this.stepsValue, collect(this.withHiddenEnabled()));
        } catch {
            verdict = { hidden: [], computed: {} };
        }

        for (const wrapper of this.element.querySelectorAll('[data-form-logic-field]')) {
            this.toggle(wrapper, verdict.hidden.includes(wrapper.dataset.formLogicField));
        }

        for (const output of this.element.querySelectorAll('[data-form-logic-output]')) {
            const value = verdict.computed[output.dataset.formLogicOutput];
            output.textContent = null === value || undefined === value ? '' : String(value);
        }

        this.dispatch('change', { detail: verdict });
    }

    // The form, its controls disabled by the controller enabled again for the time of reading: a field shown again
    // reads its value back.
    withHiddenEnabled() {
        for (const control of this.element.querySelectorAll('[data-form-logic-disabled]')) {
            control.disabled = false;
        }

        return this.element;
    }

    toggle(wrapper, hide) {
        wrapper.hidden = hide;

        for (const control of wrapper.querySelectorAll('input, select, textarea, button')) {
            if (hide && !control.disabled) {
                control.disabled = true;
                control.dataset.formLogicDisabled = '';
            } else if (!hide && undefined !== control.dataset.formLogicDisabled) {
                control.disabled = false;
                delete control.dataset.formLogicDisabled;
            }
        }
    }
}
