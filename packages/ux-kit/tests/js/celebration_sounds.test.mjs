// The sounds of the celebration, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    CHIME,
    CLAPS,
    keepSound,
    noise,
    ROLL_RATE,
    SOUND_KEY,
    SOUND_ON,
    SOUNDS,
    sound,
    soundOf,
    soundOn,
    VOLUME,
} from '../../celebration/assets/controllers/celebration_sounds.js';

/** An audio context that records each node it makes, what it is set to, and when each source starts. */
class RecordingAudio {
    constructor() {
        this.currentTime = 10;
        this.sampleRate = 4;
        this.destination = { destination: true };
        this.nodes = [];
        this.buffers = 0;
    }

    createBuffer(channels, length, rate) {
        this.buffers++;
        const data = new Float32Array(length);

        return { channels, length, rate, data, getChannelData: () => data };
    }

    node(kind) {
        const param = (name) => {
            const calls = [];

            return {
                calls,
                setValueAtTime: (...values) => calls.push([`${name}.set`, ...values]),
                linearRampToValueAtTime: (...values) => calls.push([`${name}.linear`, ...values]),
                exponentialRampToValueAtTime: (...values) => calls.push([`${name}.exponential`, ...values]),
            };
        };
        const node = { kind, gain: param('gain'), frequency: param('frequency'), Q: param('Q'), connected: [], starts: [], stops: [] };
        node.connect = (next) => {
            node.connected.push(next);

            return next;
        };
        node.start = (...values) => node.starts.push(values);
        node.stop = (at) => node.stops.push(at);
        this.nodes.push(node);

        return node;
    }

    createBufferSource() {
        return this.node('source');
    }

    createBiquadFilter() {
        return this.node('filter');
    }

    createGain() {
        return this.node('gain');
    }

    createOscillator() {
        return this.node('oscillator');
    }
}

const half = () => 0.5;
const of = (audio, kind) => audio.nodes.filter((node) => kind === node.kind);

test('sounds: an effect has its own sound, or the chime', () => {
    assert.deepEqual(SOUNDS, { fireworks: 'pops', 'drum-roll': 'roll', applause: 'claps', rocket: 'whoosh' });
    assert.equal(soundOf('applause'), 'claps');
    assert.equal(soundOf('confetti'), 'chime');
    assert.equal(soundOf(undefined), 'chime');
    assert.ok(VOLUME <= 0.2, 'soft');
});

test('sounds: off unless the person turned them on, kept in the local storage where it can be', () => {
    const items = new Map();
    const storage = { getItem: (key) => items.get(key) ?? null, setItem: (key, value) => items.set(key, value), removeItem: (key) => items.delete(key) };
    assert.equal(soundOn(storage), false);
    keepSound(storage, true);
    assert.equal(items.get(SOUND_KEY), SOUND_ON);
    assert.equal(soundOn(storage), true);
    items.set(SOUND_KEY, 'yes');
    assert.equal(soundOn(storage), false, 'on alone turns them on');
    keepSound(storage, false);
    assert.equal(items.has(SOUND_KEY), false);
    assert.equal(soundOn(null), false);
    const blocked = {
        getItem() {
            throw new Error('blocked');
        },
        setItem() {
            throw new Error('blocked');
        },
        removeItem() {
            throw new Error('blocked');
        },
    };
    assert.equal(soundOn(blocked), false);
    assert.doesNotThrow(() => keepSound(blocked, true));
    assert.doesNotThrow(() => keepSound(null, false));
});

test('sounds: a second of noise made once for each context', () => {
    const audio = new RecordingAudio();
    const buffer = noise(audio, () => 0.75);
    assert.equal(buffer.length, 4);
    assert.deepEqual([...buffer.data], [0.5, 0.5, 0.5, 0.5]);
    assert.equal(noise(audio), buffer);
    assert.equal(audio.buffers, 1);
});

