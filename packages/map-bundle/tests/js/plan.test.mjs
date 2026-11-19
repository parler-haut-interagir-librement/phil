import assert from 'node:assert/strict';
import { test } from 'node:test';

import { BOOTH_STATES, readArchive, readPlan, shapeClass } from '../../assets/src/lib/plan.js';

test('a booth takes the class of its state, unknown when the state is not one', () => {
    assert.deepEqual(BOOTH_STATES, ['free', 'taken', 'closed', 'unknown']);
    assert.equal(shapeClass({ booth: 'b1', state: 'free' }), 'phil-map-booth isFree');
    assert.equal(shapeClass({ booth: 'b1', state: 'taken' }), 'phil-map-booth isTaken');
    assert.equal(shapeClass({ booth: 'b1', state: 'closed' }), 'phil-map-booth isClosed');
    assert.equal(shapeClass({ booth: 'b1', state: '<script>' }), 'phil-map-booth isUnknown');
    assert.equal(shapeClass({ booth: 'b1' }), 'phil-map-booth isUnknown');
});

test('an area of the neighbourhood, and nothing for other shapes', () => {
    assert.equal(shapeClass({ count: 3 }), 'phil-map-area');
    assert.equal(shapeClass({ count: 2.5 }), null);
    assert.equal(shapeClass({ booth: 7 }), null);
    assert.equal(shapeClass({}), null);
    assert.equal(shapeClass(undefined), null);
});

test('a plan is read when it is whole', () => {
    const plan = { image: '/hall.png', tiles: '/t/{z}/{x}/{y}.png', nativeZoom: 1, bounds: [[-90, 0], [0, 180]] };

    assert.deepEqual(readPlan(plan), plan);
    assert.deepEqual(readPlan({ ...plan, tiles: null }), { ...plan, tiles: null });
    assert.deepEqual(readPlan({ ...plan, tiles: 3 }), { ...plan, tiles: null });
    assert.deepEqual(readPlan({ ...plan, nativeZoom: 20 }), { ...plan, nativeZoom: 20 });
    assert.deepEqual(readPlan({ ...plan, nativeZoom: 0 }), { ...plan, nativeZoom: 0 });
});

test('a plan that is not whole is not read', () => {
    const plan = { image: '/hall.png', nativeZoom: 1, bounds: [[-90, 0], [0, 180]] };

    for (const broken of [
        null,
        undefined,
        'plan',
        { ...plan, image: 3 },
        { ...plan, nativeZoom: 1.5 },
        { ...plan, nativeZoom: -1 },
        { ...plan, nativeZoom: 21 },
        { ...plan, bounds: [[-90, 0]] },
        { ...plan, bounds: [[-90, 0], [0]] },
        { ...plan, bounds: [[-90, 0], [0, Infinity]] },
        { ...plan, bounds: 'x' },
        { ...plan, bounds: [[-90, 0], 'xy'] },
    ]) {
        assert.equal(readPlan(broken), null, JSON.stringify(broken));
    }
});

test('an archive is read with its defaults', () => {
    assert.deepEqual(readArchive({ url: '/a.pmtiles', kind: 'vector', attribution: '© A', maxZoom: 12, flavor: 'dark' }), { url: '/a.pmtiles', kind: 'vector', attribution: '© A', maxZoom: 12, flavor: 'dark' });
    assert.deepEqual(readArchive({ url: '/a.pmtiles', kind: 'raster' }), { url: '/a.pmtiles', kind: 'raster', attribution: '', maxZoom: 15, flavor: 'light' });
    assert.deepEqual(readArchive({ url: '/a.pmtiles', kind: 'raster', attribution: 1, maxZoom: 1.5, flavor: 2 }), { url: '/a.pmtiles', kind: 'raster', attribution: '', maxZoom: 15, flavor: 'light' });
});

test('an archive that is not one is not read', () => {
    for (const broken of [null, undefined, 'a', { url: 3, kind: 'raster' }, { url: '/a', kind: 'mbtiles' }, { url: '/a' }]) {
        assert.equal(readArchive(broken), null, JSON.stringify(broken));
    }
});
