// The seasonal decor controller, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import SeasonalDecorController, {
    advance,
    bounded,
    budget,
    CELEBRATE,
    counted,
    DECORS,
    DRAWN,
    draw,
    FADE,
    fallbacks,
    FLAP,
    hiding,
    keep,
    LONGEST,
    LONGEST_STEP,
    normal,
    pieces,
    progress,
    recall,
    sequence,
    SHORTEST,
    SPIN,
    STAGE,
    STORAGE_PREFIX,
    storageOf,
    typing,
} from '../../seasonal-decor/assets/controllers/seasonal_decor_controller.js';
import { browser, FakeElement, stimulate } from './fake.mjs';

const KONAMI = 'ArrowUp ArrowUp ArrowDown ArrowDown ArrowLeft ArrowRight ArrowLeft ArrowRight b a';

/** A 2D context that records every call made on it. */
function recorder() {
    const calls = [];
    const state = { globalAlpha: 1, fillStyle: '' };

    return new Proxy(state, {
        get(target, name) {
            if (name === 'calls') {
                return calls;
            }

            if (name in target) {
                return target[name];
            }

            return (...values) => calls.push([name, ...values]);
        },
        set(target, name, value) {
            target[name] = value;
            calls.push([`=${String(name)}`, value]);

            return true;
        },
    });
}

/** A storage of the browser, in memory. */
class MemoryStorage {
    constructor(entries = {}) {
        this.map = new Map(Object.entries(entries));
    }

    getItem(key) {
        return this.map.has(key) ? this.map.get(key) : null;
    }

    setItem(key, value) {
        this.map.set(key, String(value));
    }
}

let listeners;
let frames;

beforeEach(() => {
    listeners = browser();
    frames = [];
    globalThis.requestAnimationFrame = (callback) => frames.push(callback);
    globalThis.cancelAnimationFrame = () => {
        frames = [];
    };
    Object.defineProperty(globalThis, 'navigator', { value: { hardwareConcurrency: 8 }, configurable: true, writable: true });
    globalThis.getComputedStyle = () => ({ color: 'rgb(1, 2, 3)' });
    window.devicePixelRatio = 1;
    window.localStorage = new MemoryStorage();
});

/** A canvas as the template clones it: its style, its context, and whether it left the page. */
function canvas(context = recorder()) {
    const stage = new FakeElement({ tagName: 'CANVAS' });
    stage.style.removeProperty = (name) => delete stage.style[name];
    stage.getContext = () => context;
    stage.remove = () => (stage.removed = true);
    stage.context = context;

    return stage;
}

/** A controller connected to a decor element, with its templates, its live region and its button. */
function mount(values = {}, { closest = {}, nests = [], stage = canvas() } = {}) {
    const element = new FakeElement();
    const appended = [];
    element.append = (child) => appended.push(child);
    element.closest = (selector) => closest[selector] ?? null;
    element.appended = appended;
    document.querySelectorAll = (selector) => ('[data-egg-nest]' === selector ? nests : []);
    const counter = new FakeElement();
    const close = new FakeElement();
    close.hidden = true;
    const egg = () => {
        const button = new FakeElement({ tagName: 'BUTTON' });
        button.remove = () => (button.removed = true);
        button.addEventListener = (type, listener) => (button.listeners = { ...button.listeners, [type]: listener });

        return button;
    };
    const controller = stimulate(SeasonalDecorController, {
        element,
        targets: {
            stage: [{ content: { firstElementChild: { cloneNode: () => stage } } }],
            egg: [{ content: { firstElementChild: { cloneNode: egg } } }],
            counter: [counter],
            close: [close],
        },
        values: {
            decor: 'snow',
            holiday: '',
            sober: false,
            eggs: 0,
            eggLabel: 'A hidden egg',
            counter: 'Eggs found: %found% of %total%',
            hunted: 'Every egg found!',
            code: '',
            codeMessage: 'Secret!',
            codeEffect: 'fireworks',
            duration: 12000,
            colors: '',
            ...values,
        },
    });
    controller.connect();

    return { controller, element, counter, close, stage };
}

