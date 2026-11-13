// The controller that registers the service worker, under Node's test runner: its peer resolved to a stub by
// `register.mjs`, the browser made of the plain objects of `browser.mjs`.

import assert from 'node:assert/strict';
import { test } from 'node:test';
import Pwa from '../../assets/src/pwa_controller.js';
import { Element, Events, install, target } from './browser.mjs';

function browser({ serviceWorker = undefined, onLine = true } = {}) {
    const window = new Events();
    window.location = { reloaded: 0, reload: () => window.location.reloaded++ };
    const navigator = { onLine };

    if (serviceWorker !== undefined) {
        navigator.serviceWorker = serviceWorker;
    }

    const document = {
        fullscreenElement: null,
        exited: 0,
        exitFullscreen: () => ++document.exited,
        documentElement: { requested: 0, requestFullscreen: () => ++document.documentElement.requested },
    };
    install({ window, navigator, document });

    return { window, navigator, document };
}

function controller({ url = '/sw.js', scope = '/' } = {}) {
    const pwa = new Pwa();
    pwa.element = new Element();
    pwa.urlValue = url;
    pwa.scopeValue = scope;
    pwa.installTargets = [target()];
    pwa.updateTargets = [target()];
    pwa.offlineTargets = [target()];

    return pwa;
}

function worker(state = 'installing') {
    const events = new Events();

    return Object.assign(events, { state, posted: [], postMessage: (message) => events.posted.push(message) });
}

function container({ controller: active = null, registration = null, fails = null } = {}) {
    const events = new Events();

    return Object.assign(events, {
        controller: active,
        registered: [],
        register: async (url, options) => {
            events.registered.push([url, options]);

            if (fails) {
                throw fails;
            }

            return registration;
        },
    });
}

function registration({ waiting = null } = {}) {
    return Object.assign(new Events(), { scope: 'https://example.org/', waiting, installing: null });
}

const names = (pwa) => pwa.dispatched.map((event) => event.name);

test('it registers the worker at its address with its scope', async () => {
    const serviceWorker = container({ registration: registration() });
    browser({ serviceWorker });
    const pwa = controller({ url: '/app/sw.js', scope: '/app/' });

    await pwa.connect();

    assert.deepEqual(serviceWorker.registered, [['/app/sw.js', { scope: '/app/' }]]);
    assert.deepEqual(names(pwa), ['online', 'registered']);
    assert.deepEqual(pwa.dispatched[1].detail, { scope: 'https://example.org/' });
});

test('without service workers, or without an address, it says so', async () => {
    browser();
    const pwa = controller();
    await pwa.connect();
    assert.deepEqual(names(pwa), ['online', 'unsupported']);

    const serviceWorker = container();
    browser({ serviceWorker });
    const noAddress = controller({ url: '' });
    await noAddress.connect();
    assert.deepEqual(names(noAddress), ['online', 'unsupported']);
    assert.deepEqual(serviceWorker.registered, []);
});

test('a refused registration is dispatched with its error', async () => {
    const error = new Error('SecurityError');
    browser({ serviceWorker: container({ fails: error }) });
    const pwa = controller();

    await pwa.connect();

    assert.deepEqual(names(pwa), ['online', 'failed']);
    assert.equal(pwa.dispatched[1].detail.error, error);
});

test('a worker already waiting when a page is controlled offers the update', async () => {
    const waiting = worker('installed');
    browser({ serviceWorker: container({ controller: {}, registration: registration({ waiting }) }) });
    const pwa = controller();

    await pwa.connect();

    assert.deepEqual(names(pwa), ['online', 'update-available', 'registered']);
    assert.equal(pwa.updateTargets[0].hidden, false);
});

test('a first worker, with no page controlled yet, offers nothing', async () => {
    const found = registration({ waiting: worker('installed') });
    browser({ serviceWorker: container({ registration: found }) });
    const pwa = controller();
    await pwa.connect();

    const installing = worker();
    found.installing = installing;
    found.emit('updatefound');
    installing.state = 'installed';
    installing.emit('statechange');

    assert.deepEqual(names(pwa), ['online', 'registered']);
    assert.equal(pwa.updateTargets[0].hidden, true);
});

