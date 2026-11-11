// The labels.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { MESSAGES, translator } from '../../assets/src/lib/i18n.js';

test('labels follow the locale and take values', () => {
    assert.equal(translator('fr_CA')('held', { name: 'Ada' }), 'Ada modifie ce bloc.');
    assert.equal(translator('en-GB')('blocks', { count: 3 }), '3 blocks');
    assert.equal(translator('de')('takeOver'), 'Take over');
    assert.equal(translator(undefined)('takeOver'), 'Take over');
    assert.equal(translator('fr', { takeOver: 'Prendre' })('takeOver'), 'Prendre');
    assert.equal(translator('fr')('held'), '{name} modifie ce bloc.');
    assert.equal(translator('en')('unknown'), 'unknown');
    assert.deepEqual(Object.keys(MESSAGES.fr), Object.keys(MESSAGES.en));
});
