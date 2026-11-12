// The browser side of the collection, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { afterEach, test } from 'node:test';
import assert from 'node:assert/strict';

import {
    clickEventsOf,
    EngagementMeter,
    eventOf,
    pageView,
    previousPathOf,
    privacyRequested,
    referrerOf,
    scrollPercent,
    send,
} from '../../assets/src/collect.js';
import CollectController from '../../assets/src/collect_controller.js';

const globals = ['navigator', 'location', 'document', 'fetch', 'addEventListener', 'removeEventListener', 'requestAnimationFrame', 'innerHeight', 'scrollY'];
const saved = Object.fromEntries(globals.map((name) => [name, Object.getOwnPropertyDescriptor(globalThis, name)]));

afterEach(() => {
    for (const name of globals) {
        if (saved[name]) {
            Object.defineProperty(globalThis, name, saved[name]);
        } else {
            delete globalThis[name];
        }
    }
});

const define = (name, value) => Object.defineProperty(globalThis, name, { value, configurable: true, writable: true });

test('a privacy signal is respected', () => {
    assert.equal(privacyRequested({ doNotTrack: '1' }), true);
    assert.equal(privacyRequested({ globalPrivacyControl: true }), true);
    assert.equal(privacyRequested({ doNotTrack: '0', globalPrivacyControl: false }), false);
    assert.equal(privacyRequested({ doNotTrack: 'unspecified' }), false);
    assert.equal(privacyRequested({ globalPrivacyControl: 'true' }), false);
    assert.equal(privacyRequested(undefined), false);
});

test('the referrer keeps its origin only', () => {
    assert.equal(referrerOf('https://search.example/results?q=secret#top'), 'https://search.example');
    assert.equal(referrerOf('https://social.example/@alice'), 'https://social.example');
    assert.equal(referrerOf('http://example.org:8080/a'), 'http://example.org:8080');
    assert.equal(referrerOf('http://example.org'), 'http://example.org');
    assert.equal(referrerOf('android-app://com.example/'), null);
    assert.equal(referrerOf('not a url'), null);
    assert.equal(referrerOf(''), null);
    assert.equal(referrerOf(undefined), null);
});

test('a page view holds the path, the referrer and the verdict', () => {
    const location = { pathname: '/events/42', search: '?token=x' };

    assert.deepEqual(pageView(location, { referrer: 'https://a.example/p?x=1' }, 'human'), { path: '/events/42', referrer: 'https://a.example', verdict: 'human' });
    assert.deepEqual(pageView(location, { referrer: '', documentElement: { dataset: { philBotVerdict: 'robot' } } }), { path: '/events/42', referrer: null, verdict: 'robot' });
    assert.deepEqual(pageView(location, { referrer: '', documentElement: { dataset: { philBotVerdict: 'robot' } } }, 'human'), { path: '/events/42', referrer: null, verdict: 'human' });
    assert.deepEqual(pageView(location, {}), { path: '/events/42', referrer: null, verdict: 'unknown' });
    assert.deepEqual(pageView(location, undefined, ''), { path: '/events/42', referrer: null, verdict: 'unknown' });
});

test('a page shown after another one in the same document has that page as referrer', () => {
    const document = { referrer: 'https://search.example/?q=x' };

    assert.equal(pageView({ pathname: '/b' }, document, 'human', 'https://site.example/a?page=2').referrer, 'https://site.example');
    assert.equal(pageView({ pathname: '/a' }, document, 'human', null).referrer, 'https://search.example');
});

test('a page view is sent by a fetch kept alive, as plain text, without credentials, even with a beacon', async () => {
    const calls = [];
    const fetch = (url, options) => {
        calls.push([url, options]);

        return Promise.reject(new Error('offline'));
    };

    assert.equal(send('/collect', { path: '/a' }, { sendBeacon: () => assert.fail('beaconed') }, fetch), true);
    assert.deepEqual(calls, [['/collect', { method: 'POST', body: '{"path":"/a"}', keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } }]]);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(send('/collect', { path: '/b' }, {}, fetch), true);
    assert.equal(calls.length, 2);
});

test('without fetch, a page view is sent by beacon, as plain text', async () => {
    const beacons = [];
    const navigator = { sendBeacon: (url, blob) => beacons.push([url, blob]) > 0 };

    assert.equal(send('https://stats.example/collect', { path: '/' }, navigator, null), true);
    assert.equal(beacons.length, 1);
    assert.equal(beacons[0][0], 'https://stats.example/collect');
    assert.equal(beacons[0][1].type, 'text/plain');
    assert.equal(await beacons[0][1].text(), '{"path":"/"}');
    assert.equal(send('https://stats.example/collect', { path: '/' }, { sendBeacon: () => false }, null), false);
    assert.equal(send('https://stats.example/collect', { path: '/' }, { sendBeacon: 'not a function' }, null), false);
});

const target = () => {
    const listeners = new Map();

    return {
        listeners,
        addEventListener: (type, listener) => listeners.set(type, listener),
        removeEventListener: (type) => listeners.delete(type),
        fire(type, event = {}) {
            listeners.get(type)?.({ type, ...event });
        },
    };
};

