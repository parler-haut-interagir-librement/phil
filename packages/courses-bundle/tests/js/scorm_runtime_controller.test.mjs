import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import Controller from '../../assets/src/scorm_runtime_controller.js';

let requests;
let beacons;
let answers;
let listeners;
let posted;
let assigned;
let submitted;

function frame() {
    return {
        src: '',
        contentWindow: {
            postMessage: (message, origin) => posted.push([message, origin]),
        },
    };
}

beforeEach(() => {
    requests = [];
    beacons = [];
    answers = [];
    listeners = {};
    posted = [];
    assigned = [];
    submitted = [];
    globalThis.window = {
        addEventListener: (name, listener) => {
            listeners[name] = listener;
        },
        removeEventListener: (name) => {
            delete listeners[name];
        },
        location: { assign: (url) => assigned.push(url) },
    };
    globalThis.fetch = async (url, options) => {
        requests.push([url, options]);
        const answer = answers.shift() ?? { status: 200, body: { errors: {}, token: 'next' } };

        if (answer instanceof Error) {
            throw answer;
        }

        return { status: answer.status, json: async () => answer.body };
    };
    Object.defineProperty(globalThis, 'navigator', {
        value: { sendBeacon: (url, blob) => beacons.push([url, blob]) > 0 },
        configurable: true,
    });
    globalThis.document = {
        createElement: () => {
            const element = { append: () => {}, requestSubmit: () => submitted.push(element) };

            return element;
        },
        body: { append: () => {} },
    };
});

function controller() {
    const instance = new Controller();
    Object.assign(instance, {
        frameTarget: frame(),
        statusTarget: { textContent: '' },
        hasStatusTarget: true,
        frameUrlValue: 'https://content.example.org/_phil/scorm/frame.html',
        contentOriginValue: 'https://content.example.org',
        commitUrlValue: '/courses/scorm/commit',
        launchUrlValue: '/courses/c/activities/00000000-0000-0000-0000-000000000000/launch',
        outlineUrlValue: '/courses/c',
        tokenValue: 'first',
        csrfValue: 'csrf-token',
        stateValue: { version: '2004' },
        messagesValue: { saved: 'Saved', refused: 'Refused', error: 'Error', expired: 'Expired', ended: 'Ended' },
        renewValue: 600,
    });
    instance.connect();

    return instance;
}

function message(instance, data, origin = 'https://content.example.org', source = instance.frameTarget.contentWindow) {
    listeners.message({ origin, source, data: { phil: 'scorm', ...data } });
}

test('the frame is handed the state once ready, from the content origin only', () => {
    const instance = controller();
    assert.equal(instance.frameTarget.src, 'https://content.example.org/_phil/scorm/frame.html');

    message(instance, { type: 'ready' }, 'https://evil.example.net');
    message(instance, { type: 'ready' }, 'https://content.example.org', {});
    listeners.message({ origin: 'https://content.example.org', source: instance.frameTarget.contentWindow, data: {} });
    assert.deepEqual(posted, []);

    message(instance, { type: 'ready' });
    assert.deepEqual(posted, [
        [{ phil: 'scorm', type: 'init', state: { version: '2004' } }, 'https://content.example.org'],
    ]);
    instance.disconnect();
});

test('the writes are committed in order, and the token renewed', async () => {
    const instance = controller();
    message(instance, { type: 'set', element: 'cmi.location', value: 'p1' });
    message(instance, { type: 'set', element: 'cmi.score.raw', value: '7' });
    message(instance, { type: 'set', element: 'cmi.location', value: 'p2' });
    message(instance, { type: 'set', element: 'cmi.bad', value: 3 });
    message(instance, { type: 'commit' });
    await instance.queue;

    assert.equal(requests.length, 1);
    const [url, options] = requests[0];
    assert.equal(url, '/courses/scorm/commit');
    assert.equal(options.method, 'POST');
    assert.equal(options.credentials, 'same-origin');
    assert.equal(options.keepalive, true);
    assert.deepEqual(JSON.parse(options.body), {
        token: 'first',
        csrf: 'csrf-token',
        values: { 'cmi.location': 'p2', 'cmi.score.raw': '7' },
        finish: false,
    });
    assert.equal(instance.tokenValue, 'next');
    assert.equal(instance.statusTarget.textContent, 'Saved');
    assert.deepEqual(instance.dispatched, [['committed', { errors: {}, ended: false }]]);
    instance.disconnect();
});

