import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatBytes, percent, splitBySize } from '../../assets/src/lib/files.js';

test('sizes read in binary units', () => {
    assert.equal(formatBytes(0), '0 B');
    assert.equal(formatBytes(-5), '0 B');
    assert.equal(formatBytes(1023), '1023 B');
    assert.equal(formatBytes(1024), '1.0 KiB');
    assert.equal(formatBytes(1536), '1.5 KiB');
    assert.equal(formatBytes(33554432), '32.0 MiB');
    assert.equal(formatBytes(5 * 1024 ** 4), '5120.0 GiB');
});

test('files larger than the limit are refused', () => {
    const small = { name: 'a', size: 10 };
    const exact = { name: 'b', size: 20 };
    const large = { name: 'c', size: 21 };

    assert.deepEqual(splitBySize([small, exact, large], 20), { accepted: [small, exact], refused: [large] });
    assert.deepEqual(splitBySize([], 20), { accepted: [], refused: [] });
});

test('the progress is a share from 0 to 100', () => {
    assert.equal(percent(0, 0), 0);
    assert.equal(percent(5, -1), 0);
    assert.equal(percent(1, 3), 33);
    assert.equal(percent(3, 3), 100);
    assert.equal(percent(4, 3), 100);
});