function page({ href = 'https://site.example/p', pathname = '/p', referrer = 'https://a.example/x?y', navigator = {} } = {}) {
    const sent = [];
    const window = target();
    const document = { ...target(), referrer, visibilityState: 'visible', documentElement: { dataset: {}, scrollHeight: 2000 } };

    define('fetch', (url, options) => {
        sent.push([url, JSON.parse(options.body)]);

        return Promise.resolve();
    });
    define('navigator', navigator);
    define('location', { pathname, href, origin: 'https://site.example', host: 'site.example' });
    define('document', document);
    define('addEventListener', window.addEventListener);
    define('removeEventListener', window.removeEventListener);
    define('requestAnimationFrame', (callback) => {
        callback();

        return 0;
    });
    define('innerHeight', 500);
    define('scrollY', 0);

    return { sent, window, document };
}

function controllerOn(values = {}) {
    const controller = new CollectController();

    Object.assign(controller, {
        element: target(),
        endpointValue: '/collect',
        verdictValue: 'human',
        clicksValue: 'aggregate',
        downloadsValue: ['pdf'],
        dimensionsValue: {},
        statusValue: 200,
        searchTermValue: '',
        searchResultsValue: -1,
        ...values,
    });

    return controller;
}

const link = (href, dataset = {}) => ({ href, dataset });
const clickOn = (element, named = null) => ({
    closest: (selector) => (selector === 'a[href]' ? element : named),
});
const location = { host: 'site.example' };

test('the previous path is kept for a page of the same site only, without its query', () => {
    const here = { origin: 'https://site.example' };

    assert.equal(previousPathOf('https://site.example/a/b?token=x#top', here), '/a/b');
    assert.equal(previousPathOf('https://other.example/a', here), null);
    assert.equal(previousPathOf('', here), null);
    assert.equal(previousPathOf(undefined, here), null);
    assert.equal(previousPathOf('not a url', here), null);
});

test('a page view names the previous path and the declared dimensions only when it has them', () => {
    const here = { pathname: '/b', origin: 'https://site.example' };

    assert.deepEqual(pageView(here, {}, 'human', 'https://site.example/a?x=1', { variant: 'b' }), {
        path: '/b',
        referrer: 'https://site.example',
        verdict: 'human',
        from: '/a',
        dimensions: { variant: 'b' },
    });
    assert.equal('from' in pageView(here, { referrer: 'https://other.example/' }, 'human'), false);
    assert.equal('dimensions' in pageView(here, {}, 'human'), false);
});

test('an event says what its page view said, without the previous page', () => {
    assert.deepEqual(eventOf({ path: '/a', referrer: null, verdict: 'human', from: '/z' }, 'click', { name: 'cta' }), {
        path: '/a',
        referrer: null,
        verdict: 'human',
        event: 'click',
        name: 'cta',
    });
    assert.deepEqual(eventOf({ path: '/a' }, 'not_found'), { path: '/a', event: 'not_found' });
});

test('a click reports a declared name, a download, or the host of a way out', () => {
    const downloads = new Set(['pdf']);

    assert.deepEqual(clickEventsOf(clickOn(link('https://site.example/doc/Guide.PDF?x=1')), location, downloads), [['download', { extension: 'pdf' }]]);
    assert.deepEqual(clickEventsOf(clickOn(link('https://other.example/a/b?token=x')), location, downloads), [['outbound', { host: 'other.example' }]]);
    assert.deepEqual(clickEventsOf(clickOn(link('https://other.example/a', { philAnalyticsLink: 'L1' })), location, downloads), [['outbound', { host: 'other.example', link: 'L1' }]]);
    assert.deepEqual(clickEventsOf(clickOn(link('https://other.example/a.pdf')), location, downloads), [['download', { extension: 'pdf' }]]);
    assert.deepEqual(clickEventsOf(clickOn(link('https://site.example/a.zip')), location, downloads), []);
    assert.deepEqual(clickEventsOf(clickOn(link('https://site.example/a')), location, downloads), []);
    assert.deepEqual(clickEventsOf(clickOn(link('mailto:a@example.org')), location, downloads), []);
    assert.deepEqual(clickEventsOf(clickOn(link('not a url')), location, downloads), []);
    assert.deepEqual(clickEventsOf(clickOn(null, { dataset: { philAnalyticsClick: 'cta' } }), location, downloads), [['click', { name: 'cta' }]]);
    assert.deepEqual(clickEventsOf({}, location, downloads), []);
    assert.deepEqual(clickEventsOf(null, location, downloads), []);
});

test('the scroll position is a share of the page, whole when the page fits the window', () => {
    assert.equal(scrollPercent({ innerHeight: 500, scrollY: 0 }, { scrollHeight: 2000 }), 25);
    assert.equal(scrollPercent({ innerHeight: 500, scrollY: 1500 }, { scrollHeight: 2000 }), 100);
    assert.equal(scrollPercent({ innerHeight: 500, scrollY: 1600 }, { scrollHeight: 2000 }), 100);
    assert.equal(scrollPercent({ innerHeight: 500, scrollY: 0 }, { scrollHeight: 400 }), 100);
    assert.equal(scrollPercent({ innerHeight: 500, scrollY: 0 }, { scrollHeight: 500 }), 100);
});

