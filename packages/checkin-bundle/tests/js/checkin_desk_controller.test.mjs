// The desk controller on a page of fake DOM nodes, under Node's test runner, Stimulus resolved to a stub by
// `register.mjs`: `node --import ./tests/js/register.mjs --test 'tests/js/*.test.mjs'`. The endpoints answer through a
// fetch of the test, the network goes down and comes back through `navigator.onLine`, and the device keeps what it
// needs in the fake IndexedDB of `support/`.
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { afterEach, beforeEach, test } from 'node:test';

import CheckinDeskController from '../../assets/src/checkin_desk_controller.js';
import { fakeIndexedDB, MemoryStorage } from './support/fake-indexeddb.mjs';

const key = Buffer.from('0123456789abcdef0123456789abcdef');
const print = (code) => createHmac('sha256', key).update(code).digest('hex');
const now = () => Math.floor(Date.now() / 1000);
const manifest = () => ({
    format: 1,
    checkpoint: 'hall',
    device: 'door-1',
    issuedAt: now() - 10,
    expiresAt: now() + 3600,
    key: key.toString('base64url'),
    policy: { earlySeconds: 0, antiPassback: true },
    credentials: [[print('CODE-1'), 'T-1', 'Élise', null, null, 1, 0, false, false]],
});
const urls = { scan: '/_checkin/scan', manifest: '/_checkin/manifest', sync: '/_checkin/sync' };

let online;
let requests;
let answers;

beforeEach(() => {
    online = true;
    requests = [];
    answers = {};
    Object.defineProperty(globalThis.navigator, 'onLine', { get: () => online, configurable: true });
    globalThis.fetch = async (url, init) => {
        const path = url.split('?')[0];
        requests.push([init.method, url, init.body ? JSON.parse(init.body) : null, init.headers]);
        const answer = answers[path] ?? { status: 404, body: { error: 'disabled' } };

        if (answer instanceof Error) {
            throw answer;
        }

        return { status: answer.status, json: async () => answer.body };
    };
});

afterEach(() => {
    delete globalThis.navigator.onLine;
    delete globalThis.fetch;
});

/** A window, its document and the form of the desk, with the targets the test asks for. */
function page({ indexedDB = fakeIndexedDB(), localStorage = new MemoryStorage(), lang = 'en' } = {}) {
    const window = new EventTarget();
    window.indexedDB = indexedDB;
    window.localStorage = localStorage;
    const element = new EventTarget();
    element.dataset = {};
    element.ownerDocument = { defaultView: window, documentElement: { lang }, cookie: '' };
    globalThis.document = element.ownerDocument;

    return { window, element };
}

async function desk(config, { targets = {}, ...options } = {}) {
    const { window, element } = options.page ?? page(options);
    const controller = new CheckinDeskController();
    controller.element = element;
    controller.configValue = config;
    const code = targets.code ?? { value: '', focused: 0, focus: () => (code.focused += 1) };
    const result = { textContent: '' };
    const state = { textContent: '' };
    Object.assign(controller, {
        hasCodeTarget: false !== targets.code,
        codeTarget: code,
        hasDirectionTarget: undefined !== targets.direction,
        directionTarget: targets.direction,
        hasResultTarget: false !== targets.result,
        resultTarget: result,
        hasStateTarget: false !== targets.state,
        stateTarget: state,
    });
    controller.connect();
    await controller.ready;

    return { controller, window, element, code, result, state };
}

/** Waits until a condition holds, two seconds at most. */
async function until(condition) {
    for (let waited = 0; !condition(); waited += 5) {
        assert.ok(waited < 2000, 'the condition never held');
        await new Promise((resolve) => setTimeout(resolve, 5));
    }
}

const offlineConfig = { checkpoint: 'hall', offline: true, urls, csrfCookie: 'csrf-token', device: 'door-1' };

