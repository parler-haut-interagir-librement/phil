import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PREVIEW_MAX, PROPERTIES, backgroundOf, isPreview, position } from '../../assets/src/lib/placeholder.js';

const PREVIEW = 'data:image/webp;base64,UklGRg==';

test('only a tiny inline image of a kept type is a preview', () => {
    assert.equal(isPreview(PREVIEW), true);
    assert.equal(isPreview('data:image/avif;base64,AAAA'), true);
    assert.equal(isPreview('data:image/jpeg;base64,AAAA'), true);
    assert.equal(isPreview('data:image/png;base64,AA=='), true);
    assert.equal(isPreview('data:image/svg+xml;base64,AAAA'), false);
    assert.equal(isPreview('data:image/gif;base64,AAAA'), false);
    assert.equal(isPreview('https://example.com/a.webp'), false);
    assert.equal(isPreview('data:image/webp;base64,AA") , url("x'), false);
    assert.equal(isPreview(` ${PREVIEW}`), false);
    assert.equal(isPreview(`${PREVIEW}\n`), false);
    assert.equal(isPreview(''), false);
    assert.equal(isPreview(null), false);
    assert.equal(isPreview(42), false);
});

test('a preview longer than the library keeps is refused', () => {
    const prefix = 'data:image/webp;base64,';
    const longest = prefix + 'A'.repeat(PREVIEW_MAX - prefix.length);

    assert.equal(PREVIEW_MAX, 4096);
    assert.equal(isPreview(longest), true);
    assert.equal(isPreview(`${longest}A`), false);
});

test('the position is the focal point in whole percents, the centre otherwise', () => {
    assert.equal(position('25% 100%'), '25% 100%');
    assert.equal(position('0% 0%'), '0% 0%');
    assert.equal(position('100% 9%'), '100% 9%');
    assert.equal(position('101% 0%'), '50% 50%');
    assert.equal(position('25%  100%'), '50% 50%');
    assert.equal(position('25% 100%; color: red'), '50% 50%');
    assert.equal(position('07% 10%'), '50% 50%');
    assert.equal(position('left top'), '50% 50%');
    assert.equal(position(''), '50% 50%');
    assert.equal(position(undefined), '50% 50%');
});

test('the preview covers the box around the position', () => {
    assert.deepEqual(backgroundOf(PREVIEW, '25% 100%'), {
        'background-image': `url("${PREVIEW}")`,
        'background-position': '25% 100%',
        'background-size': 'cover',
        'background-repeat': 'no-repeat',
    });
    assert.equal(backgroundOf(PREVIEW, 'nowhere')['background-position'], '50% 50%');
    assert.equal(backgroundOf('javascript:alert(1)', '25% 100%'), null);
    assert.deepEqual(Object.keys(backgroundOf(PREVIEW, '0% 0%')), PROPERTIES);
});
