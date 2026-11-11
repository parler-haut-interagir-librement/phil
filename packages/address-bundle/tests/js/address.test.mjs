// The address controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { formatUrl, plan } from '../../assets/src/address.js';
import AddressController from '../../assets/src/address_controller.js';

const canada = {
    country: 'CA',
    fields: [
        { name: 'addressLine1', label: 'Adresse', required: true },
        { name: 'administrativeArea', label: 'Province', required: true },
        { name: 'postalCode', label: 'Code postal', required: true },
    ],
    subdivisions: [{ code: 'QC', name: 'Québec' }, { code: 'ON', name: 'Ontario' }],
};

const france = { country: 'FR', fields: [{ name: 'addressLine1', label: 'Adresse', required: true }, { name: 'sortingCode', label: 'Code de tri', required: false }], subdivisions: [] };

class Element {
    constructor(tagName, document, attributes = {}) {
        Object.assign(this, { tagName, ownerDocument: document, children: [], hidden: false, disabled: false, required: false, name: '', id: '', className: '', value: '', type: '', textContent: '' }, attributes);
    }

    querySelector(selector) {
        const tags = selector.split(',').map((tag) => tag.trim().toUpperCase());

        return this.children.find((child) => tags.includes(child.tagName)) ?? null;
    }

    replaceWith(replacement) {
        const siblings = this.parent.children;
        siblings[siblings.indexOf(this)] = replacement;
        replacement.parent = this.parent;
    }

    replaceChildren(...children) {
        this.children = children;
    }

    append(child) {
        child.parent = this;
        this.children.push(child);

        return child;
    }
}

const documentStub = { createElement: (tag) => new Element(tag.toUpperCase(), documentStub) };

const row = (field, control = 'INPUT') => {
    const element = new Element('DIV', documentStub, { dataset: { field } });
    element.append(new Element('LABEL', documentStub, { textContent: field }));
    element.append(new Element(control, documentStub, { name: `address[${field}]`, id: `address_${field}`, className: 'field' }));

    return element;
};

test('a plan says what each field of the form becomes', () => {
    assert.deepEqual(plan(france, ['addressLine1', 'postalCode', 'sortingCode']), [
        { name: 'addressLine1', shown: true, label: 'Adresse', required: true },
        { name: 'postalCode', shown: false, label: null, required: false },
        { name: 'sortingCode', shown: true, label: 'Code de tri', required: false },
    ]);
    assert.equal(formatUrl('/address-format/__COUNTRY__?x=1', 'C A'), '/address-format/C%20A?x=1');
});

test('the rows follow the format: shown, labelled, required, the province a choice', () => {
    const controller = new AddressController();
    controller.rowTargets = [row('addressLine1'), row('administrativeArea'), row('postalCode'), row('sortingCode')];
    controller.apply(canada);

    const [line, area, postal, sorting] = controller.rowTargets;
    assert.deepEqual([line.hidden, postal.hidden, sorting.hidden], [false, false, true]);
    assert.deepEqual([postal.children[0].textContent, postal.children[1].required, postal.children[1].disabled], ['Code postal', true, false]);
    assert.deepEqual([sorting.children[1].disabled, sorting.children[0].textContent], [true, 'sortingCode']);

    const select = area.children[1];
    assert.deepEqual([select.tagName, select.name, select.id, select.className, select.required], ['SELECT', 'address[administrativeArea]', 'address_administrativeArea', 'field', true]);
    assert.deepEqual(select.children.map((option) => [option.value, option.textContent]), [['', ''], ['QC', 'Québec'], ['ON', 'Ontario']]);
    assert.deepEqual(controller.dispatched, [['changed', canada]]);
});

test('a country without provinces gets a text field back', () => {
    const controller = new AddressController();
    controller.rowTargets = [row('addressLine1'), row('administrativeArea', 'SELECT')];
    controller.rowTargets[1].children[1].value = 'QC';
    controller.apply(france);

    const input = controller.rowTargets[1].children[1];
    assert.deepEqual([input.tagName, input.type, input.value, input.name, input.disabled, controller.rowTargets[1].hidden], ['INPUT', 'text', '', 'address[administrativeArea]', true, true]);
});

test('a select kept for a country with provinces is refilled', () => {
    const controller = new AddressController();
    controller.rowTargets = [row('administrativeArea', 'SELECT')];
    const select = controller.rowTargets[0].children[1];
    controller.apply(canada);

    assert.equal(controller.rowTargets[0].children[1], select);
    assert.equal(select.children.length, 3);
});

test('a row without control or label is left as it is', () => {
    const controller = new AddressController();
    const bare = new Element('DIV', documentStub, { dataset: { field: 'administrativeArea' } });
    controller.rowTargets = [bare];
    controller.apply(canada);

    assert.equal(bare.hidden, false);
});

test('changing the country reads its format', async () => {
    const controller = new AddressController();
    const requests = [];
    controller.urlValue = '/address-format/__COUNTRY__';
    controller.countryTarget = { value: 'CA' };
    controller.rowTargets = [row('postalCode')];
    globalThis.fetch = async (url, options) => {
        requests.push([url, options.headers.Accept]);

        return { ok: url.endsWith('/CA'), json: async () => canada };
    };

    await controller.change();
    controller.countryTarget = { value: 'ZZ' };
    await controller.change();
    controller.countryTarget = { value: '' };
    await controller.change();

    assert.deepEqual(requests, [['/address-format/CA', 'application/json'], ['/address-format/ZZ', 'application/json']]);
    assert.deepEqual(controller.dispatched.map(([name]) => name), ['changed']);
    delete globalThis.fetch;
});

test('only the answer for the country still chosen is applied', async () => {
    const controller = new AddressController();
    const pending = new Map();
    controller.urlValue = '/address-format/__COUNTRY__';
    controller.rowTargets = [row('addressLine1'), row('sortingCode')];
    globalThis.fetch = (url) => new Promise((resolve) => pending.set(url, () => resolve({ ok: true, json: async () => (url.endsWith('/FR') ? france : canada) })));

    controller.countryTarget = { value: 'FR' };
    const first = controller.change();
    controller.countryTarget = { value: 'CA' };
    const second = controller.change();
    pending.get('/address-format/CA')();
    await second;
    pending.get('/address-format/FR')();
    await first;

    assert.deepEqual(controller.dispatched, [['changed', canada]]);
    assert.equal(controller.rowTargets[1].hidden, true);
    delete globalThis.fetch;
});

test('a request that fails leaves the fields and says so', async () => {
    const controller = new AddressController();
    const error = new TypeError('offline');
    controller.urlValue = '/address-format/__COUNTRY__';
    controller.countryTarget = { value: 'CA' };
    controller.rowTargets = [row('postalCode')];
    globalThis.fetch = async () => {
        throw error;
    };

    await controller.change();

    assert.deepEqual(controller.dispatched, [['failed', error]]);
    assert.equal(controller.rowTargets[0].hidden, false);
    delete globalThis.fetch;
});