test('a code typed is sent, trimmed, and its outcome announced and dispatched', async (t) => {
    answers['/_checkin/scan'] = {
        status: 200,
        body: { outcome: 'admitted', credential: { id: 'T-1', label: 'Élise' }, attendance: { entries: 1 } },
    };
    const { controller, element, code, result, state } = await desk({ checkpoint: 'hall', urls, device: 'door-1' });
    t.after(() => controller.disconnect());
    code.value = '  CODE-1  ';
    let prevented = 0;

    controller.submit({ preventDefault: () => (prevented += 1) });
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.equal(prevented, 1);
    assert.equal(code.value, '');
    assert.equal(code.focused, 1);
    const [method, url, body, headers] = requests.at(-1);
    assert.deepEqual([method, url], ['POST', '/_checkin/scan']);
    assert.equal(body.code, 'CODE-1');
    assert.equal(body.checkpoint, 'hall');
    assert.equal(body.device, 'door-1');
    assert.equal(body.direction, 'in');
    assert.match(body.id, /^[0-9a-f]{32}$/);
    assert.equal(headers['X-Phil-Checkin'], '1');
    assert.equal(result.textContent, 'Admitted — Élise');
    assert.equal(element.dataset.outcome, 'admitted');
    assert.equal(state.textContent, 'Online');
    assert.deepEqual(controller.dispatched.at(-1), [
        'checked',
        { outcome: 'admitted', offline: false, kept: true, credential: { id: 'T-1', label: 'Élise' } },
    ]);
});

test('a reader hands its code over by an event, and the direction is read from its field', async (t) => {
    answers['/_checkin/scan'] = { status: 200, body: { outcome: 'checked_out', credential: null } };
    const { controller, element, result } = await desk(
        { checkpoint: 'hall', urls },
        { targets: { direction: { value: 'out' } } },
    );
    t.after(() => controller.disconnect());

    element.dispatchEvent(new CustomEvent('phil-checkin:code', { detail: { code: 'CODE-1' } }));
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.equal(requests.at(-1)[2].direction, 'out');
    assert.equal(result.textContent, 'Checked out');
});

test('an empty code is not sent and a missing field submits nothing', async (t) => {
    const { controller } = await desk({ checkpoint: 'hall', urls }, { targets: { code: false } });
    t.after(() => controller.disconnect());
    let prevented = 0;

    controller.submit({ preventDefault: () => (prevented += 1) });
    await controller.check('');

    assert.equal(prevented, 1);
    assert.deepEqual(requests, []);
});

test('a busy server asks to scan again, a refusal without outcome is a refusal', async (t) => {
    const { controller, result } = await desk({ checkpoint: 'hall', urls });
    t.after(() => controller.disconnect());

    answers['/_checkin/scan'] = { status: 503, body: { error: 'busy' } };
    await controller.check('CODE-1');
    assert.equal(result.textContent, 'Busy, scan again');

    answers['/_checkin/scan'] = { status: 403, body: { error: 'forbidden' } };
    await controller.check('CODE-1');
    assert.equal(result.textContent, 'The scan was refused');
});

test('a server in error or a network that fails falls back to offline, and without a manifest says so', async (t) => {
    const { controller, result } = await desk({ checkpoint: 'hall', urls });
    t.after(() => controller.disconnect());

    answers['/_checkin/scan'] = { status: 500, body: {} };
    await controller.check('CODE-1');
    assert.equal(result.textContent, 'Offline, and no list of credentials on this device — (decided offline)');

    answers['/_checkin/scan'] = new TypeError('Failed to fetch');
    await controller.check('CODE-1');
    assert.equal(controller.dispatched.at(-1)[1].outcome, 'offlineNoManifest');

    const { controller: bare, result: bareResult } = await desk({ checkpoint: 'hall' });
    t.after(() => bare.disconnect());
    await bare.check('CODE-1');
    assert.equal(bareResult.textContent, 'Offline, and no list of credentials on this device — (decided offline)');
});

