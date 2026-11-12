import assert from 'node:assert/strict';
import { test } from 'node:test';

import { apply, collect, isKept, mark, remount, sameFields } from '../../assets/src/lib/fields.js';
import { FakeElement, field, form } from './fakes.mjs';

test('secrets, files, buttons, tokens, disabled and unnamed fields are never kept', () => {
    for (const type of ['password', 'file', 'submit', 'button', 'reset', 'image', 'fieldset', 'output']) {
        assert.equal(isKept(field(type, 'x')), false, type);
    }

    for (const name of ['_token', 'form[_token]', '_csrf_token', 'a[b][_token]']) {
        assert.equal(isKept(field('text', name)), false, name);
    }

    for (const autocomplete of ['cc-number', 'cc-csc', 'new-password', 'current-password', 'one-time-code', 'section-a CC-EXP']) {
        assert.equal(isKept(field('text', 'x', { autocomplete })), false, autocomplete);
    }

    assert.equal(isKept(field('text', '')), false);
    assert.equal(isKept(field('text', 'x', { disabled: true })), false);
    assert.equal(isKept({ name: 7, type: 'text' }), false);
    assert.equal(isKept({ name: 'plain' }), true, 'a field without a type is a text');
    assert.equal(isKept(field('text', 'token')), true);
    assert.equal(isKept(field('text', 'a_token_b')), true);
    assert.equal(isKept(field('text', 'notes', { autocomplete: 'street-address' })), true);
    assert.equal(isKept(field('text', 'notes', { autocomplete: 'xcc-number' })), true);
    assert.equal(isKept(field('TEXTAREA', 'notes')), true);
});

test('a mark around a field keeps it out or puts a hidden one in, the nearest deciding', () => {
    const outer = new FakeElement('div', { 'data-phil-autosave': 'off' });
    const inner = new FakeElement('div', { 'data-phil-autosave': 'on' });
    const offText = field('text', 'secret');
    const onHidden = field('hidden', 'body');
    const plainHidden = field('hidden', 'id');
    outer.append(offText, inner);
    inner.append(onHidden);

    assert.equal(mark(offText), 'off');
    assert.equal(mark(onHidden), 'on');
    assert.equal(mark(plainHidden), null);
    assert.equal(mark({}), null);
    assert.equal(isKept(offText), false);
    assert.equal(isKept(onHidden), true);
    assert.equal(isKept(plainHidden), false);
    assert.equal(isKept(field('hidden', 'x', {}, { 'data-phil-autosave': 'off' })), false);
    assert.equal(isKept(field('text', 'x', {}, { 'data-phil-autosave': 'on' })), true);
});

function options(...values) {
    return values.map(([value, selected]) => ({ value, selected }));
}

test('values are collected by name in the order of the form', () => {
    const fields = collect(
        form([
            field('text', 'title', { value: 'Hello' }),
            field('checkbox', 'tags[]', { value: 'a', checked: true }),
            field('checkbox', 'tags[]', { value: 'b', checked: false }),
            field('checkbox', 'tags[]', { value: 'c', checked: true }),
            field('radio', 'size', { value: 's', checked: false }),
            field('radio', 'size', { value: 'm', checked: false }),
            field('select-multiple', 'days', { options: options(['mon', true], ['tue', false], ['wed', true]) }),
            field('select-one', 'level', { value: '2' }),
            field('text', 'lines[]', { value: 'one' }),
            field('text', 'lines[]', { value: 'two' }),
            field('password', 'pass', { value: 'secret' }),
            field('number', 'count', { value: 3 }),
        ]),
    );

    assert.equal(Object.getPrototypeOf(fields), null);
    assert.deepEqual({ ...fields }, {
        title: ['Hello'],
        'tags[]': ['a', 'c'],
        size: [],
        days: ['mon', 'wed'],
        level: ['2'],
        'lines[]': ['one', 'two'],
        count: ['3'],
    });
});

test('a field named __proto__ is kept as any other', () => {
    const fields = collect(form([field('text', '__proto__', { value: 'x' })]));

    assert.deepEqual(Object.keys(fields), ['__proto__']);
    assert.deepEqual(fields.__proto__, ['x']);
    assert.equal({}.polluted, undefined);
});

