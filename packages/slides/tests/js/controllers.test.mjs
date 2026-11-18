// The two controllers, under Node's test runner, Stimulus resolved to a stub and the DOM to the fakes of `dom.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { afterEach, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';

import DeckController from '../../assets/src/deck_controller.js';
import PresenterController from '../../assets/src/presenter_controller.js';
import { FakeChannel, FakeElement, slide } from './dom.mjs';

const saved = {};

beforeEach(() => {
    for (const name of ['BroadcastChannel', 'location', 'history', 'document', 'open', 'CSS']) {
        saved[name] = Object.getOwnPropertyDescriptor(globalThis, name);
    }

    FakeChannel.opened = [];
    globalThis.BroadcastChannel = FakeChannel;
    globalThis.location = { hash: '' };
    globalThis.history = { state: null, replaced: [], replaceState(state, unused, url) { this.replaced.push(url); globalThis.location.hash = url; } };
    globalThis.document = { activeElement: null, fullscreenElement: null, exited: 0, exitFullscreen() { this.exited += 1; } };
    globalThis.CSS = { escape: (value) => value };
});

afterEach(() => {
    for (const [name, descriptor] of Object.entries(saved)) {
        if (descriptor) {
            Object.defineProperty(globalThis, name, descriptor);
        } else {
            delete globalThis[name];
        }
    }
});

const values = (instance) => {
    instance.channelValue = 'talk';
    instance.fragmentClassValue = 'fragment';
    instance.statusValue = 'Slide %number% of %count%';
    instance.titledStatusValue = 'Slide %number% of %count%: %title%';
    instance.counterValue = '%number% / %count%';
};

const deck = (slides = [slide(1, { fragments: 2 }), slide(2), slide(3, { title: '' })]) => {
    const instance = new DeckController();
    const stage = new FakeElement('DIV', { children: slides });
    instance.element = new FakeElement('SECTION', { children: [stage] });
    instance.element.requested = 0;
    instance.element.requestFullscreen = () => { instance.element.requested += 1; };
    instance.slideTargets = slides;
    instance.hasStatusTarget = true;
    instance.statusTarget = new FakeElement('P');
    instance.hasCounterTarget = true;
    instance.counterTarget = new FakeElement('SPAN');
    instance.hasControlsTarget = true;
    instance.controlsTarget = new FakeElement('NAV');
    instance.controlsTarget.hidden = true;
    instance.hasFullscreenTarget = true;
    instance.fullscreenTarget = new FakeElement('BUTTON');
    instance.pageKeysValue = false;
    instance.stage = stage;
    values(instance);

    return instance;
};

const press = (instance, name, target = { tagName: 'DIV' }) => {
    let prevented = 0;
    instance.key({ key: name, target, defaultPrevented: false, preventDefault: () => (prevented += 1) });

    return prevented;
};

const current = (instance) => instance.slideTargets.findIndex((element) => element.classList.contains('isCurrent'));

test('the deck presents one slide at a time and says hello to the other windows', () => {
    const instance = deck();
    instance.connect();

    assert.equal(instance.element.classList.contains('isPresenting'), true);
    assert.equal(instance.controlsTarget.hidden, false);
    assert.equal(current(instance), 0);
    assert.equal(instance.counterTarget.textContent, '1 / 3');
    assert.equal(instance.statusTarget.textContent, '');
    assert.equal(FakeChannel.opened[0].name, 'phil-slides:talk');
    assert.equal(FakeChannel.opened[0].sent[0].type, 'hello');
    assert.deepEqual(globalThis.history.replaced, ['#slide-1']);

    const fragments = instance.slideTargets[0].querySelectorAll('.fragment');
    assert.deepEqual(fragments.map((element) => element.classList.contains('isPending')), [true, true]);
});

test('the deck starts at the slide of the address and follows it', () => {
    globalThis.location.hash = '#slide-2';
    const instance = deck();
    instance.connect();

    assert.equal(current(instance), 1);
    assert.deepEqual(globalThis.history.replaced, []);
    assert.deepEqual(instance.slideTargets[0].querySelectorAll('.fragment').map((element) => element.classList.contains('isPending')), [false, false]);

    globalThis.location.hash = '#slide-3';
    instance.hashChanged();
    assert.equal(current(instance), 2);
    assert.equal(instance.statusTarget.textContent, 'Slide 3 of 3');
    assert.deepEqual(FakeChannel.opened[0].sent.at(-1), { type: 'go', slide: 2, fragment: 0, sender: instance.sender });

    const sent = FakeChannel.opened[0].sent.length;
    instance.hashChanged();
    globalThis.location.hash = '#nothing';
    instance.hashChanged();
    assert.equal(FakeChannel.opened[0].sent.length, sent);
});