test('a duration is kept between its bounds, the longest when it is no number', () => {
    assert.equal(bounded(100), SHORTEST);
    assert.equal(bounded(60000), LONGEST);
    assert.equal(bounded(5000.4), 5000);
    assert.equal(bounded('x'), LONGEST);
    assert.equal(bounded(undefined), LONGEST);
    assert.equal(SHORTEST, 2000);
    assert.equal(LONGEST, 15000);
});

test('the keys of a code: a letter in lower case, a named key as it is', () => {
    assert.deepEqual(sequence(' ArrowUp  B a '), ['ArrowUp', 'b', 'a']);
    assert.deepEqual(sequence(''), []);
    assert.deepEqual(sequence(null), []);
    assert.equal(normal('A'), 'a');
    assert.equal(normal('Enter'), 'Enter');
    assert.equal(normal('É'), 'é');
    assert.equal(normal(undefined), '');
});

test('a key that breaks the code keeps what may still begin it', () => {
    const keys = sequence(KONAMI);
    const table = fallbacks(keys);
    assert.deepEqual(table.slice(0, 4), [0, 1, 0, 0]);
    let typed = 0;

    for (const key of ['ArrowUp', 'ArrowUp', 'ArrowUp', ...keys.slice(2)]) {
        typed = progress(keys, table, typed, key);
    }

    assert.equal(typed, keys.length, 'up up up down… still types the code');
    assert.equal(progress(keys, table, 3, 'x'), 0);
    assert.equal(progress(keys, table, 1, 'ArrowUp'), 2);
    assert.equal(progress(keys, table, 2, 'ArrowUp'), 2);

    const abab = sequence('a b a c');
    const tableAbab = fallbacks(abab);
    assert.deepEqual(tableAbab, [0, 0, 1, 0]);
    assert.equal(progress(abab, tableAbab, 3, 'b'), 2, 'a b a, then b: a b');
    assert.equal(progress(abab, tableAbab, abab.length, 'a'), 1, 'after the whole code, it starts again');
    assert.equal(progress(sequence('a a'), fallbacks(sequence('a a')), 2, 'a'), 2, 'a a a types a a twice');
});

test('a key typed in a field or with a modifier is never one of the code', () => {
    assert.equal(typing({ target: { tagName: 'INPUT' } }), true);
    assert.equal(typing({ target: { tagName: 'TEXTAREA' } }), true);
    assert.equal(typing({ target: { tagName: 'SELECT' } }), true);
    assert.equal(typing({ target: { tagName: 'DIV', isContentEditable: true } }), true);
    assert.equal(typing({ target: { tagName: 'BODY' }, ctrlKey: true }), true);
    assert.equal(typing({ target: { tagName: 'BODY' }, metaKey: true }), true);
    assert.equal(typing({ target: { tagName: 'BODY' }, altKey: true }), true);
    assert.equal(typing({ target: { tagName: 'BODY' }, shiftKey: true }), false);
    assert.equal(typing({ target: { tagName: 'BUTTON' } }), false);
    assert.equal(typing(undefined), false);
});

test('the counter of the hunt', () => {
    assert.equal(counted('%found% of %total%, %found%!', 2, 5), '2 of 5, 2!');
    assert.equal(counted(undefined, 1, 1), '');
});

test('the eggs hide in the nests in turn, at random', () => {
    const draws = [0.9, 0.1, 0.5];
    const random = () => draws.shift();
    assert.deepEqual(hiding(4, ['a', 'b', 'c'], random), ['b', 'c', 'a', 'b']);
    assert.deepEqual(hiding(0, ['a']), []);
    assert.deepEqual(hiding(3, []), []);
});

