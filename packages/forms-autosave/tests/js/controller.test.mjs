import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

import Autosave from '../../assets/src/autosave_controller.js';
import { storageKey } from '../../assets/src/lib/storage.js';
import { ENGLISH } from '../../assets/src/lib/ui.js';
import { FakeElement, FakeStorage, field, form } from './fakes.mjs';

const KEY = 'abcdefghijklmnopqrstuvwxyz012345';
const ID = 'phil--forms-autosave--autosave';
const realFetch = globalThis.fetch;

afterEach(() => {
    globalThis.fetch = realFetch;
});

function page(storage = new FakeStorage(), cookie = '') {
    const view = new FakeElement('window');
    Object.defineProperty(view, 'localStorage', { get: () => storage, configurable: true });

    return { cookie, location: { protocol: 'https:' }, defaultView: view, createElement: (tag) => new FakeElement(tag) };
}

function mount(fields, values = {}, doc = page()) {
    const element = form(fields);
    element.ownerDocument = doc;
    const controller = new Autosave(element, {
        keyValue: KEY,
        versionValue: 'v1',
        ttlValue: 3600,
        debounceValue: 800,
        serverDebounceValue: 5000,
        maxBytesValue: 4096,
        endpointValue: '',
        csrfValue: '',
        invalidValue: false,
        labelsValue: {},
        ...values,
    });
    controller.connect();

    return { controller, element, doc, part: (name) => element.querySelector(`[data-${ID}-target~="${name}"]`) };
}

function stored(storage) {
    return JSON.parse(storage.getItem(storageKey(KEY)));
}

function draft(fields, extra = {}) {
    return JSON.stringify({ v: 1, version: 'v1', savedAt: Date.now() - 1000, revision: null, fields, ...extra });
}

test('without a template, a status and a notice are added at the top of the form', () => {
    const { element, part } = mount([field('text', 'title')]);

    assert.equal(element.children[0].getAttribute('class'), 'phil-autosave');
    assert.equal(part('status').textContent, '');
    assert.equal(part('notice').hidden, true);
});

test('a template status is used as it is', () => {
    const status = new FakeElement('p', { [`data-${ID}-target`]: 'status' });
    const element = form([field('text', 'title')]);
    element.append(status);
    element.ownerDocument = page();
    new Autosave(element, { keyValue: KEY, versionValue: 'v1', ttlValue: 60, debounceValue: 800, serverDebounceValue: 5000, maxBytesValue: 4096, endpointValue: '', csrfValue: '', invalidValue: false, labelsValue: {} }).connect();

    assert.equal(element.children.length, 2);
});

test('a change is saved once the typing stops, and at once on submit or when the page goes', () => {
    const storage = new FakeStorage();
    const doc = page(storage);
    const title = field('text', 'title', { value: 'Hello' });
    const pass = field('password', 'pass', { value: 'secret' });
    const { controller, element, part } = mount([title, pass], { labelsValue: { saved: 'Gardé.' } }, doc);

    element.emit('input');
    assert.equal(storage.getItem(storageKey(KEY)), null);
    assert.equal(controller.local.pending, true);
    element.emit('submit');
    assert.deepEqual(stored(storage).fields, { title: ['Hello'] });
    assert.equal(stored(storage).version, 'v1');
    assert.equal(stored(storage).revision, null);
    assert.equal(part('status').textContent, 'Gardé.');

    title.value = 'Bye';
    element.emit('change');
    doc.defaultView.emit('pagehide');
    assert.deepEqual(stored(storage).fields, { title: ['Bye'] });

    controller.disconnect();
    element.emit('input');
    assert.equal(controller.local.pending, false, 'no listener is left once disconnected');
});

