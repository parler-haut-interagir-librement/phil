// The effects of the celebration and their painter, under Node's test runner: `node --import ./tests/js/register.mjs --test tests/js`.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    aurora,
    balloons,
    BEAM_GAP,
    BEAM_RISE,
    BEAM_SWING,
    bubbles,
    coins,
    confetti,
    EFFECTS,
    fireworks,
    lasers,
    pieces,
    scaled,
    shootingStars,
    SPOT_RISE,
    spotlight,
    streamers,
    trophy,
} from '../../celebration/assets/controllers/celebration_effects.js';
import {
    advance,
    AURORA_ALPHA,
    BEAM_ALPHA,
    draw,
    FIRST_STEP,
    inWorker,
    listen,
    LONGEST_STEP,
    opacity,
    Painter,
    PHYSICS,
    place,
    pop,
    POP_LIFE,
    recolor,
    SOURCE,
    SPOT_DIM,
} from '../../celebration/assets/controllers/celebration_worker.js';

const half = () => 0.5;
const box = (extra = {}) => ({ width: 1000, height: 800, colors: ['red', 'blue', 'green'], duration: 2500, random: half, ...extra });

/** A 2D context that records every call, and the opacity, the colours and the composition each call was made with. */
class RecordingContext {
    constructor() {
        this.calls = [];
        this.globalAlpha = 1;
        this.fillStyle = '';
        this.strokeStyle = '';
        this.lineWidth = 1;
        this.lineCap = 'butt';
        this.globalCompositeOperation = 'source-over';

        return new Proxy(this, {
            get: (target, name) => {
                if (name in target || 'symbol' === typeof name) {
                    return target[name];
                }

                return (...values) => {
                    target.calls.push([name, ...values]);

                    return 'createRadialGradient' === name ? { stops: [], addColorStop(...stop) { this.stops.push(stop); } } : undefined;
                };
            },
        });
    }
}

const names = (context) => context.calls.map(([name]) => name);

test('effects: the list the controller and the server know, confetti first', () => {
    assert.deepEqual(EFFECTS, ['confetti', 'fireworks', 'lasers', 'balloons', 'shooting-stars', 'streamers', 'coins', 'bubbles', 'aurora', 'spotlight', 'trophy']);
    assert.deepEqual(Object.keys(PHYSICS).sort(), [...EFFECTS].sort(), 'each effect has its physics');
    for (const name of EFFECTS) {
        const made = pieces(name, box());
        assert.ok(made.length > 0, `${name} makes pieces`);
        assert.ok(made.every((piece) => Number.isFinite(piece.x) && Number.isFinite(piece.y) && 'string' === typeof piece.color), name);
        assert.ok(made.every((piece) => Number.isFinite(piece.delay) && piece.delay >= 0), name);
    }
    assert.equal(pieces('unknown', box())[0].shape, 'piece');
});

test('effects: a scale counts fewer pieces, one at least', () => {
    assert.equal(scaled(40), 40);
    assert.equal(scaled(40, 0.5), 20);
    assert.equal(scaled(40, 0.26), 10);
    assert.equal(scaled(1, 0.1), 1);
    assert.equal(scaled(3, 0), 1);
    assert.equal(confetti(800, 800, ['red'], half, 0.5).length, 40);
    assert.equal(fireworks(1000, 1000, ['red'], 600, half, 0.5).length, 18);
    assert.equal(pieces('confetti', box({ scale: 0.25 })).length, Math.round(100 * 0.25));
    assert.equal(pieces('balloons', box({ scale: 0.5 })).length, Math.round(11 * 0.5));
});