test('the keys move the deck only when it has the focus, the page keys or the full screen', () => {
    const instance = deck();
    instance.connect();

    assert.equal(press(instance, 'ArrowRight'), 0);
    assert.equal(current(instance), 0);

    globalThis.document.activeElement = instance.stage;
    assert.equal(press(instance, 'ArrowRight'), 1);
    assert.equal(press(instance, 'ArrowRight'), 1);
    assert.equal(press(instance, 'ArrowRight'), 1);
    assert.equal(current(instance), 1);
    assert.equal(instance.statusTarget.textContent, 'Slide 2 of 3: Title 2');
    assert.equal(press(instance, 'a'), 0);
    assert.equal(press(instance, 'ArrowRight', { tagName: 'INPUT' }), 0);
    assert.equal(press(instance, 'End'), 1);
    assert.equal(current(instance), 2);
    assert.equal(instance.statusTarget.textContent, 'Slide 3 of 3');
    assert.equal(press(instance, 'Home'), 1);
    assert.equal(current(instance), 0);

    globalThis.document.activeElement = null;
    instance.pageKeysValue = true;
    assert.equal(press(instance, 'PageDown'), 1);

    instance.pageKeysValue = false;
    globalThis.document.fullscreenElement = instance.element;
    assert.equal(press(instance, 'PageUp'), 1);
    assert.equal(instance.slideTargets[0].querySelectorAll('.fragment')[0].classList.contains('isPending'), true);
});

test('escape leaves the full screen, F toggles it', () => {
    const instance = deck();
    instance.connect();
    instance.pageKeysValue = true;

    assert.equal(press(instance, 'Escape'), 0);
    assert.equal(press(instance, 'f'), 1);
    assert.equal(instance.element.requested, 1);

    globalThis.document.fullscreenElement = instance.element;
    instance.fullscreenChanged();
    assert.equal(instance.fullscreenTarget.getAttribute('aria-pressed'), 'true');
    assert.equal(instance.element.classList.contains('isFullscreen'), true);
    assert.equal(press(instance, 'Escape'), 1);
    assert.equal(globalThis.document.exited, 1);
    instance.toggleFullscreen();
    assert.equal(globalThis.document.exited, 2);

    globalThis.document.fullscreenElement = null;
    instance.fullscreenChanged();
    assert.equal(instance.fullscreenTarget.getAttribute('aria-pressed'), 'false');
    assert.equal(instance.element.classList.contains('isFullscreen'), false);

    instance.hasFullscreenTarget = false;
    instance.fullscreenChanged();
});

test('the buttons move the deck and tell the other windows', () => {
    const instance = deck();
    instance.connect();
    const channel = FakeChannel.opened[0];

    instance.next();
    assert.deepEqual(channel.sent.at(-1), { type: 'go', slide: 0, fragment: 1, sender: instance.sender });
    instance.previous();
    assert.deepEqual(channel.sent.at(-1), { type: 'go', slide: 0, fragment: 0, sender: instance.sender });

    const sent = channel.sent.length;
    instance.previous();
    assert.equal(channel.sent.length, sent);
});

test('the deck follows the other windows and answers their hello', () => {
    const instance = deck();
    instance.connect();
    const channel = FakeChannel.opened[0];

    channel.onmessage({ data: { type: 'go', slide: 1, fragment: 0, sender: 'presenter' } });
    assert.equal(current(instance), 1);
    assert.equal(instance.statusTarget.textContent, 'Slide 2 of 3: Title 2');
    assert.equal(channel.sent.length, 1);

    channel.onmessage({ data: { type: 'hello', sender: 'presenter' } });
    assert.deepEqual(channel.sent.at(-1), { type: 'go', slide: 1, fragment: 0, sender: instance.sender });

    instance.statusTarget.textContent = '';
    channel.onmessage({ data: { type: 'go', slide: 1, fragment: 0, sender: 'presenter' } });
    channel.onmessage({ data: { type: 'go', slide: 0, fragment: 0, sender: instance.sender } });
    channel.onmessage({ data: null });
    assert.equal(current(instance), 1);
    assert.equal(instance.statusTarget.textContent, '');
});