test('the meter keeps the deepest band and counts only the time the page is visible', () => {
    const meter = new EngagementMeter(1000, true);

    meter.scrolled(60);
    meter.scrolled(30);
    meter.visibility(false, 6000);
    meter.visibility(false, 7000);
    meter.visibility(true, 9000);
    meter.visibility(true, 9500);

    assert.deepEqual(meter.report(12000), { scroll: 50, active: 8 });
    assert.deepEqual(new EngagementMeter(0, false).report(5000), { scroll: 0, active: 0 });
});

test('the controller sends one page view when it connects', () => {
    assert.deepEqual(Object.keys(CollectController.values), ['endpoint', 'verdict', 'clicks', 'downloads', 'dimensions', 'status', 'searchTerm', 'searchResults']);
    assert.equal(CollectController.values.clicks.default, 'aggregate');

    const { sent } = page();
    controllerOn().connect();

    assert.deepEqual(sent, [['/collect', { path: '/p', referrer: 'https://a.example', verdict: 'human' }]]);

    // Turbo renders the next page: same document, same `document.referrer`, a new controller on the new body.
    define('location', { pathname: '/q', href: 'https://site.example/q', origin: 'https://site.example', host: 'site.example' });
    controllerOn().connect();

    assert.deepEqual(sent[1], ['/collect', { path: '/q', referrer: 'https://site.example', verdict: 'human', from: '/p' }]);
});

test('the controller reports a page not found and a search', () => {
    const { sent } = page({ referrer: '' });

    controllerOn({ statusValue: 404, searchTermValue: 'dice', searchResultsValue: 0 }).connect();

    assert.deepEqual(sent.slice(1).map(([, body]) => body), [
        { path: '/p', referrer: 'https://site.example', verdict: 'human', event: 'not_found' },
        { path: '/p', referrer: 'https://site.example', verdict: 'human', event: 'search', term: 'dice', results: false },
    ]);

    const again = page({ referrer: '' });

    controllerOn({ searchTermValue: 'dice' }).connect();

    assert.equal('results' in again.sent[1][1], false);
    assert.equal(again.sent[1][1].term, 'dice');
});

test('the controller reports clicks and declared forms, a middle click too, a right click not', () => {
    const { sent } = page();
    const controller = controllerOn();
    const way = clickOn(link('https://other.example/a'));

    controller.connect();
    controller.element.fire('click', { target: way });
    controller.element.fire('auxclick', { target: way, button: 1 });
    controller.element.fire('auxclick', { target: way, button: 2 });
    controller.element.fire('submit', { target: { closest: () => ({ dataset: { philAnalyticsForm: 'contact' } }) } });
    controller.element.fire('submit', { target: { closest: () => null } });

    assert.deepEqual(sent.slice(1).map(([, body]) => body.event), ['outbound', 'outbound', 'form_submit']);
    assert.equal(sent[3][1].form, 'contact');
});

test('with clicks off, no click is listened to', () => {
    page();
    const controller = controllerOn({ clicksValue: 'off' });

    controller.connect();

    assert.equal(controller.element.listeners.has('click'), false);
    assert.equal(controller.element.listeners.has('auxclick'), false);
    assert.equal(controller.element.listeners.has('submit'), true);
});

test('leaving a page sends its engagement once, whether by pagehide or by Turbo', () => {
    const { sent, window, document } = page();
    const controller = controllerOn();

    controller.connect();
    define('scrollY', 1500);
    window.fire('scroll');
    document.visibilityState = 'hidden';
    document.fire('visibilitychange');
    window.fire('pagehide');
    controller.disconnect();

    const engagement = sent.filter(([, body]) => body.event === 'engagement');

    assert.equal(engagement.length, 1);
    assert.equal(engagement[0][1].scroll, 100);
    assert.equal(engagement[0][1].active, 0);
    assert.equal('from' in engagement[0][1], false);
    assert.equal(controller.element.listeners.size, 0);
    assert.equal(window.listeners.size, 0);
    assert.equal(document.listeners.size, 0);
});

test('a scroll waits for the next frame already asked for', () => {
    const { window } = page();
    let frames = 0;
    let pending = null;

    define('requestAnimationFrame', (callback) => {
        frames += 1;
        pending = callback;

        return 7;
    });
    controllerOn().connect();
    window.fire('scroll');
    window.fire('scroll');

    assert.equal(frames, 1);
    pending();
    window.fire('scroll');
    assert.equal(frames, 2);
});

test('the controller sends nothing when the browser asks not to be tracked', () => {
    const { window } = page({ navigator: { doNotTrack: '1', sendBeacon: () => assert.fail('sent') } });
    const controller = controllerOn();

    define('fetch', () => assert.fail('sent'));
    controller.connect();
    controller.disconnect();

    assert.equal(controller.element.listeners.size, 0);
    assert.equal(window.listeners.size, 0);
});