test('lasers: beams from feet along the bottom, thin, in waves at most two a second, fading in, swinging slowly', () => {
    const beams = lasers(box({ width: 1280 }));
    assert.equal(beams.length, 8);
    assert.ok(beams.every((beam) => 'beam' === beam.shape && 800 === beam.y && beam.x > 0 && beam.x < 1280));
    assert.deepEqual(beams.map((beam) => beam.x), [80, 240, 400, 560, 720, 880, 1040, 1200]);
    assert.deepEqual([...new Set(beams.map((beam) => beam.color))], ['red', 'blue', 'green']);
    assert.ok(beams.every((beam) => beam.size >= 3 && beam.size <= 5), 'thin lines of light');
    assert.ok(beams.every((beam) => beam.length >= Math.hypot(1280, 800)), 'long enough to cross the screen');
    const starts = [...new Set(beams.map((beam) => beam.delay))].sort((a, b) => a - b);
    assert.deepEqual(starts, [0, 500]);
    for (let index = 1; index < starts.length; index++) {
        assert.ok(starts[index] - starts[index - 1] >= BEAM_GAP);
    }
    assert.ok(BEAM_GAP >= 1000 / 2, 'two waves a second at most, under the three flashes of WCAG 2.3.1');
    assert.ok(beams.every((beam) => beam.fadeIn === BEAM_RISE && BEAM_RISE >= 300), 'each beam fades in, never flashes');
    assert.ok(beams.every((beam) => beam.sweep.speed <= (Math.PI * 2 * BEAM_SWING) / 1000 + 1e-12), 'half a swing a second at most');
    assert.ok(BEAM_SWING < 3);
    assert.ok(beams.every((beam) => Math.abs(beam.sweep.base) <= 0.4 && beam.sweep.amplitude <= 0.5));
    assert.deepEqual([...new Set(lasers(box({ duration: 4000 })).map((beam) => beam.delay))].sort((a, b) => a - b), [0, 500, 1000]);
    assert.deepEqual([...new Set(lasers(box({ duration: 600 })).map((beam) => beam.delay))], [0]);
    assert.equal(lasers(box({ width: 100 })).length, 3);
    assert.equal(lasers(box({ width: 4000 })).length, 8);
    assert.equal(lasers(box({ duration: 2500 }))[1].life, 2000, 'it shines to the end of the animation');
    const lone = lasers(box({ width: 100, scale: 0.2 }));
    assert.equal(lone.length, 1);
    assert.equal(lone[0].sweep.base, -0.4);
});

test('lasers: drawn as lines of light at most BEAM_ALPHA, never a fill of the screen', () => {
    const context = new RecordingContext();
    const beams = lasers(box());
    for (let time = 0; time <= 2500; time += 50) {
        advance(beams, time, 50, PHYSICS.lasers);
        draw(context, beams, time, 2500, 1000, 800);
        assert.ok(context.globalAlpha <= 1);
    }
    assert.ok(!names(context).includes('fillRect'), 'no rectangle filled');
    assert.ok(!names(context).includes('fill'), 'nothing filled');
    assert.ok(names(context).includes('stroke'));
    assert.ok(BEAM_ALPHA <= 0.6);
    const lit = [];
    const probe = new RecordingContext();
    Object.defineProperty(probe, 'globalAlpha', { set: (value) => lit.push(value), get: () => 1 });
    draw(probe, [{ ...beams[0], delay: 0, fadeIn: 0, life: Infinity }], 100, 2500, 1000, 800);
    assert.ok(lit.every((value) => value <= BEAM_ALPHA || 1 === value));
    assert.equal(opacity({ delay: 0, life: Infinity, fadeIn: BEAM_RISE }, BEAM_RISE / 2, 4000), 0.5, 'half lit halfway through its rise');
});

test('lasers: a beam swings around its base angle with its sweep', () => {
    const beam = { x: 0, y: 0, vx: 0, vy: 0, angle: 0, spin: 0, delay: 100, sweep: { base: 0.1, amplitude: 0.2, speed: 0.001, phase: 0 } };
    advance([beam], 100 + (Math.PI / 2) * 1000, 16, PHYSICS.lasers);
    assert.ok(Math.abs(beam.angle - 0.3) < 1e-9);
    advance([beam], 50, 16, PHYSICS.lasers);
    assert.ok(Math.abs(beam.angle - 0.3) < 1e-9, 'not before it starts');
});