test('the storage remembers, and forgets nothing it cannot read', () => {
    const storage = new MemoryStorage();
    keep(storage, 'closed:x', '1');
    assert.equal(storage.getItem(`${STORAGE_PREFIX}closed:x`), '1');
    assert.equal(recall(storage, 'closed:x'), '1');
    assert.equal(recall(storage, 'other'), null);
    assert.equal(recall(null, 'other'), null);
    const broken = {
        getItem() {
            throw new Error('blocked');
        },
        setItem() {
            throw new Error('blocked');
        },
    };
    assert.equal(recall(broken, 'x'), null);
    assert.doesNotThrow(() => keep(broken, 'x', 1));
    assert.equal(storageOf({ localStorage: storage }), storage);
    assert.equal(
        storageOf({
            get localStorage() {
                throw new Error('denied');
            },
        }),
        null,
    );
    assert.equal(storageOf(undefined), null);
    assert.equal(STORAGE_PREFIX, 'phil-seasonal-decor:');
});

test('a device of few cores, or saving data, draws fewer pieces', () => {
    assert.equal(budget({ hardwareConcurrency: 8 }), 1);
    assert.equal(budget({ hardwareConcurrency: 4 }), 0.5);
    assert.equal(budget({ hardwareConcurrency: 2, connection: { saveData: true } }), 0.25);
    assert.equal(budget({ connection: { saveData: true } }), 0.5);
    assert.equal(budget(undefined), 1);
});

test('the pieces of a decor spread over the screen, a share of them kept, in its colours or those given', () => {
    const list = pieces('snow', { width: 1280, height: 800, random: () => 0.5 });
    assert.equal(list.length, DECORS.snow.count);
    assert.deepEqual(list[0], {
        x: 640,
        y: 400,
        size: 6,
        speed: 50,
        color: DECORS.snow.colors[1],
        angle: Math.PI,
        spin: 0,
        phase: Math.PI,
    });
    assert.equal(pieces('snow', { width: 1280, height: 800, scale: 0.5 }).length, 30);
    assert.equal(pieces('snow', { width: 640, height: 400 }).length, 15, 'fewer on a small screen');
    assert.equal(pieces('snow', { width: 10, height: 10, scale: 0 }).length, 1, 'one at least');
    assert.equal(pieces('snow', { width: 4000, height: 4000, scale: 3 }).length, DECORS.snow.count, 'never more');
    assert.deepEqual(new Set(pieces('hearts', { width: 1280, height: 800, colors: ['red'] }).map((piece) => piece.color)), new Set(['red']));
    assert.deepEqual(pieces('poppy', { width: 100, height: 100 }), []);
    assert.deepEqual(pieces('confetti', { width: 100, height: 100 }), []);
    assert.deepEqual(DRAWN, ['snow', 'hearts', 'clovers', 'eggs', 'bats', 'leaves', 'petals', 'lanterns', 'maple-leaves', 'fleurs-de-lis']);
    assert.ok(SPIN <= 0.25 && FLAP <= 2, 'slow: never a flicker');
});

test('the pieces fall, rise or cross, and come back on the other side', () => {
    const size = { width: 100, height: 100 };
    const piece = () => ({ x: 50, y: 50, size: 10, speed: 100, angle: 0, spin: 1, phase: 0 });
    const fall = [piece()];
    advance('snow', fall, 0.1, size);
    assert.ok(Math.abs(fall[0].y - 60) < 1e-9);
    assert.ok(fall[0].x !== 50, 'it sways');
    assert.ok(Math.abs(fall[0].angle - 0.1) < 1e-9);
    assert.ok(Math.abs(fall[0].phase - Math.PI / 10) < 1e-9);
    const rise = [piece()];
    advance('hearts', rise, 0.1, size);
    assert.ok(Math.abs(rise[0].y - 40) < 1e-9);
    const cross = [piece()];
    advance('bats', cross, 0.1, size);
    assert.ok(Math.abs(cross[0].x - 60) < 1e-9);
    assert.ok(cross[0].y !== 50);

    const out = [{ ...piece(), y: 105 }];
    advance('snow', out, 0.1, size);
    assert.equal(out[0].y, -10, 'back at the top');
    const gone = [{ ...piece(), y: -5 }];
    advance('lanterns', gone, 0.1, size);
    assert.equal(gone[0].y, 110, 'back at the bottom');
    const across = [{ ...piece(), x: 105 }];
    advance('bats', across, 0.1, size);
    assert.equal(across[0].x, -10, 'back on the left');
    const still = [{ ...piece(), y: 104 }];
    advance('snow', still, 0, size);
    assert.equal(still[0].y, 104, 'not yet out');
    assert.doesNotThrow(() => advance('poppy', [piece()], 1, size));
});