test('disconnecting gives the linear reading back', () => {
    const instance = deck();
    instance.connect();
    instance.element.classList.add('isFullscreen');
    instance.disconnect();

    assert.equal(FakeChannel.opened[0].closed, true);
    assert.equal(instance.channel, null);
    assert.equal(instance.element.classList.contains('isPresenting'), false);
    assert.equal(instance.element.classList.contains('isFullscreen'), false);
    assert.equal(instance.controlsTarget.hidden, true);
    assert.equal(current(instance), -1);
    assert.equal(instance.slideTargets[0].querySelectorAll('.fragment').some((element) => element.classList.contains('isPending')), false);
});

test('without BroadcastChannel, targets or a history, the deck still works', () => {
    globalThis.BroadcastChannel = undefined;
    globalThis.history = undefined;
    globalThis.CSS = undefined;
    const instance = deck();
    instance.hasControlsTarget = false;
    instance.hasCounterTarget = false;
    instance.hasStatusTarget = false;
    instance.connect();
    instance.next();
    instance.disconnect();

    assert.equal(instance.channel, null);
    assert.equal(FakeChannel.opened.length, 0);
});

test('the presenter link opens a window of its own', () => {
    const instance = deck();
    const opened = [];
    let prevented = 0;
    const event = { currentTarget: { href: '/talk/presenter' }, preventDefault: () => (prevented += 1) };

    globalThis.open = (...args) => { opened.push(args); return {}; };
    instance.openPresenter(event);
    assert.deepEqual(opened, [['/talk/presenter', 'phil-slides-presenter', 'popup']]);
    assert.equal(prevented, 1);

    globalThis.open = () => null;
    instance.openPresenter(event);
    assert.equal(prevented, 1);
});

const presenter = () => {
    const slides = [slide(1, { fragments: 1 }), slide(2), slide(3)];
    const instance = new PresenterController();
    instance.identifier = 'x';
    instance.element = new FakeElement('SECTION');
    instance.slideTargets = slides;
    instance.pairTargets = slides.map((element) => new FakeElement('ARTICLE', { children: [element] }));
    instance.hasStatusTarget = true;
    instance.statusTarget = new FakeElement('P');
    instance.hasCounterTarget = true;
    instance.counterTarget = new FakeElement('SPAN');
    instance.hasSideTarget = true;
    instance.sideTarget = new FakeElement('ASIDE');
    instance.sideTarget.hidden = true;
    instance.hasNextTarget = true;
    instance.nextTarget = new FakeElement('DIV');
    instance.hasEndTarget = true;
    instance.endTarget = new FakeElement('P');
    instance.hasElapsedTarget = true;
    instance.elapsedTarget = new FakeElement('TIME');
    instance.hasRemainingTarget = true;
    instance.remainingTarget = new FakeElement('TIME');
    instance.hasToggleTarget = true;
    instance.toggleTarget = new FakeElement('BUTTON');
    instance.toggleTarget.dataset = { startLabel: 'Start', pauseLabel: 'Pause' };
    instance.durationValue = 60;
    values(instance);

    return instance;
};

test('the presenter shows the current pair and a copy of the next slide', () => {
    const instance = presenter();
    instance.connect();

    assert.equal(instance.element.classList.contains('isPresenting'), true);
    assert.equal(instance.sideTarget.hidden, false);
    assert.deepEqual(instance.pairTargets.map((pair) => pair.classList.contains('isCurrent')), [true, false, false]);

    const copy = instance.nextTarget.children[0];
    assert.equal(copy.getAttribute('id'), null);
    assert.equal(copy.getAttribute('tabindex'), null);
    assert.equal(copy.classList.contains('isCurrent'), true);
    assert.equal(copy.querySelector('h2').getAttribute('id'), null);
    assert.equal(copy.textContent, 'Title 2');
    assert.equal(instance.endTarget.hidden, true);

    instance.run('last');
    assert.equal(instance.nextTarget.children.length, 0);
    assert.equal(instance.endTarget.hidden, false);
    assert.equal(instance.run('fullscreen'), false);
    instance.disconnect();
    assert.equal(instance.element.classList.contains('isPresenting'), false);
});