test('sounds: a chime of three notes, one after the other, soft and fading', () => {
    const audio = new RecordingAudio();
    const sources = sound(audio, 'confetti', { duration: 2500 });
    assert.equal(sources.length, 3);
    assert.deepEqual(
        of(audio, 'oscillator').map((node) => [node.type, node.frequency.calls[0][1], node.starts[0][0]]),
        CHIME.map((frequency, index) => ['sine', frequency, 10 + index * 0.12]),
    );
    const [gain] = of(audio, 'gain');
    assert.deepEqual(gain.gain.calls, [
        ['gain.set', 0.0001, 10],
        ['gain.linear', VOLUME, 10.02],
        ['gain.exponential', 0.0001, 10.6],
    ]);
    assert.ok(of(audio, 'oscillator').every((node) => node.connected[0].kind === 'gain'));
    assert.ok(of(audio, 'gain').every((node) => node.connected[0] === audio.destination));
});

test('sounds: pops for each burst of fireworks, four at most', () => {
    const audio = new RecordingAudio();
    assert.equal(sound(audio, 'fireworks', { duration: 2500, random: half }).length, 3);
    assert.deepEqual(
        of(audio, 'source').map((node) => node.starts[0]),
        [
            [10, 0.25],
            [10.5, 0.25],
            [11, 0.25],
        ],
    );
    assert.ok(of(audio, 'filter').every((node) => 'lowpass' === node.type));
    assert.equal(sound(new RecordingAudio(), 'fireworks', { duration: 600, random: half }).length, 1);
    assert.equal(sound(new RecordingAudio(), 'fireworks', { duration: 4000, random: half }).length, 4);
});

test('sounds: a drum roll growing louder for its roll, then a cymbal', () => {
    const audio = new RecordingAudio();
    const sources = sound(audio, 'drum-roll', { duration: 2500, roll: 1000, random: half });
    assert.equal(sources.length, ROLL_RATE + 1);
    const filters = of(audio, 'filter');
    assert.ok(filters.slice(0, -1).every((node) => 'bandpass' === node.type));
    assert.equal(filters.at(-1).type, 'highpass');
    assert.deepEqual(sources.at(-1).starts[0], [11, 0.25], 'the cymbal when the roll ends');
    const peaks = of(audio, 'gain').map((node) => node.gain.calls[1][1]);
    assert.ok(peaks.slice(0, -1).every((peak, index, all) => 0 === index || peak > all[index - 1]), 'louder and louder');
    assert.equal(peaks[0], VOLUME * 0.3);
    assert.equal(sound(new RecordingAudio(), 'drum-roll', { duration: 600, random: half }).length, Math.floor(0.6 * ROLL_RATE) + 1, 'the run without a roll');
    assert.equal(sound(new RecordingAudio(), 'drum-roll', { duration: 100, roll: 10, random: half }).length, 4, 'a short roll of a fifth of a second at least');
});

test('sounds: claps scattered over a second and a half, and a whoosh rising for a rocket', () => {
    const audio = new RecordingAudio();
    assert.equal(sound(audio, 'applause', { duration: 2500, random: half }).length, CLAPS);
    assert.ok(of(audio, 'source').every((node) => 10.75 === node.starts[0][0]));
    const short = new RecordingAudio();
    sound(short, 'applause', { duration: 600, random: half });
    assert.ok(of(short, 'source').every((node) => 10.3 === node.starts[0][0]), 'within the run');

    const rocket = new RecordingAudio();
    assert.equal(sound(rocket, 'rocket', { duration: 2000, random: half }).length, 1);
    const [filter] = of(rocket, 'filter');
    assert.deepEqual(filter.frequency.calls, [
        ['frequency.set', 300, 10],
        ['frequency.exponential', 3000, 11.6],
    ]);
    assert.deepEqual(of(rocket, 'source')[0].stops, [11.65]);
    const tiny = new RecordingAudio();
    sound(tiny, 'rocket', { duration: 0 });
    assert.ok(Math.abs(of(tiny, 'source')[0].stops[0] - (10 + 0.3 * 0.8 + 0.05)) < 1e-9, 'three tenths of a second at least');
});
