// The player controller, under Node's test runner, its peer resolved to a stub by `register.mjs`.

import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import PlayerController, { PARTS, currentIndex, partOf } from '../../assets/src/player_controller.js';

const element = (start, end) => {
    const attributes = new Map();

    return {
        dataset: end === undefined ? { start: String(start) } : { start: String(start), end: String(end) },
        attributes,
        setAttribute(name, value) {
            attributes.set(name, value);
        },
        removeAttribute(name) {
            attributes.delete(name);
        },
    };
};

const media = (canPlayHls = false) => {
    const listeners = new Map();

    return {
        currentTime: 0,
        duration: 200,
        src: 'file.mp4',
        listeners,
        canPlayType: (type) => (canPlayHls && type === 'application/vnd.apple.mpegurl' ? 'maybe' : ''),
        addEventListener(name, listener) {
            listeners.set(name, listener);
        },
        removeEventListener(name) {
            listeners.delete(name);
        },
    };
};

const beacons = [];

beforeEach(() => {
    beacons.length = 0;
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: { sendBeacon: (url, body) => beacons.push([url, JSON.parse(body)]) },
    });
    globalThis.document = { visibilityState: 'visible', addEventListener() {}, removeEventListener() {} };
});

const hosted = (values, video = media()) => {
    const player = new PlayerController();
    Object.assign(player, {
        element: { querySelector: (selector) => (selector === 'video' ? video : null) },
        hasPlayTarget: false,
        chapterTargets: [element(0), element(100)],
        cueTargets: [],
        hlsValue: '',
        trackingValue: false,
        endpointValue: '',
        videoValue: 'talk',
        ...values,
    });
    player.connect();

    return { player, video };
};

test('an instant falls in one of a hundred parts', () => {
    assert.equal(PARTS, 100);
    assert.equal(partOf(0, 200), 0);
    assert.equal(partOf(1.99, 200), 0);
    assert.equal(partOf(2, 200), 1);
    assert.equal(partOf(200, 200), 99);
    assert.equal(partOf(5, Number.NaN), -1);
    assert.equal(partOf(5, 0), -1);
    assert.equal(partOf(-1, 10), -1);
});

test('the current chapter is the last one started', () => {
    assert.equal(currentIndex([element(0), element(10)], 9), 0);
    assert.equal(currentIndex([element(0, 1)], 1), -1);
});

test('the parts watched are sent only when tracking is on', () => {
    const { player, video } = hosted({ trackingValue: true, endpointValue: '/_phil/video/progress' });
    video.currentTime = 3;
    video.listeners.get('timeupdate')();
    video.currentTime = 150;
    video.listeners.get('timeupdate')();

    assert.equal(player.chapterTargets[1].attributes.get('aria-current'), 'true');
    video.listeners.get('ended')();
    assert.equal(beacons.length, 1);
    assert.equal(beacons[0][0], '/_phil/video/progress');
    assert.equal(beacons[0][1].video, 'talk');
    assert.equal(beacons[0][1].parts, `0${'1'}${'0'.repeat(73)}1${'0'.repeat(24)}`);

    player.send();
    assert.equal(beacons.length, 1, 'nothing new, nothing sent');
});

test('without consent nothing is ever sent', () => {
    const { player, video } = hosted({});
    video.currentTime = 50;
    video.listeners.get('timeupdate')();
    video.listeners.get('ended')();
    player.disconnect();

    assert.equal(beacons.length, 0);
    assert.equal(video.listeners.size, 0);
});

test('a page hidden sends what was watched', () => {
    const { player, video } = hosted({ trackingValue: true, endpointValue: '/p' });
    video.currentTime = 10;
    video.listeners.get('timeupdate')();
    player.hidden();
    assert.equal(beacons.length, 0);

    globalThis.document.visibilityState = 'hidden';
    player.hidden();
    assert.equal(beacons.length, 1);
});

test('the HLS stream replaces the files only where the browser plays it', () => {
    assert.equal(hosted({ hlsValue: 'https://cdn.example.com/talk.m3u8' }, media(true)).video.src, 'https://cdn.example.com/talk.m3u8');
    assert.equal(hosted({ hlsValue: 'https://cdn.example.com/talk.m3u8' }, media(false)).video.src, 'file.mp4');
    assert.equal(hosted({}, media(true)).video.src, 'file.mp4');
});

test('a link to an instant seeks the video', () => {
    const { player, video } = hosted({});
    let prevented = false;

    player.seek({ params: { time: '100.250' }, preventDefault: () => (prevented = true) });
    assert.equal(video.currentTime, 100.25);
    assert.equal(prevented, true);

    prevented = false;
    player.seek({ params: { time: 'x' }, preventDefault: () => (prevented = true) });
    assert.equal(prevented, false);
});

test('the facade shows its button and loads the player only when pressed', () => {
    const created = [];
    const frame = () => {
        const attributes = new Map();
        const made = { attributes, focused: false, setAttribute: (name, value) => attributes.set(name, value), focus: () => (made.focused = true) };
        created.push(made);

        return made;
    };
    let children = null;
    const play = { hidden: true };
    const player = new PlayerController();
    Object.assign(player, {
        element: { querySelector: () => null, ownerDocument: { createElement: frame } },
        hasPlayTarget: true,
        playTarget: play,
        facadeTarget: { replaceChildren: (child) => (children = child) },
        embedValue: 'https://www.youtube-nocookie.com/embed/abcdefghijk?autoplay=1',
        titleValue: 'Talk',
        widthValue: 1280,
        heightValue: 720,
    });
    player.connect();

    assert.equal(play.hidden, false);
    assert.equal(created.length, 0);

    player.load();
    assert.equal(children, created[0]);
    assert.equal(created[0].attributes.get('src'), 'https://www.youtube-nocookie.com/embed/abcdefghijk?autoplay=1');
    assert.equal(created[0].attributes.get('title'), 'Talk');
    assert.equal(created[0].attributes.get('width'), '1280');
    assert.equal(created[0].attributes.get('referrerpolicy'), 'strict-origin-when-cross-origin');
    assert.equal(created[0].attributes.get('sandbox'), 'allow-scripts allow-same-origin allow-presentation allow-popups');
    assert.equal(created[0].focused, true);
});
