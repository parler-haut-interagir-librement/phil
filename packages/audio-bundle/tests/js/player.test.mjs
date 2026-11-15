// The player controller, under Node's test runner, its peer resolved to a stub by `register.mjs`.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import PlayerController, { barsPath, currentIndex } from '../../assets/src/player_controller.js';

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

test('each amplitude is a bar centred in a box 100 high, at least 1 high', () => {
    assert.equal(barsPath([1, 0.5, 0, 2, 'x']), 'M0.1 0.00h0.8v100.00h-0.8zM1.1 25.00h0.8v50.00h-0.8zM2.1 49.50h0.8v1.00h-0.8zM3.1 0.00h0.8v100.00h-0.8zM4.1 49.50h0.8v1.00h-0.8z');
    assert.equal(barsPath([]), '');
});

test('the current element is the last one started and not ended', () => {
    const chapters = [element(0), element(60), element(120)];
    const cues = [element(0, 2), element(2, 5), element(7, 9)];

    assert.equal(currentIndex(chapters, 0), 0);
    assert.equal(currentIndex(chapters, 119.9), 1);
    assert.equal(currentIndex(chapters, 500), 2);
    assert.equal(currentIndex(cues, 2), 1);
    assert.equal(currentIndex(cues, 6), -1);
    assert.equal(currentIndex([], 1), -1);
});

test('a link of a chapter seeks the audio instead of opening the file', () => {
    const player = new PlayerController();
    const audio = { currentTime: 0, duration: 200 };
    const chapters = [element(0), element(60)];
    let prevented = false;
    Object.assign(player, {
        hasAudioTarget: true,
        audioTarget: audio,
        chapterTargets: chapters,
        cueTargets: [],
        peaksValue: [],
    });

    player.seek({ params: { time: '60.500' }, preventDefault: () => (prevented = true) });

    assert.equal(audio.currentTime, 60.5);
    assert.equal(prevented, true);
    assert.equal(chapters[1].attributes.get('aria-current'), 'true');
    assert.equal(chapters[0].attributes.has('aria-current'), false);
});

test('a link without an instant, or without audio, is followed', () => {
    const player = new PlayerController();
    let prevented = false;
    Object.assign(player, { hasAudioTarget: true, audioTarget: { currentTime: 3 } });

    player.seek({ params: {}, preventDefault: () => (prevented = true) });
    player.seek({ params: { time: '-1' }, preventDefault: () => (prevented = true) });
    assert.equal(prevented, false);

    Object.assign(player, { hasAudioTarget: false });
    player.seek({ params: { time: '1' }, preventDefault: () => (prevented = true) });
    assert.equal(prevented, false);
});

test('a click on the waveform seeks to its point', () => {
    const player = new PlayerController();
    const audio = { currentTime: 0, duration: 100 };
    Object.assign(player, {
        hasAudioTarget: true,
        audioTarget: audio,
        waveformTarget: { getBoundingClientRect: () => ({ width: 400 }) },
        chapterTargets: [],
        cueTargets: [],
        peaksValue: [0.5],
    });

    player.scrub({ offsetX: 100 });
    assert.equal(audio.currentTime, 25);

    player.scrub({ offsetX: 900 });
    assert.equal(audio.currentTime, 100);
});