test('offline, a code is decided from the manifest kept in IndexedDB, journaled, and synchronised once back', async (t) => {
    answers['/_checkin/manifest'] = { status: 200, body: manifest() };
    const shared = page();
    const { controller, element, result, state } = await desk(offlineConfig, { page: shared });
    t.after(() => controller.disconnect());

    assert.equal(element.dataset.store, 'indexeddb');
    assert.ok(shared.window.indexedDB.values.has('phil-checkin:hall:manifest'));
    assert.deepEqual(requests.at(-1).slice(0, 2), ['GET', '/_checkin/manifest?checkpoint=hall&device=door-1']);

    online = false;
    await controller.check('CODE-1');
    assert.equal(result.textContent, 'Admitted — Élise — (decided offline)');
    assert.equal(element.dataset.pending, '1');
    assert.equal(state.textContent, 'Offline · 1 scans to send');

    await controller.check('CODE-1');
    assert.equal(element.dataset.outcome, 'already_admitted');
    await controller.check('NOPE');
    assert.equal(result.textContent, 'Unknown code — (decided offline)');
    assert.equal(element.dataset.pending, '2');

    // A page opened again on the same device finds the manifest, the attendance and the journal.
    const again = await desk(offlineConfig, { page: page({ indexedDB: shared.window.indexedDB }) });
    t.after(() => again.controller.disconnect());
    assert.equal(again.element.dataset.pending, '2');
    await again.controller.check('CODE-1');
    assert.equal(again.element.dataset.outcome, 'already_admitted');

    online = true;
    answers['/_checkin/sync'] = { status: 200, body: { scans: [] } };
    const journal = await again.controller.journal.all();
    answers['/_checkin/sync'].body.scans = journal.map((scan) => ({ id: scan.id, status: 'recorded' }));
    const synced = { ...manifest(), credentials: [[print('CODE-1'), 'T-1', 'Élise', null, null, 1, 1, true, false]] };
    answers['/_checkin/manifest'] = { status: 200, body: synced };
    again.window.dispatchEvent(new Event('online'));
    await until(() => '0' === again.element.dataset.pending && 'Online' === again.state.textContent);

    const sync = requests.find(([method, url]) => 'POST' === method && '/_checkin/sync' === url);
    assert.deepEqual(
        sync[2].scans.map((scan) => [scan.credential, scan.direction, scan.outcome]),
        [
            ['T-1', 'in', 'admitted'],
            ['T-1', 'in', 'already_admitted'],
            ['T-1', 'in', 'already_admitted'],
        ],
    );
    assert.equal(sync[2].device, 'door-1');
    assert.equal(again.element.dataset.pending, '0');
    assert.equal(again.state.textContent, 'Online');
    assert.equal(again.controller.manifest.entry(print('CODE-1')).entries, 1);
});

test('a fresh manifest downloaded while the journal could not be sent keeps the holders admitted offline', async (t) => {
    answers['/_checkin/manifest'] = { status: 200, body: manifest() };
    const { controller, element } = await desk(offlineConfig);
    t.after(() => controller.disconnect());

    online = false;
    await controller.check('CODE-1');
    online = true;
    answers['/_checkin/sync'] = { status: 500, body: {} };
    await controller.refresh();

    assert.equal(element.dataset.pending, '1');
    assert.equal(controller.manifest.entry(print('CODE-1')).inside, true);
    online = false;
    await controller.check('CODE-1');
    assert.equal(element.dataset.outcome, 'already_admitted');
});

test('the attendance a scan online answers is kept for the scans made offline after it', async (t) => {
    answers['/_checkin/manifest'] = { status: 200, body: manifest() };
    const { controller, element } = await desk(offlineConfig);
    t.after(() => controller.disconnect());
    answers['/_checkin/scan'] = {
        status: 200,
        body: { outcome: 'admitted', credential: { id: 'T-1', label: 'Élise' }, attendance: { entries: 1, inside: true } },
    };

    await controller.check('CODE-1');
    online = false;
    await controller.check('CODE-1');

    assert.equal(element.dataset.outcome, 'already_admitted');
});

test('an expired manifest decides nothing', async (t) => {
    answers['/_checkin/manifest'] = { status: 200, body: { ...manifest(), expiresAt: now() - 1 } };
    const { controller, result } = await desk(offlineConfig);
    t.after(() => controller.disconnect());
    online = false;

    await controller.check('CODE-1');

    assert.equal(result.textContent, 'Offline, and no list of credentials on this device — (decided offline)');
});