test('values refused are said, values not sent are sent again', async () => {
    const instance = controller();
    answers.push({ status: 200, body: { errors: { 'cmi.x': 401 } } }, new Error('offline'), { status: 500, body: {} });
    message(instance, { type: 'set', element: 'cmi.location', value: 'p1' });
    await instance.flush(false);
    assert.equal(instance.statusTarget.textContent, 'Refused');
    assert.equal(instance.tokenValue, 'first');

    message(instance, { type: 'set', element: 'cmi.location', value: 'p2' });
    await instance.flush(false);
    assert.equal(instance.statusTarget.textContent, 'Error');
    message(instance, { type: 'set', element: 'cmi.suspend_data', value: 's' });
    assert.deepEqual(
        [...instance.pending],
        [
            ['cmi.location', 'p2'],
            ['cmi.suspend_data', 's'],
        ],
    );

    await instance.flush(false);
    assert.equal(instance.statusTarget.textContent, 'Error');
    assert.deepEqual(JSON.parse(requests[2][1].body).values, { 'cmi.location': 'p2', 'cmi.suspend_data': 's' });
    assert.deepEqual(
        [...instance.pending],
        [
            ['cmi.location', 'p2'],
            ['cmi.suspend_data', 's'],
        ],
    );
    instance.disconnect();
});

test('the end of a session goes to the next activity, or the outline', async () => {
    const next = controller();
    answers.push({ status: 200, body: { errors: {}, token: null, ended: true, next: 'a2' } });
    message(next, { type: 'terminate' });
    await next.queue;
    assert.equal(JSON.parse(requests[0][1].body).finish, true);
    assert.equal(submitted[0].action, '/courses/c/activities/a2/launch');
    assert.equal(submitted[0].method, 'post');
    assert.equal(next.ended, true);
    message(next, { type: 'commit' });
    await next.queue;
    assert.equal(requests.length, 1);
    next.leave();
    assert.deepEqual(beacons, []);

    const exit = controller();
    answers.push({ status: 200, body: { errors: {}, ended: true, exit: true } });
    await exit.flush(true);
    assert.deepEqual(assigned, ['/courses/c']);
});

test('a session refused or over stops committing', async () => {
    for (const [status, said] of [
        [409, 'Ended'],
        [403, 'Expired'],
    ]) {
        const instance = controller();
        answers.push({ status, body: { error: 'x' } });
        await instance.flush(false);
        assert.equal(instance.statusTarget.textContent, said);
        assert.equal(instance.ended, true);
    }
});

test('leaving the page ends the session with a beacon, else with fetch', async () => {
    const instance = controller();
    message(instance, { type: 'set', element: 'cmi.location', value: 'p9' });
    listeners.pagehide();
    assert.equal(beacons.length, 1);
    assert.equal(beacons[0][0], '/courses/scorm/commit');
    assert.equal(beacons[0][1].type, 'text/plain;charset=utf-8');
    assert.deepEqual(JSON.parse(await beacons[0][1].text()), {
        token: 'first',
        csrf: 'csrf-token',
        values: { 'cmi.location': 'p9' },
        finish: true,
    });
    instance.leave();
    assert.equal(beacons.length, 1);

    Object.defineProperty(globalThis, 'navigator', { value: { sendBeacon: () => false }, configurable: true });
    const fallback = controller();
    fallback.leave();
    assert.equal(requests.length, 1);
    assert.equal(requests[0][1].keepalive, true);
    assert.equal(JSON.parse(requests[0][1].body).finish, true);
});
