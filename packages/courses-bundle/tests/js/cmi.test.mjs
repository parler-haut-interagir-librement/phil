import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { ScormSession } from '../../assets/src/lib/api.js';
import { CmiData, collection, errorCode, parseName } from '../../assets/src/lib/cmi.js';

const fixture = (name) => JSON.parse(readFileSync(new URL(`./fixture/${name}`, import.meta.url), 'utf8'));
const models = { 1.2: fixture('model-1.2.json'), 2004: fixture('model-2004.json') };
const value = (written) => (Array.isArray(written?.repeat) ? written.repeat[0].repeat(written.repeat[1]) : written);

test('the browser answers every call as the server does', () => {
    for (const { version, values, calls, expect } of fixture('vectors.json')) {
        const session = new ScormSession(new CmiData(models[version], values));

        calls.forEach(([method, argument, written], index) => {
            const answer =
                'setValue' === method ? session.setValue(argument, value(written)) : session[method](argument ?? '');

            assert.deepEqual([answer, session.getLastError()], expect[index], `${version} ${method}(${argument})`);
        });
    }
});

test('a name is read into its pattern, indexes and keyword', () => {
    assert.deepEqual(parseName('cmi.interactions.12.objectives.3.id'), {
        name: 'cmi.interactions.12.objectives.3.id',
        pattern: 'cmi.interactions.n.objectives.n.id',
        indexes: [12, 3],
        keyword: null,
        target: null,
    });
    assert.equal(parseName('adl.nav.request_valid.jump.{target=a.b}').target, '{target=a.b}');
    assert.equal(collection(parseName('cmi.interactions.3.objectives.0.id'), 1), 'cmi.interactions.3.objectives');

    for (const name of [
        7,
        '',
        'a'.repeat(256),
        'cmi.10000.x',
        'cmi..x',
        'cmi.1x',
        'cmi.A',
        'cmi._count.x',
        'cmi.interactions.n.id',
        'a'.repeat(42),
    ]) {
        assert.equal(parseName(name), null, String(name).slice(0, 20));
    }

    assert.deepEqual([errorCode('1.2', 'NotInitialized'), errorCode('2004', 'NotInitialized')], [0, 403]);
});

test('the values kept load back, refusing what is not tracking data, within their bound', () => {
    const data = new CmiData(models['2004'], {
        'cmi.location': 'p',
        'cmi.mood': 'x',
        'cmi.score._count': '1',
        'cmi.score.raw': 3,
    });
    assert.deepEqual(data.values(), { 'cmi.location': 'p' });
    assert.equal(data.value('cmi.score.raw'), null);
    assert.equal(data.version, '2004');

    const bounded = new CmiData(models['2004'], {}, 40);
    assert.equal(bounded.set('cmi.suspend_data', 'a'.repeat(24)).error, 0);
    assert.equal(bounded.set('cmi.suspend_data', 'b'.repeat(24)).error, 0);
    assert.deepEqual(bounded.set('cmi.suspend_data', 'c'.repeat(25)), {
        value: '',
        error: 351,
        diagnostic: 'The tracking data of an attempt holds 40 bytes at most.',
    });
    assert.equal(bounded.set('cmi.location', Number.NaN).error, 406);
    assert.equal(bounded.set('cmi.location', null).error, 406);
});

test('the API carries the names of its version', () => {
    const twelve = new ScormSession(new CmiData(models['1.2'])).api();
    assert.deepEqual(Object.keys(twelve), [
        'LMSInitialize',
        'LMSFinish',
        'LMSGetValue',
        'LMSSetValue',
        'LMSCommit',
        'LMSGetLastError',
        'LMSGetErrorString',
        'LMSGetDiagnostic',
    ]);
    assert.equal(twelve.LMSInitialize(''), 'true');
    assert.equal(twelve.LMSSetValue('cmi.core.lesson_location', 'p'), 'true');
    assert.equal(twelve.LMSGetValue('cmi.core.lesson_location'), 'p');
    assert.equal(twelve.LMSCommit(''), 'true');
    assert.equal(twelve.LMSGetErrorString('0'), 'No error');
    assert.equal(twelve.LMSGetDiagnostic(''), '');
    assert.equal(twelve.LMSGetLastError(), '0');
    assert.equal(twelve.LMSFinish(''), 'true');

    const writes = [];
    const ends = [];
    const session = new ScormSession(new CmiData(models['2004']), {
        onSet: (element, written) => writes.push([element, written]),
        onCommit: () => false,
        onTerminate: () => ends.push('end') > 1,
    });
    const api = session.api();
    assert.equal(api.version, '1.0');
    assert.equal(api.Initialize(''), 'true');
    assert.equal(api.SetValue('cmi.score.raw', 7), 'true');
    assert.equal(api.SetValue('cmi.learner_id', 'x'), 'false');
    assert.deepEqual(writes, [['cmi.score.raw', '7']]);
    assert.equal(api.GetValue('cmi.score.raw'), '7');
    assert.equal(api.Commit(''), 'false');
    assert.equal(api.GetLastError(), '391');
    assert.equal(api.GetErrorString('391'), 'General Commit Failure');
    assert.equal(api.Terminate(''), 'false');
    assert.equal(api.GetLastError(), '111');
    assert.equal(session.state, 'running');
    assert.equal(api.Terminate(''), 'true');
    assert.equal(session.state, 'terminated');
    assert.equal(api.GetDiagnostic(''), '');
});
