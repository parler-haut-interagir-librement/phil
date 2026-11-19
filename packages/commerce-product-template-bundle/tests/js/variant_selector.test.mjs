// The variant selector, under Node's test runner, its peer resolved to a stub by `register.mjs` and the page made of
// plain objects with the properties the controller reads and writes.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import VariantSelectorController from '../../assets/src/variant_selector_controller.js';

const radio = (axis, value, checked = false) => ({
    tagName: 'INPUT',
    dataset: { axis },
    value,
    checked,
    disabled: false,
});

const select = (axis, values, value = '') => ({
    tagName: 'SELECT',
    dataset: { axis },
    value,
    options: ['', ...values].map((code) => ({ value: code, disabled: false })),
});

const members = [
    { sku: 'NAQ001FOMBK', values: { size: 'M', colour: 'BK' }, language: 'F', mockups: { front: 'mockups/bk.webp' } },
    { sku: 'NAQ001FOMPK', values: { size: 'M', colour: 'PK' }, language: 'F', mockups: {} },
    { sku: 'NAQ001FO2XBK', values: { size: '2X', colour: 'BK' }, language: 'F', mockups: { front: 'mockups/bk.webp' } },
    { sku: 'NAQ001EOMBK', values: { size: 'M', colour: 'BK' }, language: 'E', mockups: {} },
];

const controller = (selectors, targets = {}) => {
    const selector = new VariantSelectorController();
    selector.element = { querySelectorAll: (query) => (query === '[data-axis]' ? selectors : []) };
    selector.membersValue = members;
    selector.languageValue = 'F';
    selector.imagesValue = '/shop/';
    selector.hasSkuTarget = targets.sku !== undefined;
    selector.skuTarget = targets.sku;
    selector.hasUnavailableTarget = targets.unavailable !== undefined;
    selector.unavailableTarget = targets.unavailable;
    selector.mockupTargets = targets.mockups ?? [];
    selector.connect();

    return selector;
};

test('a value no member sells with the values chosen is disabled, the others stay enabled', () => {
    const sizes = [radio('size', 'M'), radio('size', '2X', true)];
    const colours = [radio('colour', 'BK'), radio('colour', 'PK')];
    controller([...sizes, ...colours]);

    assert.deepEqual(
        colours.map((input) => input.disabled),
        [false, true],
    );
    assert.deepEqual(
        sizes.map((input) => input.disabled),
        [false, false],
    );
});

test('every axis chosen gives the SKU, the mockups and hides the unavailable notice', () => {
    const sku = { value: 'stale' };
    const unavailable = { hidden: false };
    const front = { dataset: { face: 'front' }, src: '', hidden: true };
    const back = { dataset: { face: 'back' }, src: 'kept', hidden: false };
    const selector = controller([radio('size', 'M', true), radio('colour', 'BK', true)], {
        sku,
        unavailable,
        mockups: [front, back],
    });

    assert.equal(sku.value, 'NAQ001FOMBK');
    assert.equal(unavailable.hidden, true);
    assert.deepEqual([front.src, front.hidden], ['/shop/mockups/bk.webp', false]);
    assert.deepEqual([back.src, back.hidden], ['kept', true]);
    assert.deepEqual(selector.dispatched, [
        { name: 'change', detail: { sku: 'NAQ001FOMBK', values: { size: 'M', colour: 'BK' } } },
    ]);
});

test('a combination nobody sells empties the SKU and shows the notice', () => {
    const sku = { value: 'stale' };
    const unavailable = { hidden: true };
    const selector = controller([select('size', ['M', '2X'], '2X'), select('colour', ['BK', 'PK'], 'PK')], {
        sku,
        unavailable,
    });

    assert.equal(sku.value, '');
    assert.equal(unavailable.hidden, false);
    assert.equal(selector.dispatched[0].detail.sku, null);
});

test('an incomplete choice gives no SKU and keeps the notice hidden; the empty option is never disabled', () => {
    const sku = { value: 'stale' };
    const unavailable = { hidden: true };
    const colours = select('colour', ['BK', 'PK']);
    const selector = controller([select('size', ['M', '2X'], '2X'), colours], { sku, unavailable });

    assert.equal(sku.value, '');
    assert.equal(unavailable.hidden, true);
    assert.deepEqual(
        colours.options.map((option) => option.disabled),
        [false, false, true],
    );
    assert.deepEqual(selector.dispatched[0].detail, { sku: null, values: { size: '2X' } });
});

test('members of another language version are left out; a member without one matches an empty language', () => {
    const sku = { value: '' };
    const selector = controller([radio('size', 'M', true), radio('colour', 'BK', true)], { sku });
    selector.languageValue = 'E';
    selector.update();
    assert.equal(sku.value, 'NAQ001EOMBK');

    selector.membersValue = [{ sku: 'MUG', values: { size: 'M', colour: 'BK' }, mockups: {} }];
    selector.languageValue = '';
    selector.update();
    assert.equal(sku.value, 'MUG');
});

test('without targets, it still says what was chosen', () => {
    const selector = controller([radio('size', 'M', true), radio('colour', 'PK', true)]);

    assert.equal(selector.dispatched[0].detail.sku, 'NAQ001FOMPK');
});
