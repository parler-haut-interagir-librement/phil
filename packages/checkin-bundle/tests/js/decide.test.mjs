// `node --test tests/js`: the offline policy follows the order of AdmissionPolicy::decide().
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { apply, decide } from '../../assets/src/lib/decide.js';

const fresh = {
    id: 'T-1',
    label: null,
    validFrom: null,
    validUntil: null,
    maxEntries: 1,
    entries: 0,
    inside: false,
    revoked: false,
};
const policy = { earlySeconds: 0, antiPassback: true };

test('a fresh credential is admitted', () => {
    assert.equal(decide(fresh, policy, 'in', 1000), 'admitted');
});

test('a revoked credential is refused before anything else', () => {
    assert.equal(decide({ ...fresh, revoked: true, inside: true }, policy, 'out', 1000), 'revoked');
});

test('an exit lets out only a holder inside', () => {
    assert.equal(decide({ ...fresh, inside: true, entries: 1, validUntil: 1 }, policy, 'out', 1000), 'checked_out');
    assert.equal(decide(fresh, policy, 'out', 1000), 'not_inside');
});

test('the opening is kept with its early window, the closing to its last second', () => {
    assert.equal(decide({ ...fresh, validFrom: 1900 }, policy, 'in', 1000), 'not_yet_valid');
    assert.equal(decide({ ...fresh, validFrom: 1900 }, { earlySeconds: 900 }, 'in', 1000), 'admitted');
    assert.equal(decide({ ...fresh, validFrom: 1901 }, { earlySeconds: 900 }, 'in', 1000), 'not_yet_valid');
    assert.equal(decide({ ...fresh, validUntil: 1000 }, policy, 'in', 1000), 'admitted');
    assert.equal(decide({ ...fresh, validUntil: 999 }, policy, 'in', 1000), 'expired');
});

test('a holder inside comes in again only without anti-passback, within the entries', () => {
    const inside = { ...fresh, maxEntries: null, entries: 1, inside: true };

    assert.equal(decide(inside, policy, 'in', 1000), 'already_admitted');
    assert.equal(decide(inside, { antiPassback: false }, 'in', 1000), 'admitted');
    assert.equal(decide({ ...fresh, entries: 1 }, { antiPassback: false }, 'in', 1000), 'already_admitted');
});

test('an admission adds an entry, a check-out lets out, a refusal changes nothing', () => {
    assert.deepEqual(apply(fresh, 'admitted'), { ...fresh, entries: 1, inside: true });
    assert.deepEqual(apply({ ...fresh, entries: 1, inside: true }, 'checked_out'), {
        ...fresh,
        entries: 1,
        inside: false,
    });
    assert.equal(apply(fresh, 'expired'), fresh);
});