test('the copy of the next slide shows every fragment and drops the targets', () => {
    const instance = presenter();
    instance.connect();
    instance.slideTargets[1].children.push(new FakeElement('DIV', { classes: ['fragment', 'isPending'], attributes: { 'data-x-target': 'slide' } }));
    instance.render(false);

    const copied = instance.nextTarget.children[0];
    assert.equal(copied.getAttribute('data-x-target'), null);
    assert.equal(copied.children.at(-1).classList.contains('isPending'), false);
    assert.equal(copied.children.at(-1).getAttribute('data-x-target'), null);
});

test('the presenter keys work wherever the focus is', () => {
    const instance = presenter();
    instance.connect();

    assert.equal(press(instance, 'ArrowRight'), 1);
    assert.equal(press(instance, 'ArrowRight'), 1);
    assert.deepEqual(instance.pairTargets.map((pair) => pair.classList.contains('isCurrent')), [false, true, false]);
    assert.equal(press(instance, 'Escape'), 0);
});

test('without the next slide target, the presenter still moves', () => {
    const instance = presenter();
    instance.hasNextTarget = false;
    instance.hasEndTarget = false;
    instance.hasSideTarget = false;
    instance.connect();
    instance.run('last');
    assert.equal(instance.pairTargets[2].classList.contains('isCurrent'), true);

    const ending = presenter();
    ending.hasEndTarget = false;
    ending.connect();
    ending.run('last');
    assert.equal(ending.nextTarget.children.length, 0);
});

test('the timer counts the time elapsed and the time left', (context) => {
    context.mock.timers.enable({ apis: ['Date', 'setInterval'], now: 1_000_000 });
    const instance = presenter();
    instance.connect();

    assert.equal(instance.elapsedTarget.textContent, '0:00');
    assert.equal(instance.elapsedTarget.getAttribute('datetime'), 'PT0S');
    assert.equal(instance.remainingTarget.textContent, '1:00');
    assert.equal(instance.toggleTarget.textContent, 'Start');
    assert.equal(instance.toggleTarget.getAttribute('aria-pressed'), 'false');

    instance.toggleTimer();
    assert.equal(instance.toggleTarget.textContent, 'Pause');
    assert.equal(instance.toggleTarget.getAttribute('aria-pressed'), 'true');
    context.mock.timers.tick(61_000);
    assert.equal(instance.elapsedTarget.textContent, '1:01');
    assert.equal(instance.remainingTarget.textContent, '-0:01');
    assert.equal(instance.remainingTarget.getAttribute('datetime'), 'PT1S');
    assert.equal(instance.remainingTarget.classList.contains('isOvertime'), true);

    instance.toggleTimer();
    context.mock.timers.tick(10_000);
    assert.equal(instance.elapsedTarget.textContent, '1:01');
    assert.equal(instance.toggleTarget.textContent, 'Start');

    instance.toggleTimer();
    context.mock.timers.tick(4_000);
    assert.equal(instance.elapsedTarget.textContent, '1:05');
    instance.resetTimer();
    assert.equal(instance.elapsedTarget.textContent, '0:00');
    assert.equal(instance.remainingTarget.classList.contains('isOvertime'), false);
    context.mock.timers.tick(2_000);
    assert.equal(instance.elapsedTarget.textContent, '0:02');

    instance.disconnect();
    instance.resetTimer();
    assert.equal(instance.since, null);
    assert.equal(instance.elapsedTarget.textContent, '0:00');
    context.mock.timers.tick(5_000);
    assert.equal(instance.elapsedTarget.textContent, '0:00');
});

test('without a duration or the timer targets, the presenter shows what it can', () => {
    const instance = presenter();
    instance.durationValue = 0;
    instance.remainingTarget.textContent = 'untouched';
    instance.connect();
    assert.equal(instance.remainingTarget.textContent, 'untouched');

    const bare = presenter();
    bare.hasElapsedTarget = false;
    bare.hasRemainingTarget = false;
    bare.hasToggleTarget = false;
    bare.connect();
    bare.toggleTimer();
    bare.disconnect();
    assert.equal(bare.elapsedTarget.textContent, '');
});

test('the audience link opens a window of its own', () => {
    const instance = presenter();
    const opened = [];
    let prevented = 0;
    const event = { currentTarget: { href: '/talk' }, preventDefault: () => (prevented += 1) };

    globalThis.open = (...args) => { opened.push(args); return {}; };
    instance.openAudience(event);
    assert.deepEqual(opened, [['/talk', 'phil-slides-audience', 'popup']]);
    assert.equal(prevented, 1);

    globalThis.open = undefined;
    instance.openAudience(event);
    assert.equal(prevented, 1);
});
