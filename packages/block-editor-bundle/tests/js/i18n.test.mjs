// The labels: languages, fallbacks and replacements.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { MESSAGES, language, translator } from '../../assets/src/lib/i18n.js';

test('a language is chosen from a locale', () => {
    assert.equal(language('fr_CA'), 'fr');
    assert.equal(language('fr-CA'), 'fr');
    assert.equal(language('FR'), 'fr');
    assert.equal(language('de'), 'en');
    assert.equal(language(undefined), 'en');
    assert.equal(language('pt-BR', ['en', 'pt-br']), 'pt-br');
});

test('every label exists in both languages', () => {
    assert.deepEqual(Object.keys(MESSAGES.fr).sort(), Object.keys(MESSAGES.en).sort());
});

test('labels come from the application, the locale, English, or the key', () => {
    const t = translator('fr', { delete: 'Effacer' });
    assert.equal(t('delete'), 'Effacer');
    assert.equal(t('duplicate'), 'Dupliquer');
    assert.equal(t('nope'), 'nope');
    assert.equal(t('foreign', { type: 'statblock' }), 'Un bloc « statblock », gardé tel quel.');
    assert.equal(t('foreign'), 'Un bloc « {type} », gardé tel quel.');
    assert.equal(translator('de')('duplicate'), 'Duplicate');
    assert.equal(translator('en', [])('duplicate'), 'Duplicate', 'an empty PHP array is no labels');
});
