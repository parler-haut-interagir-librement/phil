// The service worker, run in a context of its own with a fake `self`: what it shows, and what a click opens.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../assets/worker/push-worker.js', import.meta.url), 'utf8');

function worker() {
    const listeners = {};
    const shown = [];
    const opened = [];
    const self = {
        location: { origin: 'https://site.test' },
        addEventListener: (type, listener) => {
            listeners[type] = listener;
        },
        registration: {
            showNotification: (title, options) => {
                shown.push({ title, options });

                return Promise.resolve();
            },
        },
        clients: {
            openWindow: (url) => {
                opened.push(url);

                return Promise.resolve();
            },
        },
    };
    vm.runInNewContext(source, { self, URL });

    return { listeners, shown, opened };
}

function push(listeners, data) {
    const waited = [];
    listeners.push({ data, waitUntil: (promise) => waited.push(promise) });

    return waited;
}

const json = (value) => ({ json: () => value });

test('a message is shown, tagged with its notification', () => {
    const { listeners, shown } = worker();
    const waited = push(listeners, json({ id: 'n1', type: 'comment.replied', title: 'A reply', body: 'Ada answered.', url: '/c/9', count: 1 }));

    assert.equal(waited.length, 1);
    assert.equal(shown.length, 1);
    assert.equal(shown[0].title, 'A reply');
    assert.equal(shown[0].options.body, 'Ada answered.');
    assert.equal(shown[0].options.tag, 'n1');
    assert.equal(shown[0].options.data.url, 'https://site.test/c/9');
});

test('a message without body, id or page still shows', () => {
    const { listeners, shown } = worker();
    push(listeners, json({ title: 'Hi', body: 5, id: 7, url: null }));

    assert.equal(shown[0].options.body, '');
    assert.equal(shown[0].options.tag, undefined);
    assert.equal(shown[0].options.data.url, null);
});

test('a page of another site, or no address, opens nothing', () => {
    const { listeners, shown } = worker();
    push(listeners, json({ title: 'A', url: 'https://evil.test/x' }));
    push(listeners, json({ title: 'B', url: 'javascript:alert(1)' }));
    push(listeners, json({ title: 'C', url: '' }));
    push(listeners, json({ title: 'D', url: 'http://[' }));
    push(listeners, json({ title: 'E', url: 'https://site.test/ok' }));

    assert.deepEqual(
        shown.map((s) => s.options.data.url),
        [null, null, null, null, 'https://site.test/ok'],
    );
});

test('what is no message is not shown', () => {
    const { listeners, shown } = worker();

    for (const data of [null, json(null), json('x'), json({ body: 'no title' }), json({ title: 5 }), { json: () => { throw new SyntaxError('x'); } }]) {
        assert.deepEqual(push(listeners, data), []);
    }

    assert.equal(shown.length, 0);
});

test('a click opens the page of the notification, if any', () => {
    const { listeners, opened } = worker();
    let closed = 0;
    const waited = [];
    const click = (url) =>
        listeners.notificationclick({
            notification: { data: { url }, close: () => (closed += 1) },
            waitUntil: (promise) => waited.push(promise),
        });

    click('https://site.test/c/9');
    click(null);
    listeners.notificationclick({ notification: { close: () => (closed += 1) }, waitUntil: (promise) => waited.push(promise) });

    assert.deepEqual(opened, ['https://site.test/c/9']);
    assert.equal(closed, 3);
    assert.equal(waited.length, 1);
});
