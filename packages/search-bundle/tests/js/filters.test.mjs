// The filters controller, under Node's test runner, with the page stood for by the little it touches.

import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import FiltersController from '../../assets/src/filters_controller.js';

let assigned;
let pushed;

beforeEach(() => {
    assigned = [];
    pushed = [];
    globalThis.window = {
        location: {
            href: 'https://example.com/games?q=jeu',
            origin: 'https://example.com',
            assign: (url) => assigned.push(url),
        },
        history: { pushState: (_state, _title, url) => pushed.push(url) },
    };
});

afterEach(() => {
    delete globalThis.window;
    delete globalThis.fetch;
});

const controller = (
    element = {
        dataset: {},
        setAttribute() {},
        removeAttribute() {},
        ownerDocument: { activeElement: null },
        contains: () => false,
    },
) => {
    const filters = new FiltersController();
    filters.element = element;
    filters.submitTargets = [{ hidden: false }];

    return filters;
};

test('connecting hides the submit button and marks the filters enhanced', () => {
    const filters = controller();
    filters.connect();

    assert.equal(filters.submitTargets[0].hidden, true);
    assert.equal(filters.element.dataset.enhanced, 'true');
});

test('a link to another origin is opened by the browser', async () => {
    globalThis.fetch = () => assert.fail('nothing is fetched');
    await controller().load(new URL('https://example.org/games'));

    assert.deepEqual(assigned, ['https://example.org/games']);
});

test('a page that cannot be fetched is opened by the browser', async () => {
    globalThis.fetch = async () => ({ ok: false, status: 500 });
    await controller().load(new URL('https://example.com/games?filter=x'));

    assert.deepEqual(assigned, ['https://example.com/games?filter=x']);
    assert.deepEqual(pushed, []);
});

test('a load given up for another one changes nothing', async () => {
    globalThis.fetch = async () => {
        throw Object.assign(new Error('aborted'), { name: 'AbortError' });
    };
    await controller().load(new URL('https://example.com/games'));

    assert.deepEqual(assigned, []);
});

test('a modified click is left to the browser, a plain one is followed in place', () => {
    const filters = controller();
    const loaded = [];
    filters.load = (url) => loaded.push(url.href);
    let prevented = 0;
    const event = (overrides) => ({
        defaultPrevented: false,
        button: 0,
        metaKey: false,
        ctrlKey: false,
        shiftKey: false,
        altKey: false,
        preventDefault: () => prevented++,
        currentTarget: { getAttribute: () => '?q=jeu' },
        ...overrides,
    });

    filters.follow(event({ ctrlKey: true }));
    assert.deepEqual([loaded, prevented], [[], 0]);

    filters.follow(event({}));
    assert.deepEqual([loaded, prevented], [['https://example.com/games?q=jeu'], 1]);
});

test('going back draws the address without remembering it again', () => {
    const filters = controller();
    const loaded = [];
    filters.load = (url, remember) => loaded.push([url.href, remember]);
    filters.restore();

    assert.deepEqual(loaded, [['https://example.com/games?q=jeu', false]]);
});
