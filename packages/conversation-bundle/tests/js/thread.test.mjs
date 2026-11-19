// The conversation controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.

import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import ThreadController from '../../assets/src/thread_controller.js';

const sources = [];
const fetched = [];
let responses = [];

class FakeEventSource {
    constructor(url, options) {
        this.url = url;
        this.options = options;
        this.closed = false;
        sources.push(this);
    }

    close() {
        this.closed = true;
    }
}

const controller = ({ hub = 'https://hub.test/.well-known/mercure?topic=urn', target = true } = {}) => {
    const instance = new ThreadController();
    instance.hubValue = hub;
    instance.conversationValue = 'c1';
    instance.urlValue = '/messages/c1/messages';
    instance.hasMessagesTarget = target;
    instance.messagesTarget = { innerHTML: '<ol>old</ol>' };

    return instance;
};

beforeEach(() => {
    sources.length = 0;
    fetched.length = 0;
    responses = [];
    globalThis.EventSource = FakeEventSource;
    globalThis.fetch = async (url, options) => {
        fetched.push([url, options]);
        const next = responses.shift() ?? { ok: true, text: '<ol>new</ol>' };

        if (next instanceof Error) {
            throw next;
        }

        return { ok: next.ok, text: async () => next.text };
    };
});

afterEach(() => {
    delete globalThis.EventSource;
    delete globalThis.fetch;
});

test('it subscribes to the hub with the cookie and stops on disconnect', () => {
    const instance = controller();
    instance.connect();

    assert.equal(sources.length, 1);
    assert.equal(sources[0].url, 'https://hub.test/.well-known/mercure?topic=urn');
    assert.deepEqual(sources[0].options, { withCredentials: true });

    instance.disconnect();
    assert.equal(sources[0].closed, true);
    assert.equal(instance.source, null);
    instance.disconnect();
});

test('it does nothing without a hub or without EventSource', () => {
    controller({ hub: '' }).connect();
    assert.equal(sources.length, 0);

    delete globalThis.EventSource;
    controller().connect();
    assert.equal(sources.length, 0);
});

test('an update about its conversation loads the messages again', async () => {
    const instance = controller();
    instance.connect();

    await sources[0].onmessage({ data: JSON.stringify({ conversation: 'c1', message: 'm2', sequence: 2 }) });

    assert.deepEqual(fetched, [['/messages/c1/messages', { headers: { Accept: 'text/html' }, credentials: 'same-origin' }]]);
    assert.equal(instance.messagesTarget.innerHTML, '<ol>new</ol>');
    assert.deepEqual(instance.dispatched, [['updated', { message: 'm2' }]]);
});

test('an update about another conversation, or that is not JSON, is left alone', async () => {
    const instance = controller();

    await instance.received({ data: JSON.stringify({ conversation: 'c2', message: 'm2' }) });
    await instance.received({ data: 'not json' });
    await instance.received({ data: 'null' });
    await controller({ target: false }).received({ data: JSON.stringify({ conversation: 'c1' }) });

    assert.equal(fetched.length, 0);
    assert.equal(instance.messagesTarget.innerHTML, '<ol>old</ol>');
});

test('a failed load leaves the messages as they were', async () => {
    const instance = controller();
    responses = [{ ok: false, text: 'Not found' }, new Error('offline')];

    await instance.received({ data: JSON.stringify({ conversation: 'c1', message: 'm2' }) });
    await instance.received({ data: JSON.stringify({ conversation: 'c1', message: 'm3' }) });

    assert.equal(fetched.length, 2);
    assert.equal(instance.messagesTarget.innerHTML, '<ol>old</ol>');
    assert.deepEqual(instance.dispatched, []);
});
