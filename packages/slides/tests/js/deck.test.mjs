// The shared logic of the controllers, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { CHANNEL_PREFIX, clock, command, format, go, isoDuration, Position, read, slideFromHash } from '../../assets/src/lib/deck.js';

const key = (name, options = {}) => ({ key: name, defaultPrevented: false, altKey: false, ctrlKey: false, metaKey: false, target: { tagName: 'DIV', isContentEditable: false }, ...options });

test('each key has its command', () => {
    const expected = {
        ArrowRight: 'next', ArrowDown: 'next', PageDown: 'next', ' ': 'next',
        ArrowLeft: 'previous', ArrowUp: 'previous', PageUp: 'previous',
        Home: 'first', End: 'last', Escape: 'exit', f: 'fullscreen', F: 'fullscreen',
    };

    for (const [name, result] of Object.entries(expected)) {
        assert.equal(command(key(name)), result, name);
    }

    assert.equal(command(key('a')), null);
    assert.equal(command(key('toString')), null);
});

test('a key with a modifier, typed in a field or already taken is left alone', () => {
    assert.equal(command(key('ArrowRight', { altKey: true })), null);
    assert.equal(command(key('ArrowRight', { ctrlKey: true })), null);
    assert.equal(command(key('ArrowRight', { metaKey: true })), null);
    assert.equal(command(key('ArrowRight', { defaultPrevented: true })), null);

    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT']) {
        assert.equal(command(key('ArrowRight', { target: { tagName, isContentEditable: false } })), null);
    }

    assert.equal(command(key('ArrowRight', { target: { tagName: 'P', isContentEditable: true } })), null);
    assert.equal(command(key('ArrowRight', { target: null })), 'next');
    assert.equal(command(key('ArrowRight', { shiftKey: true })), 'next');
});

test('next reveals the fragments, then moves to the next slide', () => {
    const position = new Position([2, 0, 1]);

    assert.deepEqual([position.slide, position.fragment, position.count], [0, 0, 3]);
    assert.equal(position.next(), true);
    assert.deepEqual([position.slide, position.fragment], [0, 1]);
    position.next();
    assert.deepEqual([position.slide, position.fragment], [0, 2]);
    position.next();
    assert.deepEqual([position.slide, position.fragment], [1, 0]);
    position.next();
    assert.deepEqual([position.slide, position.fragment], [2, 0]);
    position.next();
    assert.deepEqual([position.slide, position.fragment], [2, 1]);
    assert.equal(position.next(), false);
    assert.deepEqual([position.slide, position.fragment], [2, 1]);
});

test('previous hides the fragments, then goes back with every fragment shown', () => {
    const position = new Position([2, 0, 1]);
    position.go(2, 1);

    assert.equal(position.previous(), true);
    assert.deepEqual([position.slide, position.fragment], [2, 0]);
    position.previous();
    assert.deepEqual([position.slide, position.fragment], [1, 0]);
    position.previous();
    assert.deepEqual([position.slide, position.fragment], [0, 2]);
    position.previous();
    position.previous();
    assert.deepEqual([position.slide, position.fragment], [0, 0]);
    assert.equal(position.previous(), false);
});

test('first and last go to the ends', () => {
    const position = new Position([0, 3]);

    assert.equal(position.last(), true);
    assert.deepEqual([position.slide, position.fragment], [1, 3]);
    assert.equal(position.last(), false);
    assert.equal(position.first(), true);
    assert.deepEqual([position.slide, position.fragment], [0, 0]);
    assert.equal(position.first(), false);
});

test('a position is brought within the deck', () => {
    const position = new Position([1, 2]);

    assert.equal(position.go(9, 9), true);
    assert.deepEqual([position.slide, position.fragment], [1, 2]);
    position.go(-3, -1);
    assert.deepEqual([position.slide, position.fragment], [0, 0]);
    position.go(1.5, 'x');
    assert.deepEqual([position.slide, position.fragment], [0, 0]);
    assert.equal(position.go(0, 0), false);
    assert.equal(position.go(0, 1), true);
});

test('an empty deck never moves', () => {
    const position = new Position([]);

    assert.equal(position.next(), false);
    assert.equal(position.previous(), false);
    assert.equal(position.last(), false);
    assert.equal(position.first(), false);
    assert.deepEqual([position.slide, position.fragment], [0, 0]);
});

test('an address names a slide by its anchor', () => {
    assert.equal(slideFromHash('#slide-1', 3), 0);
    assert.equal(slideFromHash('#slide-3', 3), 2);
    assert.equal(slideFromHash('#slide-4', 3), null);
    assert.equal(slideFromHash('#slide-0', 3), null);
    assert.equal(slideFromHash('#slide-01', 3), null);
    assert.equal(slideFromHash('#slide-2x', 3), null);
    assert.equal(slideFromHash('x#slide-2', 3), null);
    assert.equal(slideFromHash('#slide-1234567', 9999999), null);
    assert.equal(slideFromHash('#slide-123456', 999999), 123455);
    assert.equal(slideFromHash('', 3), null);
    assert.equal(slideFromHash(undefined, 3), null);
});

test('a pattern has its placeholders replaced', () => {
    assert.equal(format('Slide %number% of %count%: %title%', { number: 2, count: 5, title: 'Hi' }), 'Slide 2 of 5: Hi');
    assert.equal(format('%unknown% %number%', { number: 1 }), '%unknown% 1');
    assert.equal(format('%toString%', {}), '%toString%');
});

test('the messages say where a window stands', () => {
    const position = new Position([1, 1]);
    position.go(1, 1);

    assert.deepEqual(go(position, 'me'), { type: 'go', slide: 1, fragment: 1, sender: 'me' });
    assert.equal(CHANNEL_PREFIX, 'phil-slides:');
});

test('only the messages of another window of the deck are read', () => {
    assert.deepEqual(read({ type: 'hello', sender: 'other' }, 'me'), { type: 'hello' });
    assert.deepEqual(read({ type: 'go', slide: 2, fragment: 0, sender: 'other' }, 'me'), { type: 'go', slide: 2, fragment: 0 });
    assert.equal(read({ type: 'go', slide: 2, fragment: 0, sender: 'me' }, 'me'), null);
    assert.equal(read({ type: 'go', slide: '2', fragment: 0, sender: 'other' }, 'me'), null);
    assert.equal(read({ type: 'go', slide: 2, fragment: 0.5, sender: 'other' }, 'me'), null);
    assert.equal(read({ type: 'go', slide: 2, fragment: 0, sender: 7 }, 'me'), null);
    assert.equal(read({ type: 'other', sender: 'other' }, 'me'), null);
    assert.equal(read(null, 'me'), null);
    assert.equal(read('hello', 'me'), null);
});

test('a duration reads as a clock', () => {
    assert.equal(clock(0), '0:00');
    assert.equal(clock(5.9), '0:05');
    assert.equal(clock(65), '1:05');
    assert.equal(clock(3600), '1:00:00');
    assert.equal(clock(3723), '1:02:03');
    assert.equal(clock(-0.5), '0:00');
    assert.equal(clock(-1), '-0:01');
    assert.equal(clock(-125), '-2:05');
    assert.equal(isoDuration(65.7), 'PT65S');
    assert.equal(isoDuration(-30), 'PT30S');
});
