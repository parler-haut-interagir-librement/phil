// The logic controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import LogicController, { collect, fieldName } from '../../assets/src/logic_controller.js';

function control(attributes) {
    return { type: 'text', value: '', checked: false, disabled: false, multiple: false, dataset: {}, ...attributes };
}

function wrapper(field, controls) {
    return { dataset: { formLogicField: field }, hidden: false, querySelectorAll: () => controls };
}

function form(wrappers, outputs, extra = []) {
    const controls = [...wrappers.flatMap((item) => item.querySelectorAll()), ...extra];
    const listeners = {};

    return {
        elements: controls,
        listeners,
        addEventListener(type, listener) {
            listeners[type] = listener;
        },
        removeEventListener(type, listener) {
            if (listeners[type] === listener) {
                delete listeners[type];
            }
        },
        querySelectorAll(selector) {
            if ('[data-form-logic-field]' === selector) {
                return wrappers;
            }

            if ('[data-form-logic-output]' === selector) {
                return outputs;
            }

            return controls.filter((item) => undefined !== item.dataset.formLogicDisabled);
        },
    };
}

function mount(element, steps) {
    const controller = new LogicController();
    controller.element = element;
    controller.stepsValue = steps;
    controller.connect();

    return controller;
}

const steps = [
    { field: 'guest_name', when: { '==': [{ var: 'bringing_guest' }, true] } },
    { field: 'guest_diet', when: { '!!': [{ var: 'guest_name' }] } },
    { compute: 'total', type: 'number', value: { '+': [1, { if: [{ var: 'bringing_guest' }, 1, 0] }] } },
];

test('a field is named by its key, a list field without its brackets', () => {
    assert.equal(fieldName({ name: 'entry[guest_name]' }), 'guest_name');
    assert.equal(fieldName({ name: 'entry[colours][]' }), 'colours');
    assert.equal(fieldName({ name: 'colours[]' }), 'colours');
    assert.equal(fieldName({ name: 'plain' }), 'plain');
    assert.equal(fieldName({}), '');
});

test('the answer is read as the server reads it', () => {
    const select = control({
        name: 'days',
        type: 'select-multiple',
        multiple: true,
        options: [
            { value: 'fri', selected: true },
            { value: 'sat', selected: false },
            { value: 'sun', selected: true },
        ],
    });
    const element = {
        elements: [
            control({ name: 'entry[agreed]', type: 'checkbox', checked: true }),
            control({ name: 'entry[colours][]', type: 'checkbox', value: 'red', checked: true }),
            control({ name: 'entry[colours][]', type: 'checkbox', value: 'blue' }),
            control({ name: 'size', type: 'checkbox', value: 's' }),
            control({ name: 'size', type: 'checkbox', value: 'm', checked: true }),
            control({ name: 'team', type: 'radio', value: 'Blue', checked: true }),
            control({ name: 'team', type: 'radio', value: 'Green' }),
            control({ name: 'mood', type: 'radio', value: 'happy' }),
            control({ name: 'entry[name]', value: 'Ada' }),
            control({ name: 'entry[empty]', value: '' }),
            control({ name: 'entry[off]', value: 'x', disabled: true }),
            control({ name: 'send', type: 'submit', value: 'Send' }),
            control({ name: '', value: 'nameless' }),
            select,
        ],
    };

    assert.deepEqual(collect(element), {
        agreed: true,
        colours: ['red'],
        size: ['m'],
        team: 'Blue',
        mood: null,
        name: 'Ada',
        empty: null,
        days: ['fri', 'sun'],
    });
    assert.deepEqual(collect({}), {});
});

test('fields are hidden in cascade and computed variables shown', () => {
    const checkbox = control({ name: 'entry[bringing_guest]', type: 'checkbox' });
    const name = control({ name: 'entry[guest_name]', value: 'Grace' });
    const diet = control({ name: 'entry[guest_diet]', value: 'vegan' });
    const alreadyOff = control({ name: 'entry[guest_note]', value: 'n', disabled: true });
    const guest = wrapper('guest_name', [name, alreadyOff]);
    const dietWrapper = wrapper('guest_diet', [diet]);
    const total = { dataset: { formLogicOutput: 'total' }, textContent: 'stale' };
    const unknown = { dataset: { formLogicOutput: 'nothing' }, textContent: 'stale' };
    const element = form([guest, dietWrapper], [total, unknown], [checkbox]);
    const controller = mount(element, steps);

    assert.equal(guest.hidden, true);
    assert.equal(dietWrapper.hidden, true, 'a field hidden makes the field reading it hidden too');
    assert.equal(name.disabled, true);
    assert.equal(diet.disabled, true);
    assert.equal(alreadyOff.disabled, true);
    assert.equal(alreadyOff.dataset.formLogicDisabled, undefined, 'a control disabled by the page is left to the page');
    assert.equal(total.textContent, '1');
    assert.equal(unknown.textContent, '');
    assert.deepEqual(controller.dispatched.at(-1), [
        'change',
        { hidden: ['guest_name', 'guest_diet'], computed: { total: 1 } },
    ]);

    checkbox.checked = true;
    element.listeners.change();

    assert.equal(guest.hidden, false);
    assert.equal(dietWrapper.hidden, false, 'the value kept while hidden is read back once shown');
    assert.equal(name.disabled, false);
    assert.equal(name.dataset.formLogicDisabled, undefined);
    assert.equal(diet.disabled, false);
    assert.equal(alreadyOff.disabled, true);
    assert.equal(total.textContent, '2');

    name.value = '';
    element.listeners.input();

    assert.equal(guest.hidden, false);
    assert.equal(dietWrapper.hidden, true);

    controller.disconnect();
    assert.deepEqual(element.listeners, {});
});

test('a rule the controller cannot read leaves every field shown', () => {
    const name = control({ name: 'guest_name', value: '' });
    const guest = wrapper('guest_name', [name]);
    const output = { dataset: { formLogicOutput: 'total' }, textContent: 'stale' };
    const element = form([guest], [output]);
    guest.hidden = true;
    const controller = mount(element, [{ field: 'guest_name', when: { cat: ['a'] } }]);

    assert.equal(guest.hidden, false);
    assert.equal(name.disabled, false);
    assert.equal(output.textContent, '');
    assert.deepEqual(controller.dispatched.at(-1), ['change', { hidden: [], computed: {} }]);
});

test('a computed text is shown as it is', () => {
    const output = { dataset: { formLogicOutput: 'band' }, textContent: '' };
    mount(form([], [output]), [{ compute: 'band', type: 'text', value: { if: [true, 'gold', 'silver'] } }]);

    assert.equal(output.textContent, 'gold');
});