test('a draft is restored with a notice, and discarding puts the shown values back', () => {
    const storage = new FakeStorage({ [storageKey(KEY)]: draft({ title: ['Draft'] }) });
    const title = field('text', 'title', { value: 'Shown' });
    const { controller, part } = mount([title], {}, page(storage));

    assert.equal(title.value, 'Draft');
    assert.deepEqual(title.events, ['change']);
    assert.equal(part('notice').hidden, false);
    assert.equal(part('message').textContent, ENGLISH.restored);
    assert.equal(part('discard').hidden, false);
    assert.equal(part('useServer').hidden, true);
    assert.equal(part('status').textContent, ENGLISH.restored);
    assert.deepEqual(controller.dispatched, [['restored', { fields: { title: ['Draft'] } }]]);

    controller.discard();
    assert.equal(title.value, 'Shown');
    assert.equal(storage.getItem(storageKey(KEY)), null);
    assert.equal(part('notice').hidden, true);
    assert.equal(part('status').textContent, ENGLISH.discarded);
    assert.deepEqual(controller.dispatched.at(-1), ['discarded', null]);
});

test('a draft equal to the shown values says nothing; dismissing hides the notice', () => {
    const storage = new FakeStorage({ [storageKey(KEY)]: draft({ title: ['Same'] }) });
    const same = mount([field('text', 'title', { value: 'Same' })], {}, page(storage));
    assert.equal(same.part('notice').hidden, true);

    storage.setItem(storageKey(KEY), draft({ title: ['Other'] }));
    const other = mount([field('text', 'title', { value: 'Shown' })], {}, page(storage));
    other.controller.dismiss();
    assert.equal(other.part('notice').hidden, true);
    other.controller.useServer();
    assert.equal(other.part('status').textContent, ENGLISH.restored, 'nothing to use without a newer draft');
});

test('after an invalid submission, the submitted values are kept, not overwritten by the draft', () => {
    const storage = new FakeStorage({ [storageKey(KEY)]: draft({ title: ['Old draft'] }) });
    const title = field('text', 'title', { value: 'Submitted' });
    const { part } = mount([title], { invalidValue: true }, page(storage));

    assert.equal(title.value, 'Submitted');
    assert.deepEqual(stored(storage).fields, { title: ['Submitted'] });
    assert.equal(part('notice').hidden, true);
});

test('the drafts of sent forms are forgotten before anything is restored', () => {
    const storage = new FakeStorage({ [storageKey(KEY)]: draft({ title: ['Draft'] }) });
    const title = field('text', 'title', { value: '' });
    mount([title], {}, page(storage, `phil_forms_autosave_done=${KEY}`));

    assert.equal(title.value, '');
    assert.equal(storage.getItem(storageKey(KEY)), null);
});

test('a browser that refuses storage is told, and a draft too large is not kept', () => {
    const refused = page();
    Object.defineProperty(refused.defaultView, 'localStorage', {
        get: () => {
            throw new Error('SecurityError');
        },
    });
    assert.equal(mount([field('text', 'title')], {}, refused).part('status').textContent, ENGLISH.unavailable);

    const storage = new FakeStorage();
    const { controller, part } = mount([field('text', 'title', { value: 'x'.repeat(200) })], { maxBytesValue: 100 }, page(storage));
    controller.save();
    assert.equal(part('status').textContent, ENGLISH.tooLarge);
    assert.equal(storage.entries.size, 0);
});