test('balloons: rising from below the bottom, swaying, burst by a click', () => {
    const made = balloons(box());
    assert.equal(made.length, 11);
    assert.ok(made.every((balloon) => 'balloon' === balloon.shape && balloon.y > 800 && balloon.vy < 0 && balloon.sway.amplitude > 0));
    advance(made, 100, 100, PHYSICS.balloons);
    assert.ok(made[0].y < 800 + made[0].size + 120);
    const one = { shape: 'balloon', x: 100, y: 100, size: 20, delay: 0, life: Infinity, color: 'red' };
    const other = { shape: 'piece', x: 100, y: 100, size: 20, delay: 0, life: Infinity, color: 'blue' };
    assert.deepEqual(pop([other, one], 300, 300, 50), [], 'nothing under the point');
    const sparks = pop([other, one], 110, 110, 50);
    assert.equal(sparks.length, 8);
    assert.ok(sparks.every((spark) => 'spark' === spark.shape && 'red' === spark.color && 50 === spark.delay && POP_LIFE === spark.life));
    assert.ok(one.popped);
    assert.equal(one.life, 50);
    assert.equal(opacity(one, 60, 4000), 0, 'gone once burst');
    assert.deepEqual(pop([one], 110, 110, 60), [], 'burst once');
    const waiting = { shape: 'bubble', x: 100, y: 100, size: 20, delay: 500, life: 900, color: 'red' };
    assert.deepEqual(pop([waiting], 100, 100, 100), [], 'not before it starts');
    const swaying = { shape: 'balloon', x: 100, y: 100, size: 10, delay: 0, life: Infinity, color: 'red', sway: { amplitude: 30, speed: 0.001, phase: Math.PI / 2 } };
    assert.deepEqual(place(swaying, 0), { x: 130, y: 100 });
    assert.equal(pop([swaying], 100, 100, 0).length, 0, 'where it is drawn, not where it started');
    assert.equal(pop([swaying], 130, 100, 0).length, 8);
    const wide = { shape: 'balloon', x: 0, y: 0, size: 10, delay: 0, life: Infinity, color: 'red' };
    assert.equal(pop([wide], 9, 0, 0).length, 0, 'a balloon is narrower than tall');
    assert.equal(pop([{ ...wide, shape: 'bubble' }], 9, 0, 0).length, 8, 'a bubble is round');
});

test('shooting stars: a few, one after the other, across the top in a diagonal', () => {
    const stars = shootingStars(box({ width: 1200 }));
    assert.equal(stars.length, 4);
    assert.deepEqual(stars.map((star) => star.delay), [0, 300, 600, 900]);
    assert.ok(stars.every((star) => star.vx > 0 && star.vy > 0 && star.y <= 240 && 900 === star.life));
    assert.equal(shootingStars(box({ width: 100 })).length, 3);
    assert.equal(shootingStars(box({ width: 9000 })).length, 6);
});

test('streamers: from the two bottom corners, two by two', () => {
    const made = streamers(box());
    assert.equal(made.length, 10);
    assert.deepEqual(made.slice(0, 4).map((streamer) => [streamer.x, streamer.delay]), [[0, 0], [1000, 0], [0, 60], [1000, 60]]);
    assert.ok(made.every((streamer) => 800 === streamer.y && streamer.vy < 0));
    assert.ok(made[0].vx > 0 && made[1].vx < 0, 'towards the middle');
});

test('coins: falling from above and bouncing on the floor', () => {
    const made = coins(box());
    assert.equal(made.length, 40);
    assert.ok(made.every((coin) => coin.y < 0 && coin.floor === 800 - coin.size && 0.5 === coin.bounce));
    const coin = { x: 0, y: 99, vx: 1, vy: 2, angle: 0, spin: 0, delay: 0, floor: 100, bounce: 0.5 };
    advance([coin], 10, 1, { gravity: 0, drag: 0 });
    assert.deepEqual([coin.y, coin.vy, coin.vx], [100, -1, 0.8]);
});

test('bubbles: floating up, each bursting at the end of its own life', () => {
    const made = bubbles(box());
    assert.equal(made.length, 27);
    assert.ok(made.every((bubble) => bubble.vy < 0 && Number.isFinite(bubble.life) && bubble.life >= 600 && bubble.life <= 2500));
    assert.equal(bubbles(box({ duration: 600 }))[0].life, 600);
});

test('aurora: three slow bands across the top, a calm veil', () => {
    const bands = aurora(box());
    assert.equal(bands.length, 3);
    assert.deepEqual(bands.map((band) => Math.round(band.y)), [64, 144, 224]);
    assert.ok(bands.every((band) => 'veil' === band.shape && band.fadeIn >= 600 && band.spin <= 0.001));
    const context = new RecordingContext();
    const seen = [];
    Object.defineProperty(context, 'globalAlpha', { set: (value) => seen.push(value), get: () => 1 });
    draw(context, bands, 1000, 2500, 1000, 800);
    assert.ok(seen.filter((value) => value !== 1).every((value) => value <= AURORA_ALPHA));
    assert.ok(AURORA_ALPHA <= 0.3);
});

