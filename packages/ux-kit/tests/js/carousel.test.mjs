import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { setTimeout as sleep } from 'node:timers/promises';

import CarouselController, { direction, standing } from '../../carousel/assets/controllers/carousel_controller.js';
import { browser, FakeElement, keydown, stimulate } from './fake.mjs';

beforeEach(() => {
    browser();
});

const setUp = ({ options = {}, orientation = 'horizontal', autoplay = 0, size = 300, slides = 3 } = {}) => {
    const items = Array.from({ length: slides }, (_, index) => ({ offsetLeft: index * size, offsetTop: index * size }));
    const scrolls = [];
    const viewport = Object.assign(new FakeElement(), {
        firstElementChild: { children: items },
        scrollLeft: 0,
        scrollTop: 0,
        clientWidth: size,
        clientHeight: size,
        scrollWidth: size * slides,
        scrollHeight: size * slides,
        scrollBy: (options_) => scrolls.push(['by', options_]),
        scrollTo: (options_) => scrolls.push(['to', options_]),
    });
    const previous = Object.assign(new FakeElement(), { hidden: true });
    const next = Object.assign(new FakeElement(), { hidden: true });
    const toggle = Object.assign(new FakeElement(), { hidden: true });
    const controller = stimulate(CarouselController, {
        targets: { viewport: [viewport], previous: [previous], next: [next], toggle: [toggle] },
        values: { orientation, options, autoplay, pauseLabel: 'Pause', playLabel: 'Play' },
    });

    return { controller, viewport, previous, next, toggle, scrolls };
};

test('the direction of a key', () => {
    assert.equal(direction('ArrowRight'), 1);
    assert.equal(direction('ArrowLeft'), -1);
    assert.equal(direction('ArrowRight', { rtl: true }), -1);
    assert.equal(direction('ArrowLeft', { rtl: true }), 1);
    assert.equal(direction('ArrowDown', { vertical: true }), 1);
    assert.equal(direction('ArrowUp', { vertical: true }), -1);
    assert.equal(direction('ArrowRight', { vertical: true }), 0);
    assert.equal(direction('ArrowDown'), 0);
});

test('where a scroll stands, also negative right to left', () => {
    assert.deepEqual(standing(0, 600, 300, 3), { atStart: true, atEnd: false, index: 0 });
    assert.deepEqual(standing(300, 600, 300, 3), { atStart: false, atEnd: false, index: 1 });
    assert.deepEqual(standing(-600, 600, 300, 3), { atStart: false, atEnd: true, index: 2 });
    assert.deepEqual(standing(5000, 600, 300, 3), { atStart: false, atEnd: true, index: 2 });
    assert.equal(standing(0, 0, 0, 0).index, 0);
});

test('without JavaScript the buttons are hidden; connected they show and the ends are disabled', () => {
    const { controller, previous, next, viewport } = setUp();
    controller.connect();
    assert.equal(previous.hidden, false);
    assert.equal(next.hidden, false);
    assert.equal(previous.disabled, true);
    assert.equal(next.disabled, false);
    assert.deepEqual(controller.dispatched[0], ['select', { detail: { index: 0, count: 3 } }]);

    viewport.scrollLeft = 600;
    controller.update();
    assert.equal(previous.disabled, false);
    assert.equal(next.disabled, true);
    assert.equal(controller.dispatched.length, 2);
    controller.update();
    assert.equal(controller.dispatched.length, 2);
});

test('with loop nothing is disabled and the ends wrap around', () => {
    const { controller, previous, next, viewport, scrolls } = setUp({ options: { loop: true } });
    controller.connect();
    assert.equal(previous.disabled, false);
    assert.equal(next.disabled, false);
    controller.scrollPrev();
    assert.deepEqual(scrolls.pop(), ['to', { left: 900, behavior: 'smooth' }]);
    viewport.scrollLeft = 600;
    controller.scrollNext();
    assert.deepEqual(scrolls.pop(), ['to', { top: 0, left: 0, behavior: 'smooth' }]);
});

test('the buttons scroll by one slide, along the axis and the direction of the page', () => {
    const { controller, scrolls } = setUp();
    controller.connect();
    controller.scrollNext();
    assert.deepEqual(scrolls.pop(), ['by', { left: 300, behavior: 'smooth' }]);
    controller.scrollPrev();
    assert.deepEqual(scrolls.pop(), ['by', { left: -300, behavior: 'smooth' }]);

    browser({ direction: 'rtl', reduced: true });
    controller.scrollNext();
    assert.deepEqual(scrolls.pop(), ['by', { left: -300, behavior: 'auto' }]);

    const vertical = setUp({ orientation: 'vertical' });
    vertical.controller.connect();
    vertical.controller.scrollNext();
    assert.deepEqual(vertical.scrolls.pop(), ['by', { top: 300, behavior: 'auto' }]);
});

test('one slide alone scrolls by the viewport', () => {
    const { controller, scrolls } = setUp({ slides: 1, size: 500 });
    controller.connect();
    controller.scrollNext();
    assert.equal(scrolls.pop()[1].left, 500);
});

test('the arrows move it, other keys do not', () => {
    const { controller, scrolls, viewport } = setUp();
    controller.connect();
    const event = keydown('ArrowRight', viewport);
    controller.handleKeydown(event);
    assert.equal(event.prevented, true);
    assert.equal(scrolls.length, 1);
    const other = keydown('a', viewport);
    controller.handleKeydown(other);
    assert.equal(other.prevented, false);
    assert.equal(scrolls.length, 1);
});

test('automatic play has its button, holds on hover and focus, and goes on', async () => {
    const { controller, toggle, scrolls } = setUp({ autoplay: 10 });
    controller.connect();
    assert.equal(toggle.hidden, false);
    await sleep(35);
    assert.ok(scrolls.length > 0);

    controller.hold({ type: 'mouseenter' });
    let count = scrolls.length;
    await sleep(30);
    assert.equal(scrolls.length, count);
    controller.hold({ type: 'focusin' });
    controller.release({ type: 'mouseleave' });
    await sleep(30);
    assert.equal(scrolls.length, count);
    controller.release({ type: 'focusout', relatedTarget: null });
    await sleep(30);
    assert.ok(scrolls.length > count);

    controller.toggleAutoplay();
    assert.equal(toggle.textContent, 'Play');
    count = scrolls.length;
    await sleep(30);
    assert.equal(scrolls.length, count);
    controller.toggleAutoplay();
    assert.equal(toggle.textContent, 'Pause');
    controller.disconnect();
});

test('a focus moving inside keeps it held', () => {
    const { controller } = setUp();
    const inner = new FakeElement();
    controller.element.contains = (element) => element === inner;
    controller.connect();
    controller.hold({ type: 'focusin' });
    controller.release({ type: 'focusout', relatedTarget: inner });
    controller.disconnect();
});

test('automatic play never runs under reduced motion, nor scrolls a hidden page', async () => {
    browser({ reduced: true });
    const reduced = setUp({ autoplay: 10 });
    reduced.controller.connect();
    assert.equal(reduced.toggle.hidden, true);
    await sleep(30);
    assert.equal(reduced.scrolls.length, 0);
    reduced.controller.disconnect();

    browser({ hidden: true });
    const hidden = setUp({ autoplay: 10 });
    hidden.controller.connect();
    await sleep(30);
    assert.equal(hidden.scrolls.length, 0);
    hidden.controller.disconnect();
});
