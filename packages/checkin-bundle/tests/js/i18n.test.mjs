import assert from 'node:assert/strict';
import { test } from 'node:test';

import { MESSAGES, translator } from '../../assets/src/lib/i18n.js';

test('every label exists in both languages', () => {
    assert.deepEqual(Object.keys(MESSAGES.fr).sort(), Object.keys(MESSAGES.en).sort());
});

test('a locale takes its language, an unknown one English, and labels can be replaced', () => {
    assert.equal(translator('fr_CA')('admitted'), 'Admis');
    assert.equal(translator('fr-FR')('admitted'), 'Admis');
    assert.equal(translator('de')('admitted'), 'Admitted');
    assert.equal(translator(undefined)('admitted'), 'Admitted');
    assert.equal(translator('en', { admitted: 'Welcome' })('admitted'), 'Welcome');
    assert.equal(translator('en')('missing'), 'missing');
});

test('every outcome of the library has a label', () => {
    for (const outcome of ['admitted', 'checked_out', 'already_admitted', 'not_inside', 'not_yet_valid', 'expired', 'revoked', 'wrong_checkpoint', 'unknown', 'collision']) {
        assert.ok(Object.hasOwn(MESSAGES.en, outcome), outcome);
    }

    assert.equal(translator('fr')('collision'), 'Scan en double, scannez de nouveau');
});

test('values are replaced, unknown ones kept', () => {
    const t = translator('fr');

    assert.equal(t('already_admitted_at', { time: '10:02' }), 'Déjà admis à 10:02');
    assert.equal(t('pending', { count: 3 }), '3 scans à envoyer');
    assert.equal(t('pending'), '{count} scans à envoyer');
});