test('a code the manifest does not hold is offered to the listeners of phil-checkin:lookup', async (t) => {
    answers['/_checkin/manifest'] = { status: 200, body: manifest() };
    const { controller, element, result } = await desk(offlineConfig);
    t.after(() => controller.disconnect());
    const asked = [];
    element.addEventListener('phil-checkin:lookup', (event) => {
        asked.push([event.detail.code, event.detail.checkpoint]);

        if ('SIGNED-7' === event.detail.code) {
            event.detail.respond(Promise.resolve({ id: 'S-7', label: 'Noé', validUntil: now() + 60 }));
            event.detail.respond({ id: 'ignored' });
        }

        if ('BAD' === event.detail.code) {
            event.detail.respond({ id: 7 });
        }

        if ('FAILING' === event.detail.code) {
            event.detail.respond(Promise.reject(new Error('bad signature')));
        }
    });
    online = false;

    await controller.check('SIGNED-7');
    assert.equal(result.textContent, 'Admitted — Noé — (decided offline)');
    assert.equal((await controller.journal.all()).at(-1).credential, 'S-7');
    await controller.check('SIGNED-7');
    assert.equal(element.dataset.outcome, 'already_admitted', 'the attendance of a looked-up credential is kept');

    for (const code of ['BAD', 'FAILING', 'SILENT']) {
        await controller.check(code);
        assert.equal(element.dataset.outcome, 'unknown');
    }

    await controller.check('CODE-1');
    assert.deepEqual(asked, [
        ['SIGNED-7', 'hall'],
        ['SIGNED-7', 'hall'],
        ['BAD', 'hall'],
        ['FAILING', 'hall'],
        ['SILENT', 'hall'],
    ]);
});

test('a scan the device cannot keep says so', async (t) => {
    const indexedDB = fakeIndexedDB({ refuseWrites: true });
    const { controller, result } = await desk(offlineConfig, { indexedDB });
    t.after(() => controller.disconnect());
    controller.manifest = (await import('../../assets/src/lib/offline.js')).Manifest.parse(manifest());
    online = false;

    await controller.check('CODE-1');

    assert.equal(result.textContent, 'Admitted — Élise — (decided offline) — not kept on this device');
    assert.equal(controller.dispatched.at(-1)[1].kept, false);
});

test('without IndexedDB the device keeps its identifier in localStorage, and without either in memory', async (t) => {
    const localStorage = new MemoryStorage();
    const first = await desk({ checkpoint: 'hall' }, { indexedDB: fakeIndexedDB({ refuseOpen: true }), localStorage });
    t.after(() => first.controller.disconnect());
    assert.equal(first.element.dataset.store, 'local');
    assert.match(first.controller.device, /^[0-9a-f]{32}$/);
    assert.equal(JSON.parse(localStorage.getItem('phil-checkin:device')), first.controller.device);

    const second = await desk({ checkpoint: 'hall', device: 'bad device!' }, { indexedDB: null, localStorage });
    t.after(() => second.controller.disconnect());
    assert.equal(second.controller.device, first.controller.device);

    const memory = await desk({ checkpoint: 'hall' }, { indexedDB: null, localStorage: new MemoryStorage(true) });
    t.after(() => memory.controller.disconnect());
    assert.equal(memory.element.dataset.store, 'memory');
    assert.match(memory.controller.device, /^[0-9a-f]{32}$/);
});

test('the labels follow the language of the page, and the configuration overrides them', async (t) => {
    answers['/_checkin/scan'] = {
        status: 200,
        body: { outcome: 'already_admitted', credential: { id: 'T-1', label: null }, attendance: { lastEntryAt: 0 } },
    };
    const { controller, result } = await desk(
        { checkpoint: 'hall', urls, messages: { online: 'Connecté' } },
        { lang: 'fr-CA' },
    );
    t.after(() => controller.disconnect());

    await controller.check('CODE-1');

    assert.match(result.textContent, /^Déjà admis à /);
    assert.equal(controller.stateTarget.textContent, 'Connecté');
});

test('the controller works without result and state targets', async (t) => {
    answers['/_checkin/scan'] = { status: 200, body: { outcome: 'admitted' } };
    const { controller, element } = await desk({ checkpoint: 'hall', urls }, { targets: { result: false, state: false } });
    t.after(() => controller.disconnect());

    await controller.check('CODE-1');

    assert.equal(element.dataset.outcome, 'admitted');
    assert.equal(element.dataset.pending, '0');
});

test('once disconnected, the controller hears neither codes nor the network', async () => {
    answers['/_checkin/scan'] = { status: 200, body: { outcome: 'admitted' } };
    const { controller, element, window } = await desk({ checkpoint: 'hall', urls });
    controller.disconnect();
    const before = requests.length;

    element.dispatchEvent(new CustomEvent('phil-checkin:code', { detail: { code: 'CODE-1' } }));
    window.dispatchEvent(new Event('online'));
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.equal(requests.length, before);
});