test('spotlight: the page dimmed slowly around what it lights, or the middle', () => {
    const [middle] = spotlight(box());
    assert.deepEqual([middle.x, middle.y, middle.size, middle.fadeIn], [500, 400, 144, SPOT_RISE]);
    const [lit] = spotlight(box({ focus: { x: 10, y: 20, radius: 5 } }));
    assert.deepEqual([lit.x, lit.y, lit.size], [10, 20, 24], 'never smaller than 24 pixels');
    assert.ok(SPOT_RISE >= 300, 'a fade, not a flash');
    const context = new RecordingContext();
    const alphas = [];
    Object.defineProperty(context, 'globalAlpha', { set: (value) => alphas.push(value), get: () => 1 });
    draw(context, [{ ...middle }], SPOT_RISE, 2500, 1000, 800);
    assert.ok(names(context).includes('fillRect'));
    assert.ok(alphas.includes(SPOT_DIM));
    assert.ok(SPOT_DIM <= 0.5, 'dimmed, never black');
    assert.equal(context.globalCompositeOperation, 'source-over', 'the composition put back');
});

test('trophy: comes down to its place, eased, then bursts into sparks', () => {
    const [cup, ...sparks] = trophy(box());
    assert.deepEqual([cup.shape, cup.x, cup.from, cup.to, cup.travel], ['trophy', 500, -96, 320, 900]);
    assert.equal(sparks.length, 24);
    assert.ok(sparks.every((spark) => 'spark' === spark.shape && 900 === spark.delay && 1200 === spark.life));
    advance([cup], 450, 16, PHYSICS.trophy);
    assert.ok(Math.abs(cup.y - (-96 + 416 * (1 - 0.5 ** 3))) < 1e-9, 'eased out');
    advance([cup], 2000, 16, PHYSICS.trophy);
    assert.equal(cup.y, 320);
    const [placed] = trophy(box({ focus: { x: 40, y: 50, radius: 10 }, duration: 600 }));
    assert.deepEqual([placed.x, placed.to, placed.travel], [40, 50, 240]);
    assert.equal(trophy(box({ width: 100, height: 100 }))[0].size, 48);
    assert.equal(trophy(box({ width: 4000, height: 4000 }))[0].size, 120);
});

test('painter: draws every shape it knows, in its colour, skipping one it does not', () => {
    const context = new RecordingContext();
    const all = EFFECTS.flatMap((name) => pieces(name, box()).map((piece) => ({ ...piece, delay: 0, fadeIn: 0, life: Infinity })));
    draw(context, [...all, { shape: 'unknown', x: 0, y: 0, delay: 0, life: Infinity, color: 'x' }], 100, 2500, 1000, 800);
    for (const call of ['ellipse', 'arc', 'stroke', 'fill', 'fillRect', 'quadraticCurveTo', 'lineTo', 'createRadialGradient']) {
        assert.ok(names(context).includes(call), call);
    }
    assert.equal(context.globalAlpha, 1, 'put back');
    assert.equal(names(context)[0], 'clearRect');
});

test('painter: the colours of the theme given anew', () => {
    const made = [{ color: 'red' }, { color: 'blue' }, { color: 'pink' }];
    recolor(made, ['red', 'blue'], ['darkred']);
    assert.deepEqual(made.map((piece) => piece.color), ['darkred', 'blue', 'pink']);
});