test('each decor draws its pieces, turned and coloured, faded at the end', () => {
    for (const name of DRAWN) {
        const context = recorder();
        draw(context, name, [{ x: 10, y: 20, size: 12, color: 'red', angle: 1, phase: 1 }], { width: 100, height: 50, alpha: 0.5 });
        assert.deepEqual(context.calls[0], ['clearRect', 0, 0, 100, 50]);
        assert.deepEqual(context.calls[1], ['=globalAlpha', 0.425]);
        assert.deepEqual(context.calls[3], ['translate', 10, 20]);
        assert.deepEqual(context.calls[4], ['rotate', 'bats' === name ? 0 : 1]);
        assert.deepEqual(context.calls[5], ['=fillStyle', 'red']);
        assert.ok(context.calls.some(([call]) => 'fill' === call || 'fillRect' === call), name);
        assert.deepEqual(context.calls.at(-1), ['=globalAlpha', 1]);
    }

    const context = recorder();
    draw(context, 'poppy', [{ x: 0, y: 0, size: 1 }], { width: 10, height: 10 });
    assert.deepEqual(context.calls, [['clearRect', 0, 0, 10, 10]]);
    const bright = recorder();
    draw(bright, 'snow', [], { width: 1, height: 1, alpha: 3 });
    assert.deepEqual(bright.calls[1], ['=globalAlpha', 0.85]);
    const dark = recorder();
    draw(dark, 'snow', [], { width: 1, height: 1, alpha: -1 });
    assert.deepEqual(dark.calls[1], ['=globalAlpha', 0]);
    const plain = recorder();
    draw(plain, 'snow', [], { width: 1, height: 1 });
    assert.deepEqual(plain.calls[1], ['=globalAlpha', 0.85]);
});

test('it draws over the page through the CSSOM, moving, then fading, then gone', () => {
    const { controller, element, close, stage } = mount({ duration: 2000 });

    assert.equal(close.hidden, false, 'its button shows');
    assert.equal(Object.hasOwn(controller, 'context'), false, 'the context of Stimulus left alone');
    assert.deepEqual(element.appended, [stage]);
    assert.equal(stage.getAttribute('aria-hidden'), 'true');

    for (const [property, value] of Object.entries(STAGE)) {
        assert.equal(stage.style[property], value);
    }

    assert.equal(stage.width, 1000);
    assert.equal(stage.height, 800);
    assert.ok(stage.context.calls.some(([call]) => 'clearRect' === call), 'a first picture at once');
    assert.equal(frames.length, 1);

    frames.shift()(1000);
    const moved = controller.list[0].y;
    frames.shift()(1016);
    assert.notEqual(controller.list[0].y, moved);
    assert.equal(controller.elapsed, 16);
    frames.shift()(5000);
    assert.equal(controller.elapsed, 16 + LONGEST_STEP * 1000, 'a tab shown again takes up where it was');

    controller.elapsed = 2000 - FADE / 2;
    frames.shift()(5016);
    assert.ok(stage.context.calls.some(([call, value]) => '=globalAlpha' === call && value < 0.85 && value > 0), 'it fades');

    controller.elapsed = 2000;
    frames.shift()(5032);
    assert.equal(stage.removed, true);
    assert.equal(stage.width, 0);
    assert.equal(controller.stage, null);
    assert.equal(frames.length, 0);
});

test('the colours of the tokens given, resolved on the canvas', () => {
    const { controller } = mount({ colors: '--a --b bad;token --a' });

    assert.deepEqual(new Set(controller.list.map((piece) => piece.color)), new Set(['rgb(1, 2, 3)']));
});

