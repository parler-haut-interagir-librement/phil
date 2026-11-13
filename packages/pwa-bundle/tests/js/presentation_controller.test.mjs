// The controller that shows a page on a second screen, under Node's test runner: the Presentation API, the window and
// the BroadcastChannel are plain objects the test sets.

import assert from 'node:assert/strict';
import { test } from 'node:test';
import Presentation from '../../assets/src/presentation_controller.js';
import { Events, install } from './browser.mjs';

class Channel extends Events {
    static opened = [];

    posted = [];

    closed = false;

    constructor(name) {
        super();
        this.name = name;
        Channel.opened.push(this);
    }

    postMessage(message) {
        this.posted.push(message);
    }

    close() {
        this.closed = true;
    }
}

function connection() {
    const events = new Events();

    return Object.assign(events, {
        sent: [],
        terminated: 0,
        send: (data) => events.sent.push(data),
        terminate: () => events.terminated++,
    });
}

function browser({ request = undefined, receiver = undefined, channel = Channel } = {}) {
    Channel.opened = [];
    const window = {
        opened: [],
        open: (...args) => {
            const opened = {
                closed: false,
                closes: 0,
                close: () => {
                    opened.closes++;
                    opened.closed = true;
                },
            };
            window.opened.push([args, opened]);

            return opened;
        },
    };
    install({
        window,
        navigator: receiver === undefined ? {} : { presentation: { receiver } },
        PresentationRequest: request,
        BroadcastChannel: channel,
    });

    return window;
}

function controller({ receiver = false } = {}) {
    const presentation = new Presentation();
    presentation.urlValue = '/screen';
    presentation.channelValue = 'phil-pwa-presentation';
    presentation.receiverValue = receiver;

    return presentation;
}

const names = (presentation) => presentation.dispatched.map((event) => event.name);

test('with the Presentation API, it presents the page and sends messages as JSON', async () => {
    const opened = connection();
    const urls = [];
    browser({
        request: class {
            constructor(list) {
                urls.push(list);
            }

            async start() {
                return opened;
            }
        },
    });
    const presentation = controller();

    await presentation.start();
    presentation.send({ params: { message: { slide: 2 } } });
    presentation.send({ detail: 'next' });
    presentation.send({});
    presentation.send();
    opened.emit('message', { data: '{"ready":true}' });
    opened.emit('message', { data: 'plain text' });
    opened.emit('terminate');

    assert.deepEqual(urls, [['/screen']]);
    assert.deepEqual(opened.sent, ['{"slide":2}', '"next"', 'null', 'null']);
    assert.deepEqual(presentation.dispatched, [
        { name: 'connected', detail: { mode: 'presentation' } },
        { name: 'message', detail: { data: { ready: true } } },
        { name: 'message', detail: { data: 'plain text' } },
        { name: 'disconnected' },
    ]);
    assert.equal(Channel.opened.length, 0);

    presentation.stop();
    assert.equal(opened.terminated, 1);
});

test('when the person cancels the choice of a display, nothing opens', async () => {
    for (const name of ['NotAllowedError', 'AbortError']) {
        const window = browser({
            request: class {
                async start() {
                    throw Object.assign(new Error('cancelled'), { name });
                }
            },
        });
        const presentation = controller();

        await presentation.start();

        assert.deepEqual(names(presentation), ['cancelled']);
        assert.deepEqual(window.opened, []);
    }
});

test('without a display, or without the API, it opens a window and talks through a channel', async () => {
    for (const request of [
        class {
            async start() {
                throw Object.assign(new Error('none'), { name: 'NotFoundError' });
            }
        },
        undefined,
    ]) {
        const window = browser({ request });
        const presentation = controller();

        await presentation.start();
        presentation.send({ params: { message: { slide: 3 } } });
        await presentation.start();

        assert.deepEqual(window.opened[0][0], ['/screen', 'phil-pwa-presentation', 'popup']);
        assert.equal(Channel.opened.length, 1);
        assert.equal(Channel.opened[0].name, 'phil-pwa-presentation');
        assert.deepEqual(Channel.opened[0].posted, [{ slide: 3 }]);
        assert.deepEqual(presentation.dispatched[0], { name: 'connected', detail: { mode: 'window' } });

        Channel.opened[0].emit('message', { data: { ready: true } });
        assert.deepEqual(presentation.dispatched.at(-1), { name: 'message', detail: { data: { ready: true } } });

        assert.equal(window.opened.length, 1);
        window.opened[0][1].closed = true;
        await presentation.start();
        assert.equal(window.opened.length, 2);

        presentation.stop();
        assert.equal(window.opened[1][1].closes, 1);
        assert.equal(window.opened[0][1].closes, 0);
        assert.equal(Channel.opened[0].closed, true);
        assert.equal(presentation.channel, null);
        assert.deepEqual(presentation.dispatched.at(-1), { name: 'disconnected' });
        presentation.send({ detail: 'lost' });
    }
});

test('without a channel either, the window opens and messages are dropped', async () => {
    browser({ channel: undefined });
    const presentation = controller();

    await presentation.start();
    presentation.send({ detail: 'lost' });
    presentation.stop();

    assert.deepEqual(names(presentation), ['connected', 'disconnected']);
});

test('a receiver listens to the presentation connections and to the channel', async () => {
    const first = connection();
    const later = connection();
    const list = Object.assign(new Events(), { connections: [first] });
    browser({ receiver: { connectionList: Promise.resolve(list) } });
    const receiver = controller({ receiver: true });

    receiver.connect();
    await Promise.resolve();
    await Promise.resolve();
    list.emit('connectionavailable', { connection: later });
    first.emit('message', { data: '{"slide":1}' });
    later.emit('message', { data: '{"slide":2}' });
    Channel.opened[0].emit('message', { data: { slide: 3 } });
    receiver.send({ detail: { ready: true } });

    assert.deepEqual(
        receiver.dispatched.map((event) => event.detail.data),
        [{ slide: 1 }, { slide: 2 }, { slide: 3 }],
    );
    assert.deepEqual(later.sent, ['{"ready":true}']);

    receiver.disconnect();
    assert.equal(Channel.opened[0].closed, true);
});

test('a receiver without the Presentation API listens to the channel only; a presenter listens to nothing', () => {
    browser();
    const receiver = controller({ receiver: true });
    receiver.connect();
    assert.equal(Channel.opened.length, 1);

    browser();
    controller().connect();
    assert.equal(Channel.opened.length, 0);
});
