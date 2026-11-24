import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import StickyHeaderController, {
    hiddenAfter,
    SIZE,
} from '../../sticky-header/assets/controllers/sticky_header_controller.js';
import ThemeSwitcherController, {
    apply,
    stored,
} from '../../theme-switcher/assets/controllers/theme_switcher_controller.js';
import { browser, FakeElement, stimulate } from './fake.mjs';

let listeners;
let root;

/** The root of the page: its dataset and the properties written on its style. */
const page = (options = {}) => {
    listeners = browser(options);
    root = {
        dataset: {},
        properties: new Map(),
        style: {
            setProperty: (name, value) => root.properties.set(name, value),
            removeProperty: (name) => root.properties.delete(name),
        },
    };
    globalThis.document.documentElement = root;
    globalThis.document.activeElement = null;
    globalThis.window.scrollY = 0;
};

/** A storage of the browser, or one that refuses every call. */
const storage = (entries = {}, refused = false) => {
    const map = new Map(Object.entries(entries));
    const refuse = () => {
        throw new Error('SecurityError');
    };
    globalThis.localStorage = refused
        ? { getItem: refuse, setItem: refuse, removeItem: refuse }
        : {
              getItem: (key) => (map.has(key) ? map.get(key) : null),
              setItem: (key, value) => map.set(key, String(value)),
              removeItem: (key) => map.delete(key),
          };

    return map;
};

beforeEach(() => page());

const radio = (value) => Object.assign(new FakeElement(), { value, checked: value === 'system' });

const switcher = (key = 'theme') => {
    const options = ['light', 'dark', 'system'].map(radio);
    const element = Object.assign(new FakeElement(), { hidden: true });
    const controller = stimulate(ThemeSwitcherController, {
        element,
        targets: { option: options },
        values: { key },
    });

    return { controller, element, options };
};

const checked = (options) => options.filter((option) => option.checked).map((option) => option.value);

test('theme switcher: the stored scheme, else the system', () => {
    storage({ theme: 'dark', other: 'sepia' });
    assert.equal(stored('theme'), 'dark');
    assert.equal(stored('other'), 'system');
    assert.equal(stored('missing'), 'system');
    storage({}, true);
    assert.equal(stored('theme'), 'system');
});

test('theme switcher: writes the scheme on the root, or takes it away', () => {
    apply('light');
    assert.equal(root.dataset.theme, 'light');
    apply('dark');
    assert.equal(root.dataset.theme, 'dark');
    apply('system');
    assert.equal('theme' in root.dataset, false);
    root.dataset.theme = 'dark';
    apply('sepia');
    assert.equal('theme' in root.dataset, false);
});

test('theme switcher: shows itself with the stored choice checked', () => {
    storage({ look: 'light' });
    const { controller, element, options } = switcher('look');
    controller.connect();
    assert.equal(element.hidden, false);
    assert.deepEqual(checked(options), ['light']);
    assert.equal(root.dataset.theme, 'light');
    assert.equal(listeners.window.has('storage'), true);
    controller.disconnect();
    assert.equal(listeners.window.has('storage'), false);
});

test('theme switcher: a choice is stored, written on the root and announced', () => {
    const entries = storage();
    const { controller, options } = switcher();
    controller.connect();
    assert.deepEqual(checked(options), ['system']);
    controller.choose({ target: options[1] });
    assert.equal(entries.get('theme'), 'dark');
    assert.equal(root.dataset.theme, 'dark');
    assert.deepEqual(controller.dispatched, [['change', { detail: { theme: 'dark' } }]]);
    controller.choose({ target: options[2] });
    assert.equal(entries.has('theme'), false);
    assert.equal('theme' in root.dataset, false);
    assert.deepEqual(controller.dispatched[1], ['change', { detail: { theme: 'system' } }]);
    controller.choose({ target: { value: 'sepia' } });
    assert.deepEqual(controller.dispatched[2], ['change', { detail: { theme: 'system' } }]);
});

test('theme switcher: a refused storage keeps the choice for the page', () => {
    storage({}, true);
    const { controller, options } = switcher();
    controller.connect();
    controller.choose({ target: options[0] });
    assert.equal(root.dataset.theme, 'light');
    controller.choose({ target: options[2] });
    assert.equal('theme' in root.dataset, false);
});