test('a still picture where motion is reduced, or on a sober day', () => {
    browser({ reduced: true });
    globalThis.getComputedStyle = () => ({ color: '' });
    window.localStorage = new MemoryStorage();
    const reduced = mount();
    assert.equal(reduced.stage.removed, undefined, 'the picture stays');
    assert.equal(frames.length, 0);

    browser();
    window.localStorage = new MemoryStorage();
    const sober = mount({ decor: 'leaves', sober: true });
    assert.equal(frames.length, 0);
    assert.ok(sober.stage.context.calls.length > 0);

    const marked = mount({}, { closest: { '[data-motion="reduce"]': {} } });
    assert.equal(frames.length, 0);
    assert.ok(marked.stage.context.calls.length > 0);
});

test('motion reduced while it moves stops it', () => {
    let reduced = false;
    window.matchMedia = () => ({ matches: reduced });
    const { stage } = mount();
    reduced = true;
    frames.shift()(0);
    assert.equal(stage.removed, true);
});

test('nothing is drawn for the poppy, which the template shows, nor without a context', () => {
    const poppy = mount({ decor: 'poppy', sober: true });
    assert.deepEqual(poppy.element.appended, []);
    assert.equal(poppy.close.hidden, false);

    const blank = canvas(null);
    blank.getContext = () => null;
    const none = mount({}, { stage: blank });
    assert.equal(blank.removed, true);
    assert.equal(none.controller.stage, null);
});

test('a person who chose no celebration sees no decor, but a sober one', () => {
    const none = mount({}, { closest: { '[data-celebrations]': { getAttribute: () => 'none' } } });
    assert.equal(none.element.hidden, true);
    assert.deepEqual(none.element.appended, []);

    const poppy = mount({ decor: 'poppy', sober: true }, { closest: { '[data-celebrations]': { getAttribute: () => 'none' } } });
    assert.equal(poppy.element.hidden, false);

    const all = mount({}, { closest: { '[data-celebrations]': { getAttribute: () => 'discreet' } } });
    assert.equal(all.element.hidden, false);
});

test('its button closes it, remembered for the holiday', () => {
    const { controller, element, stage } = mount({ holiday: 'halloween-2026-10-25', eggs: 2 });

    controller.close();
    assert.equal(element.hidden, true);
    assert.equal(stage.removed, true);
    assert.equal(window.localStorage.getItem('phil-seasonal-decor:closed:halloween-2026-10-25'), '1');
    assert.deepEqual(controller.dispatched, [['closed', undefined]]);
    assert.deepEqual(controller.hidden, [], 'its eggs leave with it');

    const again = mount({ holiday: 'halloween-2026-10-25' });
    assert.equal(again.element.hidden, true, 'closed for the rest of the holiday');
    assert.deepEqual(again.element.appended, []);

    const nextYear = mount({ holiday: 'halloween-2027-10-25' });
    assert.equal(nextYear.element.hidden, false);
});

test('closed without a holiday, it is closed for the page alone', () => {
    const { controller, element } = mount();

    controller.close();
    assert.equal(element.hidden, true);
    assert.equal(window.localStorage.map.size, 0);
});

test('the eggs hide in the nests, each found by a click, counted, the last one celebrated', () => {
    const nests = [new FakeElement(), new FakeElement()];
    const inNest = [];

    for (const nest of nests) {
        nest.append = (egg) => inNest.push([nest, egg]);
    }

    const { controller, counter } = mount({ decor: 'eggs', eggs: 3, holiday: 'easter-2026-04-03' }, { nests });
    assert.equal(inNest.length, 3);
    assert.equal(controller.hidden.length, 3);

    const [[, first], [, second], [, third]] = inNest;
    assert.equal(typeof first.listeners.click, 'function', 'each egg listens to its own clicks');
    first.listeners.click({ currentTarget: first });
    assert.equal(first.removed, true);
    assert.equal(counter.textContent, 'Eggs found: 1 of 3');
    assert.equal(window.localStorage.getItem('phil-seasonal-decor:eggs:easter-2026-04-03'), '1');
    assert.deepEqual(controller.dispatched.at(-1), ['found', { detail: { found: 1, total: 3 } }]);

    controller.find({ currentTarget: second });
    assert.equal(controller.hidden.length, 1);
    globalThis.lastWindowEvent = undefined;
    controller.find({ currentTarget: third });
    assert.equal(counter.textContent, 'Eggs found: 3 of 3 Every egg found!');
    assert.equal(globalThis.lastWindowEvent.type, CELEBRATE);
    assert.deepEqual(globalThis.lastWindowEvent.detail, { message: 'Every egg found!', effect: 'fireworks' });
    assert.deepEqual(controller.dispatched.at(-1), ['hunted', undefined]);
});