test('painter: paints frame by frame, holds, takes up, ends and frees its bitmap', () => {
    const posted = [];
    const frames = new Map();
    let next = 1;
    let halted = false;
    const painter = new Painter((message) => posted.push(message), {
        frame: (callback) => {
            frames.set(next, callback);

            return next++;
        },
        cancel: (id) => frames.delete(id),
        halt: () => halted,
    });
    const context = new RecordingContext();
    const canvas = { width: 1, height: 1, getContext: (type) => ('2d' === type ? context : null) };
    let now = 0;
    const run = (count, step = 16) => {
        for (let i = 0; i < count && frames.size > 0; i++) {
            const [id, callback] = frames.entries().next().value;
            frames.delete(id);
            now += step;
            callback(now);
        }
    };

    assert.equal(painter.receive({ type: 'nothing' }), undefined);
    painter.receive({ type: 'pop', x: 0, y: 0 });
    painter.receive({ type: 'recolor', from: [], to: [] });
    painter.receive({ type: 'resume' });
    assert.equal(frames.size, 0, 'nothing to paint yet');
    assert.equal(painter.receive({ type: 'start', id: 7, canvas, pieces: balloons(box()), width: 100, height: 50, ratio: 2, duration: 200, effect: 'balloons' }), true);
    assert.deepEqual([canvas.width, canvas.height], [200, 100]);
    assert.deepEqual(context.calls[0], ['scale', 2, 2]);
    run(1);
    assert.equal(painter.elapsed, FIRST_STEP);
    painter.receive({ type: 'hold' });
    assert.equal(frames.size, 0);
    painter.receive({ type: 'resume' });
    painter.receive({ type: 'resume' });
    assert.equal(frames.size, 1);
    run(1, 1000);
    assert.equal(painter.elapsed, 2 * FIRST_STEP, 'the first frame after a hold counts as one');
    run(1, 1000);
    assert.equal(painter.elapsed, 2 * FIRST_STEP + LONGEST_STEP);
    const before = painter.pieces.length;
    painter.receive({ type: 'pop', x: -999, y: -999 });
    assert.equal(painter.pieces.length, before);
    painter.receive({ type: 'recolor', from: ['red'], to: ['pink'] });
    assert.ok(painter.pieces.some((piece) => 'pink' === piece.color));
    run(10);
    assert.deepEqual(posted, [{ type: 'end', id: 7 }]);
    assert.deepEqual([canvas.width, canvas.height], [0, 0], 'its bitmap freed');
    assert.equal(frames.size, 0);

    painter.start({ canvas, pieces: [], width: 10, height: 10, duration: 1000, effect: 'unknown', hidden: true });
    assert.equal(painter.physics, PHYSICS.confetti);
    assert.equal(frames.size, 0, 'a hidden page waits');
    painter.receive({ type: 'resume' });
    halted = true;
    run(1);
    assert.deepEqual(posted.at(-1), { type: 'end', id: null }, 'halted at the frame');
    painter.start({ canvas, pieces: [], width: 10, height: 10, duration: 1000, effect: 'confetti' });
    painter.receive({ type: 'stop' });
    assert.equal(posted.length, 3);
    painter.receive({ type: 'stop' });
    assert.equal(posted.length, 3, 'stopped once');
    assert.equal(painter.start({ canvas: { getContext: () => null }, pieces: [], width: 1, height: 1, duration: 1, effect: 'confetti' }), false);
});

test('painter: without frames of its own, a timer of about sixty a second', async () => {
    const painter = new Painter(() => {});
    assert.equal('function', typeof painter.frame);
    const scope = globalThis.requestAnimationFrame;
    delete globalThis.requestAnimationFrame;
    try {
        const timed = new Painter(() => {});
        const id = timed.frame(() => {});
        timed.cancel(id);
    } finally {
        globalThis.requestAnimationFrame = scope;
    }
});

test('worker: listens to the page in a worker scope, posts the end of what cannot paint', () => {
    const listeners = new Map();
    const posted = [];
    const scope = {
        addEventListener: (type, listener) => listeners.set(type, listener),
        postMessage: (message) => posted.push(message),
        requestAnimationFrame: () => 1,
        cancelAnimationFrame: () => {},
    };
    const painter = listen(scope);
    assert.ok(painter instanceof Painter);
    listeners.get('message')({ data: { type: 'start', id: 3, canvas: { getContext: () => null }, pieces: [], width: 1, height: 1, duration: 100 } });
    assert.deepEqual(posted, [{ type: 'end', id: 3 }]);
    listeners.get('message')({ data: { type: 'hold' } });
    listeners.get('message')({ data: null });
    assert.equal(posted.length, 1);
    listeners.get('message')({ data: { type: 'start', canvas: { getContext: () => null } } });
    assert.deepEqual(posted.at(-1), { type: 'end', id: null });
    assert.equal(inWorker(globalThis), false, 'Node is no worker');
    class WorkerGlobalScope {}
    const worker = new WorkerGlobalScope();
    worker.WorkerGlobalScope = WorkerGlobalScope;
    assert.equal(inWorker(worker), true);
    assert.ok(SOURCE.endsWith('/celebration/assets/controllers/celebration_worker.js'), 'its own address');
});