test('a new worker installed while a page is controlled offers the update, which reloads once it takes over', async () => {
    const found = registration();
    const { window, navigator } = browser({ serviceWorker: container({ controller: {}, registration: found }) });
    const pwa = controller();
    await pwa.connect();

    pwa.update();
    assert.equal(navigator.serviceWorker.count('controllerchange'), 0);

    found.emit('updatefound');
    const installing = worker();
    found.installing = installing;
    found.emit('updatefound');
    installing.state = 'activating';
    installing.emit('statechange');
    assert.equal(pwa.updateTargets[0].hidden, true);
    installing.state = 'installed';
    installing.emit('statechange');

    assert.equal(names(pwa).at(-1), 'update-available');
    assert.equal(pwa.updateTargets[0].hidden, false);

    pwa.update();
    assert.deepEqual(installing.posted, [{ type: 'skip-waiting' }]);
    navigator.serviceWorker.emit('controllerchange');
    navigator.serviceWorker.emit('controllerchange');
    assert.equal(window.location.reloaded, 1);
});

test('it offers to install when the browser does, and dispatches the choice', async () => {
    const { window } = browser();
    const pwa = controller();
    await pwa.connect();
    await pwa.install();
    assert.equal(names(pwa).includes('install-choice'), false);

    let prevented = 0;
    let prompted = 0;
    const prompt = {
        preventDefault: () => prevented++,
        prompt: async () => prompted++,
        userChoice: Promise.resolve({ outcome: 'accepted' }),
    };
    window.emit('beforeinstallprompt', prompt);

    assert.equal(prevented, 1);
    assert.equal(pwa.installTargets[0].hidden, false);
    assert.equal(names(pwa).at(-1), 'installable');

    await pwa.install();
    assert.equal(prompted, 1);
    assert.equal(pwa.installTargets[0].hidden, true);
    assert.deepEqual(pwa.dispatched.at(-1), { name: 'install-choice', detail: { outcome: 'accepted' } });

    await pwa.install();
    assert.equal(prompted, 1);
});

test('once installed, the offer goes away', async () => {
    const { window } = browser();
    const pwa = controller();
    await pwa.connect();
    window.emit('beforeinstallprompt', { preventDefault: () => {} });

    window.emit('appinstalled');

    assert.equal(pwa.installTargets[0].hidden, true);
    assert.equal(pwa.deferred, null);
    assert.equal(names(pwa).at(-1), 'installed');
});

test('it follows the connection', async () => {
    const { window, navigator } = browser({ onLine: false });
    const pwa = controller();
    await pwa.connect();

    assert.equal(pwa.element.attributes.has('data-offline'), true);
    assert.equal(pwa.offlineTargets[0].hidden, false);
    assert.equal(names(pwa)[0], 'offline');

    navigator.onLine = true;
    window.emit('online');
    assert.equal(pwa.element.attributes.has('data-offline'), false);
    assert.equal(pwa.offlineTargets[0].hidden, true);
    assert.equal(names(pwa).at(-1), 'online');

    navigator.onLine = undefined;
    window.emit('offline');
    assert.equal(pwa.element.attributes.has('data-offline'), false);
});

test('disconnecting removes every listener', async () => {
    const { window } = browser();
    const pwa = controller();
    await pwa.connect();
    assert.deepEqual(
        ['online', 'offline', 'beforeinstallprompt', 'appinstalled'].map((type) => window.count(type)),
        [1, 1, 1, 1],
    );

    pwa.disconnect();

    assert.deepEqual(
        ['online', 'offline', 'beforeinstallprompt', 'appinstalled'].map((type) => window.count(type)),
        [0, 0, 0, 0],
    );
});

test('it enters and leaves the full screen', () => {
    const { document } = browser();
    const pwa = controller();

    assert.equal(pwa.fullscreen(), 1);
    document.fullscreenElement = {};
    assert.equal(pwa.fullscreen(), 1);
    assert.equal(document.exited, 1);

    document.fullscreenElement = null;
    document.documentElement.requestFullscreen = undefined;
    assert.equal(pwa.fullscreen(), undefined);
});