test('the eggs found are remembered from page to page, and hide in the decor without a nest', () => {
    window.localStorage = new MemoryStorage({ 'phil-seasonal-decor:eggs:easter': '2' });
    const { controller, element, counter } = mount({ decor: 'eggs', eggs: 3, holiday: 'easter' });

    assert.equal(controller.hidden.length, 1);
    assert.equal(element.appended.at(-1), controller.hidden[0]);
    controller.find({ currentTarget: controller.hidden[0] });
    assert.equal(counter.textContent, 'Eggs found: 3 of 3 Every egg found!');

    window.localStorage = new MemoryStorage({ 'phil-seasonal-decor:eggs:easter': 'x' });
    assert.equal(mount({ decor: 'eggs', eggs: 3, holiday: 'easter' }).controller.hidden.length, 3);
    window.localStorage = new MemoryStorage({ 'phil-seasonal-decor:eggs:easter': '-1' });
    assert.equal(mount({ decor: 'eggs', eggs: 3, holiday: 'easter' }).controller.hidden.length, 3);
    window.localStorage = new MemoryStorage({ 'phil-seasonal-decor:eggs:easter': '5' });
    assert.equal(mount({ decor: 'eggs', eggs: 3, holiday: 'easter' }).controller.hidden.length, 0, 'all found already');
});

test('without a holiday, the eggs are counted for the page alone', () => {
    window.localStorage = new MemoryStorage({ 'phil-seasonal-decor:eggs:': '2' });
    const { controller, counter } = mount({ decor: 'eggs', eggs: 2 });

    assert.equal(controller.hidden.length, 2);
    controller.find({ currentTarget: controller.hidden[0] });
    assert.equal(counter.textContent, 'Eggs found: 1 of 2');
    assert.equal(window.localStorage.getItem('phil-seasonal-decor:eggs:'), '2', 'nothing written');
});

test('no hunt on a sober day, nor without eggs', () => {
    assert.deepEqual(mount({ eggs: 3, sober: true }).controller.hidden, []);
    assert.deepEqual(mount({ eggs: 0 }).controller.hidden, []);
});

test('disconnected, its eggs leave the page and its canvas goes', () => {
    const { controller, stage } = mount({ eggs: 2 });
    const eggs = [...controller.hidden];

    controller.disconnect();
    assert.ok(eggs.every((egg) => egg.removed));
    assert.equal(stage.removed, true);
});

test('the secret code typed on the page plays the celebration, never from a field', () => {
    const { controller } = mount({ code: 'G n o m e', codeMessage: 'Hello gnome', codeEffect: 'balloons' });
    const keydown = listeners.document.get('keydown');
    assert.equal(typeof keydown, 'function');
    globalThis.lastWindowEvent = undefined;

    for (const key of ['g', 'n', 'o', 'm']) {
        keydown({ key, target: { tagName: 'BODY' } });
    }

    keydown({ key: 'e', target: { tagName: 'INPUT' } });
    assert.equal(globalThis.lastWindowEvent, undefined, 'a key in a field is ignored');
    keydown({ key: 'E', target: { tagName: 'BODY' } });
    assert.deepEqual(globalThis.lastWindowEvent.detail, { message: 'Hello gnome', effect: 'balloons' });
    assert.deepEqual(controller.dispatched.at(-1), ['code', undefined]);

    controller.disconnect();
    assert.equal(listeners.document.has('keydown'), false);
});

test('no code is listened to when it is empty, or on a sober day', () => {
    mount({ code: '' });
    assert.equal(listeners.document.has('keydown'), false);
    mount({ code: KONAMI, sober: true });
    assert.equal(listeners.document.has('keydown'), false);
});
