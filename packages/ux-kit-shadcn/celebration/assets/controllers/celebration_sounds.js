/*
 * The sounds of the celebrations, made by the Web Audio API on the device: no file is fetched, so none carries a licence.
 * Silent until the person turns them on, by a button, which is the gesture a browser asks for before a sound; and a
 * short, soft sound each time: a chime, pops for fireworks, a roll and a cymbal for a drum roll, claps for applause, a
 * whoosh for a rocket.
 */

/** The key of the local storage of the browser that keeps whether the person turned the sounds on. */
export const SOUND_KEY = 'phil-celebrations-sound';

/** The value of `SOUND_KEY` when the sounds are on; anything else, or nothing, keeps them off. */
export const SOUND_ON = 'on';

/** The loudest a sound gets, a share of the volume of the device. */
export const VOLUME = 0.15;

/** The sound of each effect that has one of its own; the others chime. */
export const SOUNDS = { fireworks: 'pops', 'drum-roll': 'roll', applause: 'claps', rocket: 'whoosh' };

/** The notes of the chime, in hertz: a major chord, played one after the other. */
export const CHIME = [523.25, 659.25, 783.99];

/** How many strikes a second the drum roll plays. */
export const ROLL_RATE = 16;

/** How many claps the applause plays. */
export const CLAPS = 40;

/** The noise each context shares, made once. */
const noises = new WeakMap();

/** The sound of an effect: its own, or the chime. */
export function soundOf(effect) {
    return SOUNDS[effect] ?? 'chime';
}

/** Whether the person turned the sounds on, from a local storage; off when it cannot be read. */
export function soundOn(storage) {
    try {
        return SOUND_ON === storage?.getItem(SOUND_KEY);
    } catch {
        return false;
    }
}

/** Keeps whether the sounds are on in a local storage, where it can be written. */
export function keepSound(storage, on) {
    try {
        if (on) {
            storage?.setItem(SOUND_KEY, SOUND_ON);
        } else {
            storage?.removeItem(SOUND_KEY);
        }
    } catch {
        // Without storage, the choice lasts as long as the page.
    }
}

/** A second of white noise for a context, made once and shared. */
export function noise(context, random = Math.random) {
    if (noises.has(context)) {
        return noises.get(context);
    }

    const buffer = context.createBuffer(1, context.sampleRate, context.sampleRate);
    const samples = buffer.getChannelData(0);

    for (let index = 0; index < samples.length; index++) {
        samples[index] = random() * 2 - 1;
    }

    noises.set(context, buffer);

    return buffer;
}

/** An envelope on a gain: up to `peak` in `attack` seconds from `at`, then down to silence by `at + length`. */
function envelope(gain, at, length, peak, attack = 0.005) {
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.linearRampToValueAtTime(peak, at + attack);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + length);
}

/** A burst of noise at `at`, `length` seconds long, through a filter, swept `to` a frequency if asked, up to `peak`. */
function burst(context, at, length, filter, peak, random) {
    const source = context.createBufferSource();
    source.buffer = noise(context, random);
    const shape = context.createBiquadFilter();
    shape.type = filter.type;
    shape.frequency.setValueAtTime(filter.frequency, at);

    if (undefined !== filter.to) {
        shape.frequency.exponentialRampToValueAtTime(filter.to, at + length);
    }

    shape.Q.setValueAtTime(filter.q ?? 1, at);
    const gain = context.createGain();
    envelope(gain, at, length, peak);
    source.connect(shape).connect(gain).connect(context.destination);
    source.start(at, random() * 0.5);
    source.stop(at + length + 0.05);

    return source;
}

/** A note at `at`, a sine of `frequency` hertz fading over `length` seconds. */
function note(context, at, frequency, length) {
    const oscillator = context.createOscillator();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(frequency, at);
    const gain = context.createGain();
    envelope(gain, at, length, VOLUME, 0.02);
    oscillator.connect(gain).connect(context.destination);
    oscillator.start(at);
    oscillator.stop(at + length + 0.05);

    return oscillator;
}

/**
 * Plays the sound of an effect on an audio context, for a run of `duration` milliseconds and a drum roll of `roll`
 * milliseconds; returns the sources it started.
 */
export function sound(context, effect, { duration, roll = duration, random = Math.random } = {}) {
    const now = context.currentTime;
    const seconds = Math.max(0.3, duration / 1000);

    switch (soundOf(effect)) {
        case 'pops': {
            const bursts = Math.max(1, Math.min(4, 1 + Math.floor((duration - 1200) / 500)));

            return Array.from({ length: bursts }, (_, index) =>
                burst(context, now + index * 0.5, 0.3, { type: 'lowpass', frequency: 1200 }, VOLUME, random),
            );
        }
        case 'roll': {
            const length = Math.max(0.2, roll / 1000);
            const strikes = Math.max(1, Math.floor(length * ROLL_RATE));
            const sources = Array.from({ length: strikes }, (_, index) =>
                burst(context, now + index / ROLL_RATE, 0.05, { type: 'bandpass', frequency: 1800 }, VOLUME * (0.3 + (0.7 * index) / strikes), random),
            );
            sources.push(burst(context, now + length, 1.2, { type: 'highpass', frequency: 5000 }, VOLUME, random));

            return sources;
        }
        case 'claps':
            return Array.from({ length: CLAPS }, () =>
                burst(context, now + random() * Math.min(1.5, seconds), 0.04, { type: 'bandpass', frequency: 1500 }, VOLUME * 0.6, random),
            );
        case 'whoosh':
            return [burst(context, now, seconds * 0.8, { type: 'lowpass', frequency: 300, to: 3000 }, VOLUME, random)];
        default:
            return CHIME.map((frequency, index) => note(context, now + index * 0.12, frequency, 0.6));
    }
}
