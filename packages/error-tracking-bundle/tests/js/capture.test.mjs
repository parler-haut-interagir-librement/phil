// The browser side of the capture, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.

import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

import { MAX_MESSAGE, MAX_STACK, pageOf, reportOf, send } from '../../assets/src/capture.js';
import CaptureController from '../../assets/src/capture_controller.js';

const location = { origin: 'https://example.com', pathname: '/cart', search: '?coupon=SECRET', hash: '#pay' };
const saved = Object.getOwnPropertyDescriptor(globalThis, 'location');

afterEach(() => {
    if (saved) {
        Object.defineProperty(globalThis, 'location', saved);
    } else {
        delete globalThis.location;
    }
});

test('the page keeps its origin and path only', () => {
    assert.equal(pageOf(location), 'https://example.com/cart');
});

test('an error is reported with its type, message and stack', () => {
    const error = new TypeError('x is undefined');
    const report = reportOf(error, 'ignored', location);

    assert.equal(report.type, 'TypeError');
    assert.equal(report.message, 'x is undefined');
    assert.equal(report.url, 'https://example.com/cart');
    assert.equal(report.stack, error.stack);
});

test('a rejection with something else than an error', () => {
    assert.deepEqual(reportOf('quota exceeded', null, location), {
        type: 'Error',
        message: 'quota exceeded',
        url: 'https://example.com/cart',
        stack: '',
    });
    assert.deepEqual(reportOf(undefined, 'Uncaught', location)?.message, 'Uncaught');
    assert.equal(reportOf(undefined, null, location), null);
    assert.equal(reportOf(null, 'Script error.', location), null);
});

test('the texts are bounded', () => {
    const error = new Error('m'.repeat(MAX_MESSAGE + 10));
    error.stack = 's'.repeat(MAX_STACK + 10);
    const report = reportOf(error, null, location);

    assert.equal(report.message.length, MAX_MESSAGE);
    assert.equal(report.stack.length, MAX_STACK);
});

test('a report is sent once, without credentials, with the token', () => {
    const calls = [];
    const fetchFunction = (url, init) => {
        calls.push([url, init]);

        return Promise.resolve();
    };

    assert.equal(send('/_errors/browser', { message: 'm' }, 'abc', fetchFunction), true);
    assert.equal(calls[0][0], '/_errors/browser');
    assert.equal(calls[0][1].credentials, 'omit');
    assert.equal(calls[0][1].keepalive, true);
    assert.equal(calls[0][1].headers['X-Error-Tracking-Token'], 'abc');
    assert.equal(calls[0][1].body, '{"message":"m"}');

    send('/e', { message: 'm' }, '', fetchFunction);
    assert.equal(calls[1][1].headers['X-Error-Tracking-Token'], undefined);
    assert.equal(send('/e', { message: 'm' }, '', null), false);
});

test('the controller reports each message once, up to its limit', () => {
    Object.defineProperty(globalThis, 'location', { value: location, configurable: true, writable: true });
    const controller = new CaptureController();
    controller.endpointValue = '/e';
    controller.tokenValue = '';
    controller.limitValue = 2;
    const queued = [];
    const original = globalThis.fetch;
    globalThis.fetch = (url, init) => {
        queued.push(JSON.parse(init.body).message);

        return Promise.resolve();
    };

    const listeners = new Map();
    globalThis.addEventListener = (type, listener) => listeners.set(type, listener);
    globalThis.removeEventListener = (type, listener) => listeners.get(type) === listener && listeners.delete(type);

    try {
        controller.connect();
        listeners.get('error')({ error: new Error('a'), message: 'Uncaught Error: a' });
        listeners.get('error')({ error: new Error('a'), message: 'Uncaught Error: a' });
        listeners.get('unhandledrejection')({ reason: 'b' });
        controller.report(new Error('c'), null);
        controller.disconnect();
    } finally {
        globalThis.fetch = original;
        delete globalThis.addEventListener;
        delete globalThis.removeEventListener;
    }

    assert.deepEqual(queued, ['a', 'b']);
    assert.equal(listeners.size, 0);
});