function serverAnswering(...answers) {
    const calls = [];
    globalThis.fetch = async (url, init) => {
        calls.push([url, init.method, init.body ?? null]);
        const [status, body] = answers.shift() ?? [500, null];

        return { status, json: async () => body };
    };

    return calls;
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

test('with the server store, a draft is restored from the server when the browser has none', async () => {
    const calls = serverAnswering([200, { revision: 3, version: 'v1', fields: { title: ['From afar'] } }]);
    const title = field('text', 'title', { value: '' });
    const { controller } = mount([title], { endpointValue: '/d/k', csrfValue: 'csrf' });
    await settle();

    assert.deepEqual(calls, [['/d/k', 'GET', null]]);
    assert.equal(title.value, 'From afar');
    assert.equal(controller.revision, 3);
    controller.disconnect();
});

test('a server draft of another structure, or none, is ignored; an equal one gives its revision', async () => {
    serverAnswering([200, { revision: 3, version: 'v0', fields: { title: ['Old'] } }]);
    const title = field('text', 'title', { value: '' });
    const first = mount([title], { endpointValue: '/d/k' });
    await settle();
    assert.equal(title.value, '');
    assert.equal(first.controller.revision, null);

    serverAnswering([200, { revision: 5, version: 'v1', fields: { title: [''] } }]);
    const second = mount([title], { endpointValue: '/d/k' });
    await settle();
    assert.equal(second.controller.revision, 5);
    assert.equal(second.part('notice').hidden, true);
    first.controller.disconnect();
    second.controller.disconnect();
});

test('a newer server draft than the browser read is told as a conflict, and can be used or set aside', async () => {
    const storage = new FakeStorage({ [storageKey(KEY)]: draft({ title: ['Here'] }, { revision: 2 }) });
    serverAnswering([200, { revision: 4, version: 'v1', fields: { title: ['There'] } }]);
    const title = field('text', 'title', { value: '' });
    const { controller, part } = mount([title], { endpointValue: '/d/k' }, page(storage));
    await settle();

    assert.equal(title.value, 'Here');
    assert.equal(part('message').textContent, ENGLISH.conflict);
    assert.equal(part('useServer').hidden, false);
    assert.equal(part('discard').hidden, true);
    controller.useServer();
    assert.equal(title.value, 'There');
    assert.equal(controller.revision, 4);
    assert.equal(stored(storage).revision, 4);
    assert.equal(part('notice').hidden, true);
    controller.disconnect();

    storage.setItem(storageKey(KEY), draft({ title: ['Mine'] }, { revision: 2 }));
    serverAnswering([200, { revision: 6, version: 'v1', fields: { title: ['Theirs'] } }]);
    const kept = mount([field('text', 'title', { value: '' })], { endpointValue: '/d/k' }, page(storage));
    await settle();
    kept.controller.dismiss();
    assert.equal(kept.controller.revision, 6);
    assert.equal(kept.controller.conflict, null);
    assert.deepEqual(stored(storage).fields, { title: ['Mine'] });
    kept.controller.disconnect();
});

test('a save is pushed to the server by revision; a conflict reads the server again', async () => {
    const storage = new FakeStorage();
    const calls = serverAnswering([204, null], [200, { revision: 1 }], [409, { revision: 3 }], [200, { revision: 3, version: 'v1', fields: { title: ['Else'] } }], [413, null], [500, null]);
    const title = field('text', 'title', { value: 'Mine' });
    const { controller, part } = mount([title], { endpointValue: '/d/k', csrfValue: 'csrf' }, page(storage));
    await settle();

    controller.save();
    assert.equal(controller.remote.pending, true);
    controller.remote.cancel();
    await controller.push();
    assert.deepEqual(calls[1], ['/d/k', 'PUT', '{"revision":null,"version":"v1","fields":{"title":["Mine"]}}']);
    assert.equal(controller.revision, 1);
    assert.equal(stored(storage).revision, 1);
    assert.equal(part('status').textContent, ENGLISH.savedServer);
    assert.equal(controller.remote.pending, false);

    await controller.push();
    assert.equal(part('message').textContent, ENGLISH.conflict);

    await controller.push();
    assert.equal(part('status').textContent, ENGLISH.tooLarge);
    await controller.push();
    assert.equal(part('status').textContent, ENGLISH.tooLarge, 'a failure says nothing new');
    controller.disconnect();
});

test('discarding also discards the server draft', async () => {
    const calls = serverAnswering([204, null], [204, null]);
    const { controller } = mount([field('text', 'title', { value: '' })], { endpointValue: '/d/k' });
    await settle();
    controller.discard();
    await settle();

    assert.deepEqual(calls.map((call) => call[1]), ['GET', 'DELETE']);
    controller.disconnect();
});
