import assert from 'node:assert/strict';
import { test } from 'node:test';

import { MESSAGES, translator } from '../../assets/src/lib/i18n.js';

test('every label exists in both languages', () => {
    assert.deepEqual(Object.keys(MESSAGES.fr).sort(), Object.keys(MESSAGES.en).sort());
});

test('a locale picks its language, English otherwise', () => {
    assert.equal(translator('fr_CA')('reply'), 'Répondre');
    assert.equal(translator('fr-FR')('reply'), 'Répondre');
    assert.equal(translator('de')('reply'), 'Reply');
    assert.equal(translator(undefined)('reply'), 'Reply');
});

test('values are replaced, overrides win, unknown keys stay', () => {
    const t = translator('en', { reply: 'Answer' });
    assert.equal(t('resolved', { name: 'Ada' }), 'Resolved by Ada');
    assert.equal(t('resolved'), 'Resolved by {name}');
    assert.equal(t('reply'), 'Answer');
    assert.equal(t('nope'), 'nope');
});
