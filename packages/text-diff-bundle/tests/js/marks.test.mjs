// The comparison controller and what it decides without the DOM, under Node's test runner, Stimulus resolved to a stub
// by `register.mjs`: `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { label, MODES, normalizeMode, shows, step, stops } from '../../assets/src/lib/marks.js';
import ComparisonController from '../../assets/src/comparison_controller.js';

test('the views', () => {
    assert.deepEqual(MODES, ['inline', 'old', 'new']);

    for (const mode of MODES) {
        assert.equal(normalizeMode(mode), mode);
    }

    assert.equal(normalizeMode('both'), 'inline');
    assert.equal(normalizeMode(undefined), 'inline');
    assert.equal(shows('inline', 'ins'), true);
    assert.equal(shows('inline', 'del'), true);
    assert.equal(shows('old', 'del'), true);
    assert.equal(shows('old', 'ins'), false);
    assert.equal(shows('new', 'ins'), true);
    assert.equal(shows('new', 'del'), false);
});

test('a replacement is one stop in the inline view only', () => {
    const marks = [
        { tag: 'del', joinsPrevious: false },
        { tag: 'ins', joinsPrevious: true },
        { tag: 'ins', joinsPrevious: false },
        { tag: 'del', joinsPrevious: false },
        { tag: 'del', joinsPrevious: true },
        { tag: 'ins', joinsPrevious: true },
        { tag: 'ins', joinsPrevious: true },
    ];

    assert.deepEqual(stops(marks, 'inline'), [[0, 1], [2], [3], [4, 5], [6]]);
    assert.deepEqual(stops(marks, 'old'), [[0], [3], [4]]);
    assert.deepEqual(stops(marks, 'new'), [[1], [2], [5], [6]]);
    assert.deepEqual(stops([{ tag: 'ins', joinsPrevious: true }], 'inline'), [[0]]);
    assert.deepEqual(stops([], 'inline'), []);
});

test('the step goes round the ends', () => {
    assert.equal(step(-1, 0, 1), -1);
    assert.equal(step(2, 0, -1), -1);
    assert.equal(step(-1, 3, 1), 0);
    assert.equal(step(-1, 3, -1), 2);
    assert.equal(step(5, 3, 1), 0);
    assert.equal(step(3, 3, -1), 2);
    assert.equal(step(0, 3, 1), 1);
    assert.equal(step(2, 3, 1), 0);
    assert.equal(step(0, 3, -1), 2);
    assert.equal(step(1, 3, -1), 0);
});

test('the labels', () => {
    assert.equal(label('{current} of {total}', 2, 5), '2 of 5');
    assert.equal(label('{total} / {total}', 0, 3), '3 / 3');
});

class Mark {
    constructor(tag, previousSibling = null) {
        this.localName = tag;
        this.nodeType = 1;
        this.previousSibling = previousSibling;
        this.attributes = {};
        this.classes = new Set();
        this.classList = {
            add: (name) => this.classes.add(name),
            remove: (name) => this.classes.delete(name),
        };
        this.focused = false;
        this.scrolled = null;
    }

    setAttribute(name, value) { this.attributes[name] = value; }

    getAttribute(name) { return this.attributes[name] ?? null; }

    removeAttribute(name) { delete this.attributes[name]; }

    focus(options) { this.focused = options; }

    scrollIntoView(options) { this.scrolled = options; }
}

const text = (content, previousSibling = null) => ({ nodeType: 3, textContent: content, previousSibling });

function controller() {
    // "a <del>b</del> <ins>c</ins> d <ins>e</ins>": the deletion and the first insertion are one replacement.
    const del = new Mark('del', text('a '));
    const ins = new Mark('ins', text(' ', del));
    const other = new Mark('ins', text(' d ', ins));
    const marks = [del, ins, other];
    const old = new Mark('button');
    old.setAttribute('data-phil--text-diff-bundle--comparison-mode-param', 'old');
    const inline = new Mark('button');
    inline.setAttribute('data-phil--text-diff-bundle--comparison-mode-param', 'inline');
    const counter = { textContent: '' };
    const element = {
        dataset: {},
        querySelectorAll: (selector) => ('ins, del' === selector ? marks : marks.filter((mark) => mark.classes.has('text-diff-current'))),
    };
    const instance = Object.assign(new ComparisonController(), {
        element,
        identifier: 'phil--text-diff-bundle--comparison',
        hasOutputTarget: false,
        hasCounterTarget: true,
        counterTarget: counter,
        modeTargets: [old, inline],
        modeValue: 'inline',
        counterTextValue: '{current} of {total}',
        totalTextValue: '{total} change(s)',
        noneTextValue: 'No changes',
    });

    return { instance, marks, counter, element, buttons: { old, inline } };
}

test('the controller steps from change to change', () => {
    globalThis.window = { matchMedia: () => ({ matches: true }) };
    const { instance, marks: [del, ins, other], counter, element, buttons } = controller();

    instance.connect();
    assert.equal(element.dataset.textDiffMode, 'inline');
    assert.equal(buttons.inline.getAttribute('aria-pressed'), 'true');
    assert.equal(buttons.old.getAttribute('aria-pressed'), 'false');
    assert.equal(counter.textContent, '2 change(s)');

    instance.next();
    assert.equal(counter.textContent, '1 of 2');
    assert.deepEqual([...del.classes, ...ins.classes], ['text-diff-current', 'text-diff-current']);
    assert.equal(del.getAttribute('aria-current'), 'true');
    assert.equal(del.getAttribute('tabindex'), '-1');
    assert.deepEqual(del.focused, { preventScroll: true });
    assert.deepEqual(del.scrolled, { block: 'center', behavior: 'auto' });

    instance.next();
    assert.equal(counter.textContent, '2 of 2');
    assert.equal(del.classes.size, 0);
    assert.equal(del.getAttribute('aria-current'), null);
    assert.deepEqual([...other.classes], ['text-diff-current']);

    globalThis.window = {};
    instance.previous();
    assert.equal(counter.textContent, '1 of 2');
    assert.deepEqual(del.scrolled, { block: 'center', behavior: 'smooth' });

    instance.show({ params: { mode: 'old' } });
    instance.modeValueChanged();
    assert.equal(element.dataset.textDiffMode, 'old');
    assert.equal(buttons.old.getAttribute('aria-pressed'), 'true');
    assert.equal(counter.textContent, '1 change(s)');
    assert.equal(del.classes.size, 0);

    instance.previous();
    assert.equal(counter.textContent, '1 of 1');

    instance.show({ params: { mode: 'sideways' } });
    assert.equal(instance.modeValue, 'inline');
    delete globalThis.window;
});

test('the controller with no change, or without a counter', () => {
    const { instance, counter, element } = controller();
    element.querySelectorAll = () => [];

    instance.connect();
    assert.equal(counter.textContent, 'No changes');

    instance.next();
    assert.equal(instance.current, -1);
    assert.equal(counter.textContent, 'No changes');

    instance.hasCounterTarget = false;
    counter.textContent = 'kept';
    instance.next();
    assert.equal(counter.textContent, 'kept');
});

test('the output target holds the marks', () => {
    const { instance, element, marks } = controller();
    element.querySelectorAll = () => [];
    Object.assign(instance, { hasOutputTarget: true, outputTarget: { querySelectorAll: () => marks } });

    assert.equal(instance.changes().length, 2);
});
