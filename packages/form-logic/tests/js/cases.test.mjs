// The cases shared with PHPUnit, `tests/Fixtures/cases.json`, under Node's test runner: the browser's evaluator gives
// the result the server's does, and refuses what the server's reader refuses.
// `node --import ./tests/js/register.mjs --test tests/js`

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { check, evaluate, judge, UnreadableRule } from '../../assets/src/logic.js';

const cases = JSON.parse(readFileSync(new URL('../Fixtures/cases.json', import.meta.url), 'utf8'));

test('every shared case gives its result', () => {
    let section = '';

    for (const item of cases.evaluate) {
        if ('string' === typeof item) {
            section = item;
            continue;
        }

        const [rule, data, expected] = item;
        check(rule);
        assert.deepStrictEqual(
            evaluate(rule, data),
            expected,
            `${section}: ${JSON.stringify(rule)} on ${JSON.stringify(data)}`,
        );
    }
});

test('every refused case is refused before it is evaluated', () => {
    for (const [rule, reason] of cases.refuse) {
        assert.throws(() => check(rule), UnreadableRule, reason);
    }
});

test('a rule nested too deep is refused', () => {
    let rule = true;

    for (let depth = 0; depth < 70; depth++) {
        rule = { '!': [rule] };
    }

    assert.throws(() => check(rule), UnreadableRule);
    assert.throws(() => evaluate(rule, {}), UnreadableRule);
    assert.throws(() => evaluate(() => true, {}), UnreadableRule);
});

test('the steps are judged as the server judges them', () => {
    const steps = [
        { compute: 'total', type: 'number', value: { '+': [{ var: 'a' }, 1] } },
        { field: 'b', when: { '>': [{ var: 'total' }, 2] } },
        { field: 'c', when: { '!!': [{ var: 'b' }] } },
    ];

    assert.deepEqual(judge(steps, { a: '1', b: 'yes', c: 'x' }), { hidden: ['b', 'c'], computed: { total: 2 } });
    assert.deepEqual(judge(steps, { a: '2', b: 'yes' }), { hidden: [], computed: { total: 3 } });
    assert.throws(() => judge({}, {}), UnreadableRule);
    assert.throws(() => judge([{ other: 1 }], {}), UnreadableRule);
    assert.throws(() => judge([null], {}), UnreadableRule);
    assert.throws(() => judge([{ field: 'b' }], {}), UnreadableRule);
});