test('theme switcher: follows a choice made in another tab, for its own key alone', () => {
    const entries = storage();
    const { controller, options } = switcher();
    controller.connect();
    entries.set('theme', 'dark');
    listeners.window.get('storage')({ key: 'other' });
    assert.deepEqual(checked(options), ['system']);
    listeners.window.get('storage')({ key: 'theme' });
    assert.deepEqual(checked(options), ['dark']);
    assert.equal(root.dataset.theme, 'dark');
});

test('sticky header: hidden after a scroll down past it, shown after a scroll up or near the top', () => {
    const at = (y, last, hidden = false) => hiddenAfter({ y, last, height: 60, threshold: 8, hidden });
    assert.equal(at(200, 100), true);
    assert.equal(at(109, 100), true);
    assert.equal(at(108, 100), false);
    assert.equal(at(108, 100, true), true);
    assert.equal(at(100, 200, true), false);
    assert.equal(at(191, 200, true), false);
    assert.equal(at(192, 200, true), true);
    assert.equal(at(60, 0), false);
    assert.equal(at(61, 0), true);
    assert.equal(at(40, 300, true), false);
});

const header = ({ hideOnScroll = true, threshold = 8, height = 60, reduced = false } = {}) => {
    page({ reduced });
    const links = [new FakeElement()];
    const element = Object.assign(new FakeElement({ children: links }), { offsetHeight: height, inert: false });
    const controller = stimulate(StickyHeaderController, { element, values: { hideOnScroll, threshold } });

    return { controller, element, links };
};

test('sticky header: keeps the scroll padding of the page at its height', () => {
    const { controller, element } = header({ hideOnScroll: false, height: 72 });
    controller.connect();
    assert.equal(root.properties.get(SIZE), '72px');
    assert.equal(listeners.window.has('scroll'), false);
    element.offsetHeight = 90;
    controller.measure();
    assert.equal(root.properties.get(SIZE), '90px');
    controller.disconnect();
    assert.equal(root.properties.has(SIZE), false);
});

test('sticky header: follows its height while it changes', () => {
    const observed = [];
    globalThis.ResizeObserver = class {
        constructor(callback) {
            this.callback = callback;
            observed.push(this);
        }

        observe(target) {
            this.target = target;
        }

        disconnect() {
            this.disconnected = true;
        }
    };
    const { controller, element } = header({ hideOnScroll: false });
    controller.connect();
    assert.equal(observed[0].target, element);
    element.offsetHeight = 120;
    observed[0].callback();
    assert.equal(root.properties.get(SIZE), '120px');
    controller.disconnect();
    assert.equal(observed[0].disconnected, true);
    delete globalThis.ResizeObserver;
});

test('sticky header: hidden, it is inert so that its links cannot be reached', () => {
    const { controller, element } = header();
    controller.connect();
    assert.equal(listeners.window.has('scroll'), true);
    window.scrollY = 300;
    listeners.window.get('scroll')();
    assert.equal(element.inert, true);
    assert.equal(element.hasAttribute('data-hidden'), true);
    controller.update(200);
    assert.equal(element.inert, false);
    assert.equal(element.hasAttribute('data-hidden'), false);
    controller.update(400);
    assert.equal(element.inert, true);
    controller.disconnect();
    assert.equal(element.inert, false);
    assert.equal(element.hasAttribute('data-hidden'), false);
    assert.equal(listeners.window.has('scroll'), false);
});

test('sticky header: never hides while it holds the focus', () => {
    const { controller, element, links } = header();
    controller.connect();
    document.activeElement = links[0];
    controller.update(500);
    assert.equal(element.inert, false);
    document.activeElement = null;
    controller.update(1000);
    assert.equal(element.inert, true);
});

test('sticky header: never hides where motion is reduced', () => {
    const { controller, element } = header({ reduced: true });
    controller.connect();
    controller.update(500);
    controller.update(1000);
    assert.equal(element.inert, false);
    assert.equal(element.hasAttribute('data-hidden'), false);
});

test('sticky header: never hides under data-motion="reduce"', () => {
    const { controller, element } = header();
    element.closest = (selector) => (selector === '[data-motion="reduce"]' ? element : null);
    controller.connect();
    controller.update(500);
    assert.equal(element.inert, false);
});

test('sticky header: a negative threshold counts as none', () => {
    const { controller, element } = header({ threshold: -50 });
    controller.connect();
    controller.update(61);
    assert.equal(element.inert, true);
    controller.update(60.5);
    assert.equal(element.inert, false);
});