test('values are written back, unchecking what the draft does not hold', () => {
    const title = field('text', 'title', { value: 'Old' });
    const a = field('checkbox', 'tags[]', { value: 'a', checked: true });
    const b = field('checkbox', 'tags[]', { value: 'b', checked: false });
    const s = field('radio', 'size', { value: 's', checked: true });
    const m = field('radio', 'size', { value: 'm', checked: false });
    const days = field('select-multiple', 'days', { options: options(['mon', true], ['tue', false]) });
    const same = field('select-multiple', 'same', { options: options(['x', true], ['y', false]) });
    const first = field('text', 'lines[]', { value: 'one' });
    const second = field('text', 'lines[]', { value: 'two' });
    const third = field('text', 'lines[]', { value: 'three' });
    const untouched = field('text', 'other', { value: 'stays' });
    const secret = field('password', 'pass', { value: '' });
    const wrong = field('text', 'wrong', { value: 'stays' });

    const changed = apply(form([title, a, b, s, m, days, same, first, second, third, untouched, secret, wrong]), {
        title: ['New'],
        'tags[]': ['b'],
        size: ['m'],
        days: ['tue'],
        same: ['x'],
        'lines[]': ['one', 'deux', 7],
        pass: ['leaked'],
        wrong: 'not a list',
    });

    assert.deepEqual(changed, [title, a, b, s, m, days, second]);
    assert.equal(title.value, 'New');
    assert.deepEqual([a.checked, b.checked, s.checked, m.checked], [false, true, false, true]);
    assert.deepEqual(days.options.map((option) => option.selected), [false, true]);
    assert.deepEqual(same.options.map((option) => option.selected), [true, false]);
    assert.deepEqual([first.value, second.value, third.value], ['one', 'deux', 'three']);
    assert.equal(untouched.value, 'stays');
    assert.equal(secret.value, '');
    assert.equal(wrong.value, 'stays');
    assert.deepEqual(apply(form([title]), Object.create({ title: ['inherited'] })), []);
});

test('two sets of values are the same name by name and value by value', () => {
    assert.equal(sameFields({ a: ['1'], b: [] }, { b: [], a: ['1'] }), true);
    assert.equal(sameFields({}, {}), true);
    assert.equal(sameFields({ a: ['1'] }, { a: ['2'] }), false);
    assert.equal(sameFields({ a: ['1'] }, { a: ['1', '2'] }), false);
    assert.equal(sameFields({ a: ['1'] }, { b: ['1'] }), false);
    assert.equal(sameFields({ a: ['1'] }, { a: ['1'], b: [] }), false);
    assert.equal(sameFields({ a: '1' }, { a: '1' }), false);
    assert.equal(sameFields({ a: ['1'] }, { a: '1' }), false);
});

test('the controllers named around changed fields are mounted again, once each, in a later task', () => {
    const editor = new FakeElement('div', { 'data-controller': 'other phil--block-editor-bundle--editor' });
    const input = field('hidden', 'body', {}, { 'data-phil-autosave-remount': 'phil--block-editor-bundle--editor' });
    const twin = field('hidden', 'body2', {}, { 'data-phil-autosave-remount': 'phil--block-editor-bundle--editor' });
    const lost = field('hidden', 'x', {}, { 'data-phil-autosave-remount': 'missing' });
    editor.append(input, twin);
    const steps = [];

    assert.deepEqual(remount([input, twin, lost, field('text', 'plain'), { type: 'text' }], (step) => steps.push(step)), [editor]);
    assert.equal(editor.getAttribute('data-controller'), 'other');
    assert.equal(steps.length, 1);
    steps[0]();
    assert.equal(editor.getAttribute('data-controller'), 'other phil--block-editor-bundle--editor');
});

test('a remount waits for a timer by default', async () => {
    const editor = new FakeElement('div', { 'data-controller': 'ed' });
    const input = field('hidden', 'body', {}, { 'data-phil-autosave-remount': 'ed' });
    editor.append(input);

    remount([input]);
    assert.equal(editor.getAttribute('data-controller'), '');
    await new Promise((resolve) => setTimeout(resolve, 5));
    assert.equal(editor.getAttribute('data-controller'), 'ed');
});
