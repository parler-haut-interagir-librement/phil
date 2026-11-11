// The browser detectors, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { afterEach, test } from 'node:test';
import assert from 'node:assert/strict';

import {
    automationStack, automationTraces, detectors, environment, headlessChrome, noLanguages, nodeRuntime, platformMismatch,
    rendererOf, softwareRenderer, verdict, webdriver, zeroSizeWindow,
} from '../../assets/src/detectors.js';
import VerdictController from '../../assets/src/verdict_controller.js';

const chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36';
const person = () => ({
    window: { outerWidth: 1280, outerHeight: 800 },
    navigator: { webdriver: false, userAgent: chrome, languages: ['fr-CA', 'en'], platform: 'Win32', userAgentData: { platform: 'Windows', brands: [{ brand: 'Chromium' }] } },
    document: {},
    stack: 'Error\n    at environment (https://example.com/app.js:1:1)',
    renderer: 'ANGLE (Intel, Intel(R) UHD Graphics)',
});

const saved = Object.getOwnPropertyDescriptor(globalThis, 'document');

afterEach(() => {
    if (saved) {
        Object.defineProperty(globalThis, 'document', saved);
    } else {
        delete globalThis.document;
    }
});

test('a person is human by every detector', () => {
    for (const [name, detector] of Object.entries(detectors)) {
        assert.equal(detector(person()), false, name);
    }

    assert.equal(verdict(person()), 'human');
});

test('each automation signal makes a robot', () => {
    const cases = {
        webdriver: (env) => { env.navigator.webdriver = true; },
        'a Selenium global': (env) => { env.window._selenium = {}; },
        'a PhantomJS global': (env) => { env.window.callPhantom = () => {}; },
        'a ChromeDriver trace': (env) => { env.document.$cdc_asdjflasutopfhvcZLmcfl_ = {}; },
        'a ChromeDriver trace without dollar': (env) => { env.document.cdc_adoQpoasnfa76pfcZLmcfl_ = {}; },
        'a Node runtime': (env) => { env.window.process = { versions: { node: '26.12.0' } }; },
        'a headless user agent': (env) => { env.navigator.userAgent = chrome.replace('Chrome/', 'HeadlessChrome/'); },
        'a headless brand': (env) => { env.navigator.userAgentData.brands = [{ brand: 'HeadlessChrome' }]; },
        'no language': (env) => { env.navigator.languages = []; },
        'a zero-size window': (env) => { env.window.outerWidth = 0; env.window.outerHeight = 0; },
        'a platform the hints contradict': (env) => { env.navigator.platform = 'Linux x86_64'; },
        'a Puppeteer frame': (env) => { env.stack += '\n    at __puppeteer_evaluation_script__:3:7'; },
        'a Playwright frame': (env) => { env.stack += '\n    at Playwright.evaluate'; },
        'a software renderer': (env) => { env.renderer = 'Google SwiftShader'; },
        'a Mesa renderer': (env) => { env.renderer = 'llvmpipe (LLVM 15.0.7, 256 bits)'; },
    };

    for (const [name, change] of Object.entries(cases)) {
        const env = person();
        change(env);
        assert.equal(verdict(env), 'robot', name);
    }
});

test('a blanked or missing signal is not automation', () => {
    const blank = { window: undefined, navigator: undefined, document: undefined, stack: undefined, renderer: undefined };

    for (const [name, detector] of Object.entries(detectors)) {
        assert.notEqual(detector(blank), true, name);
    }

    assert.equal(verdict(blank), 'human');
    assert.equal(webdriver({ navigator: { webdriver: 'yes' } }), undefined);
    assert.equal(automationTraces({ window: {}, document: undefined }), false);
    assert.equal(nodeRuntime({ window: { process: { versions: {} } } }), false);
    assert.equal(headlessChrome({ navigator: { userAgent: chrome } }), false);
    assert.equal(headlessChrome({ navigator: { userAgent: chrome, userAgentData: { brands: [{}] } } }), false);
    assert.equal(noLanguages({ navigator: { languages: 'fr' } }), undefined);
    assert.equal(zeroSizeWindow({ window: { outerWidth: 0, outerHeight: 600 } }), false);
    assert.equal(zeroSizeWindow({ window: { outerWidth: 0 } }), undefined);
    assert.equal(zeroSizeWindow({ window: { outerHeight: 0 } }), undefined);
    assert.equal(platformMismatch({ navigator: { platform: 'Win32', userAgentData: { platform: 'Chrome OS' } } }), undefined);
    assert.equal(platformMismatch({ navigator: { platform: 'FreeBSD amd64', userAgentData: { platform: 'Linux' } } }), undefined);
    assert.equal(platformMismatch({ navigator: { platform: 'MacIntel', userAgentData: { platform: 'macOS' } } }), false);
    assert.equal(platformMismatch({ navigator: { platform: 'Linux armv8l', userAgentData: { platform: 'Linux' } } }), false);
    assert.equal(platformMismatch({ navigator: { userAgentData: { platform: 'Linux' } } }), undefined);
    assert.equal(platformMismatch({ navigator: { platform: 'Linux' } }), undefined);
    assert.equal(softwareRenderer({ renderer: '' }), undefined);
    assert.equal(softwareRenderer({ renderer: 'Apple M2' }), false);
    assert.equal(automationStack({ stack: 'at app.js' }), false);
});

test('the renderer is read from WebGL when the browser tells it', () => {
    const context = {
        getExtension: (name) => (name === 'WEBGL_debug_renderer_info' ? { UNMASKED_RENDERER_WEBGL: 37446 } : null),
        getParameter: (parameter) => (parameter === 37446 ? 'Apple M2' : null),
    };
    const document = { createElement: (tag) => ({ getContext: (kind) => (tag === 'canvas' && kind === 'webgl' ? context : null) }) };

    assert.equal(rendererOf(document), 'Apple M2');
    assert.equal(rendererOf({ createElement: () => ({ getContext: () => ({ getExtension: () => null }) }) }), undefined);
    assert.equal(rendererOf({ createElement: () => ({ getContext: () => null }) }), undefined);
    assert.equal(rendererOf({ createElement: () => { throw new Error('blocked'); } }), undefined);
    assert.equal(rendererOf(undefined), undefined);
});

test('the environment holds what the detectors read', () => {
    const scope = { window: { outerWidth: 1 }, navigator: { webdriver: false }, document: undefined };
    const env = environment(scope);

    assert.equal(env.window, scope.window);
    assert.equal(env.navigator, scope.navigator);
    assert.equal(env.document, undefined);
    assert.equal(env.renderer, undefined);
    assert.match(env.stack, /environment/);
    assert.equal(typeof environment().stack, 'string');
});

test('the controller writes the verdict on the root and into its field', () => {
    const root = { dataset: {} };
    Object.defineProperty(globalThis, 'document', { value: { documentElement: root }, configurable: true });

    const field = new VerdictController();
    field.element = { value: '' };
    field.connect();
    assert.equal(field.element.value, 'human');
    assert.equal(root.dataset.philBotVerdict, 'human');

    const html = new VerdictController();
    html.element = {};
    html.connect();
    assert.equal('value' in html.element, false);

    Object.defineProperty(globalThis, 'document', { value: {}, configurable: true });
    const alone = new VerdictController();
    alone.element = { value: '' };
    alone.connect();
    assert.equal(alone.element.value, 'human');
});
