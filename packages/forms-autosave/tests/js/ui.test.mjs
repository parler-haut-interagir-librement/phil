import assert from 'node:assert/strict';
import { test } from 'node:test';

import { buildUi, ENGLISH, labels } from '../../assets/src/lib/ui.js';
import { fakeDocument } from './fakes.mjs';

test('the labels of the page replace the English ones, and only known ones', () => {
    assert.deepEqual(labels(undefined), ENGLISH);
    const merged = labels({ saved: 'Gardé.', unknown: 'x', discard: '', dismiss: 7 });

    assert.equal(merged.saved, 'Gardé.');
    assert.equal(merged.discard, ENGLISH.discard);
    assert.equal(merged.dismiss, ENGLISH.dismiss);
    assert.equal(Object.hasOwn(merged, 'unknown'), false);
    assert.equal(ENGLISH.saved, 'Draft saved on this device.', 'the English labels are left alone');
});

test('the status is a polite live region, the notice hidden with its buttons wired', () => {
    const root = buildUi(fakeDocument, ENGLISH, 'ctl');
    const target = (name) => root.querySelector(`[data-ctl-target="${name}"]`);

    assert.equal(root.getAttribute('class'), 'phil-autosave');
    assert.equal(target('status').getAttribute('role'), 'status');
    assert.equal(target('status').getAttribute('aria-live'), 'polite');
    assert.equal(target('status').getAttribute('class'), 'phil-autosave__status');
    assert.equal(target('notice').hidden, true);
    assert.equal(target('notice').getAttribute('role'), 'group');
    assert.equal(target('notice').getAttribute('class'), 'phil-autosave__notice');
    assert.equal(target('message').textContent, '');

    for (const [name, hidden] of [['useServer', true], ['discard', false], ['dismiss', false]]) {
        const button = target(name);
        assert.equal(button.tagName, 'BUTTON');
        assert.equal(button.getAttribute('type'), 'button');
        assert.equal(button.getAttribute('data-action'), `ctl#${name}`);
        assert.equal(button.textContent, ENGLISH[name]);
        assert.equal(button.hidden, hidden, name);
    }
});
