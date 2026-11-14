// The test cases JsonLogic publishes, `tests/Fixtures/json-logic/tests.json`, under Node's test runner: each rule of
// the subset gives the value JsonLogic expects, each rule refused is listed in `differences.json` with its reason, and
// each difference on values gives the value listed there, as `OfficialCasesTest` checks in PHP.
// `node --import ./tests/js/register.mjs --test tests/js`

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { check, evaluate, UnreadableRule } from '../../assets/src/logic.js';

const read = (name) => JSON.parse(readFileSync(new URL(`../Fixtures/json-logic/${name}`, import.meta.url), 'utf8'));
const published = read('tests.json').filter((item) => 'string' !== typeof item);
const differences = read('differences.json');
const refused = new Set(differences.refused.flatMap((group) => group.rules.map((rule) => JSON.stringify(rule))));

test('every published case gives its value, or is a listed refusal', () => {
    let evaluated = 0;

    for (const [rule, data, expected] of published) {
        const listed = refused.has(JSON.stringify(rule));

        try {
            check(rule);
        } catch (error) {
            assert.ok(error instanceof UnreadableRule, String(error));
            assert.ok(listed, `refused without being listed: ${JSON.stringify(rule)}`);
            continue;
        }

        assert.ok(!listed, `listed as refused, but read: ${JSON.stringify(rule)}`);
        assert.deepStrictEqual(
            evaluate(rule, data ?? {}),
            expected,
            `${JSON.stringify(rule)} on ${JSON.stringify(data)}`,
        );
        evaluated++;
    }

    assert.equal(evaluated, 198);
});

test('every listed refusal is a published case', () => {
    const rules = new Set(published.map(([rule]) => JSON.stringify(rule)));

    assert.equal(refused.size, 71);
    assert.deepEqual(
        [...refused].filter((rule) => !rules.has(rule)),
        [],
    );
});

test('every difference on values gives the listed value', () => {
    for (const { rule, data, value, reason } of differences.values) {
        check(rule);
        assert.deepStrictEqual(evaluate(rule, data), value, reason);
    }
});
