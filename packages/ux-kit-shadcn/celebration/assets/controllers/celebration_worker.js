/*
 * The painter of the celebrations: the motion and the drawing of the pieces of an effect on a canvas, frame by frame.
 *
 * It runs in a worker on an `OffscreenCanvas` the `celebration` controller transfers to it, off the main thread, where
 * the browser allows it; the controller imports it otherwise and paints on the canvas of the page with the same code.
 * It holds no state between two celebrations but the one it paints, and imports nothing, so that the worker loads alone.
 */

/**
 * The address of this module, versioned as the asset pipeline serves it, which the controller gives to `new Worker()`:
 * the page imports it by an `import` its pipeline rewrites, so the worker loads the same file, from the same origin.
 */
export const SOURCE = import.meta.url;

/** The pull of each effect, in pixels per square millisecond, and the share of their speed the pieces keep per millisecond. */
export const PHYSICS = {
    confetti: { gravity: 0.00012, drag: 0.0006 },
    fireworks: { gravity: 0.00008, drag: 0.0018 },
    lasers: { gravity: 0, drag: 0 },
    balloons: { gravity: -0.00002, drag: 0.0004 },
    'shooting-stars': { gravity: 0, drag: 0 },
    streamers: { gravity: 0.0004, drag: 0.0008 },
    coins: { gravity: 0.0012, drag: 0.0002 },
    bubbles: { gravity: -0.00001, drag: 0.0008 },
    aurora: { gravity: 0, drag: 0 },
    spotlight: { gravity: 0, drag: 0 },
    trophy: { gravity: 0.00008, drag: 0.0018 },
};

/** The highest opacity of a laser beam: a thin line of light, never a flash of the whole screen. */
export const BEAM_ALPHA = 0.6;

/** The highest opacity of a band of the aurora: a veil, calm. */
export const AURORA_ALPHA = 0.28;

/** How dark the veil of the spotlight gets, at most, around what it lights. */
export const SPOT_DIM = 0.45;

/** How long the sparks of a balloon or a bubble that bursts shine, in milliseconds. */
export const POP_LIFE = 400;

/** The longest step a frame counts, in milliseconds, whatever time went by since the last one. */
export const LONGEST_STEP = 64;

/** The step of the first frame, and of the first one after a pause, in milliseconds. */
export const FIRST_STEP = 16;

const clamp = (value) => Math.max(0, Math.min(1, value));

/** Where a piece is drawn at `time`: its place, swayed sideways when it sways. */
export function place(piece, time) {
    const sway = piece.sway;

    return {
        x: undefined === sway ? piece.x : piece.x + sway.amplitude * Math.sin(sway.phase + time * sway.speed),
        y: piece.y,
    };
}

/**
 * Moves the pieces that have started by `step` milliseconds, at `time` into the animation: pulled and slowed, a piece
 * with a `floor` bouncing on it, one with a `travel` eased from its `from` to its `to`, one with a `sweep` swinging
 * around its base angle.
 */
export function advance(pieces, time, step, { gravity, drag }) {
    const kept = Math.max(0, 1 - drag * step);

    for (const piece of pieces) {
        if (time < piece.delay) {
            continue;
        }

        if (undefined !== piece.travel) {
            const done = clamp((time - piece.delay) / piece.travel);
            piece.y = piece.from + (piece.to - piece.from) * (1 - (1 - done) ** 3);
            continue;
        }

        if (undefined !== piece.sweep) {
            const sweep = piece.sweep;
            piece.angle = sweep.base + sweep.amplitude * Math.sin(sweep.phase + (time - piece.delay) * sweep.speed);
            continue;
        }

        piece.vx *= kept;
        piece.vy = piece.vy * kept + gravity * step;
        piece.x += piece.vx * step;
        piece.y += piece.vy * step;
        piece.angle += piece.spin * step;

        if (undefined !== piece.floor && piece.y > piece.floor) {
            piece.y = piece.floor;
            piece.vy = -Math.abs(piece.vy) * piece.bounce;
            piece.vx *= 0.8;
        }
    }
}

/**
 * The opacity of a piece at `time`: rising over its `fadeIn`, if any, then whole, then fading over the last 30 % of the
 * animation or of its own life.
 */
export function opacity(piece, time, duration) {
    const fade = (left, length) => clamp(left / (length * 0.3));
    const own = Number.isFinite(piece.life) ? fade(piece.life - (time - piece.delay), piece.life) : 1;
    const rise = piece.fadeIn > 0 ? clamp((time - piece.delay) / piece.fadeIn) : 1;

    return time < piece.delay ? 0 : Math.min(own, rise, fade(duration - time, duration));
}

/** A strip of confetti, turned. */
function piece(context, item, at) {
    context.save();
    context.translate(at.x, at.y);
    context.rotate(item.angle);
    context.fillRect(-item.size / 2, -item.size / 4, item.size, item.size / 2);
    context.restore();
}

/** A spark, round. */
function spark(context, item, at) {
    context.beginPath();
    context.arc(at.x, at.y, item.size, 0, Math.PI * 2);
    context.fill();
}

/** A beam of light from its foot, `angle` from the vertical, thin, never a flash. */
function beam(context, item, at, alpha) {
    context.globalAlpha = alpha * BEAM_ALPHA;
    context.strokeStyle = item.color;
    context.lineWidth = item.size;
    context.lineCap = 'round';
    context.save();
    context.translate(at.x, at.y);
    context.rotate(item.angle);
    context.beginPath();
    context.moveTo(0, 0);
    context.lineTo(0, -item.length);
    context.stroke();
    context.restore();
}

/** A balloon and its string. */
function balloon(context, item, at) {
    context.beginPath();
    context.ellipse(at.x, at.y, item.size * 0.8, item.size, 0, 0, Math.PI * 2);
    context.fill();
    context.strokeStyle = item.color;
    context.lineWidth = 1;
    context.beginPath();
    context.moveTo(at.x, at.y + item.size);
    context.lineTo(at.x, at.y + item.size * 2.6);
    context.stroke();
}

/** A shooting star, its head and the trail behind it. */
function star(context, item, at) {
    context.strokeStyle = item.color;
    context.lineWidth = item.size;
    context.lineCap = 'round';
    context.beginPath();
    context.moveTo(at.x - item.vx * item.length, at.y - item.vy * item.length);
    context.lineTo(at.x, at.y);
    context.stroke();
    spark(context, item, at);
}

/** A streamer, a wavy ribbon unrolled behind its head, as long as it has flown. */
function streamer(context, item, at, alpha, time) {
    const speed = Math.hypot(item.vx, item.vy) || 1;
    const back = { x: -item.vx / speed, y: -item.vy / speed };
    const length = Math.min(item.length, (time - item.delay) * 0.3);
    context.strokeStyle = item.color;
    context.lineWidth = item.size;
    context.beginPath();
    context.moveTo(at.x, at.y);

    for (let along = 10; along <= length; along += 10) {
        const wave = Math.sin(along / 12 + item.angle) * 6;
        context.lineTo(at.x + back.x * along - back.y * wave, at.y + back.y * along + back.x * wave);
    }

    context.stroke();
}

/** A coin, spinning: its width follows its angle. */
function coin(context, item, at) {
    context.beginPath();
    context.ellipse(at.x, at.y, item.size * Math.max(0.15, Math.abs(Math.cos(item.angle))), item.size, 0, 0, Math.PI * 2);
    context.fill();
}

/** A bubble: a ring, tinted inside. */
function bubble(context, item, at, alpha) {
    context.strokeStyle = item.color;
    context.lineWidth = 1.5;
    context.beginPath();
    context.arc(at.x, at.y, item.size, 0, Math.PI * 2);
    context.stroke();
    context.globalAlpha = alpha * 0.2;
    context.fill();
}

/** A band of the aurora across the whole width, waving with its phase. */
function veil(context, item, at, alpha, time, width) {
    context.globalAlpha = alpha * AURORA_ALPHA;
    context.beginPath();
    context.moveTo(0, at.y);

    for (let x = 0; x <= width; x += 40) {
        context.lineTo(x, at.y + item.amplitude * Math.sin(x * item.wave + item.angle));
    }

    for (let x = width; x >= 0; x -= 40) {
        context.lineTo(x, at.y + item.size + item.amplitude * Math.sin(x * item.wave + item.angle + 1));
    }

    context.closePath();
    context.fill();
}

/** The spotlight: the page dimmed around a disc of light, ringed in its colour. */
function spot(context, item, at, alpha, time, width, height) {
    context.globalAlpha = alpha * SPOT_DIM;
    context.fillStyle = 'rgb(0 0 0)';
    context.fillRect(0, 0, width, height);
    context.globalAlpha = alpha;
    context.globalCompositeOperation = 'destination-out';
    const light = context.createRadialGradient(at.x, at.y, item.size * 0.7, at.x, at.y, item.size);
    light.addColorStop(0, 'rgb(0 0 0)');
    light.addColorStop(1, 'rgb(0 0 0 / 0)');
    context.fillStyle = light;
    context.beginPath();
    context.arc(at.x, at.y, item.size, 0, Math.PI * 2);
    context.fill();
    context.globalCompositeOperation = 'source-over';
    context.strokeStyle = item.color;
    context.lineWidth = 2;
    context.beginPath();
    context.arc(at.x, at.y, item.size * 0.85, 0, Math.PI * 2);
    context.stroke();
}

/** A trophy: its cup, its handles, its stem and its base. */
function trophy(context, item, at) {
    const size = item.size;
    context.save();
    context.translate(at.x, at.y);
    context.beginPath();
    context.moveTo(-size / 2, -size / 2);
    context.lineTo(size / 2, -size / 2);
    context.quadraticCurveTo(size / 2, size * 0.15, 0, size * 0.2);
    context.quadraticCurveTo(-size / 2, size * 0.15, -size / 2, -size / 2);
    context.fill();
    context.strokeStyle = item.color;
    context.lineWidth = size / 12;
    context.beginPath();
    context.arc(-size / 2, -size / 4, size / 5, Math.PI / 2, Math.PI * 1.5);
    context.stroke();
    context.beginPath();
    context.arc(size / 2, -size / 4, size / 5, -Math.PI / 2, Math.PI / 2);
    context.stroke();
    context.fillRect(-size / 16, size * 0.2, size / 8, size * 0.2);
    context.fillRect(-size / 4, size * 0.4, size / 2, size / 10);
    context.restore();
}

/** How each shape is drawn, with its colour as the fill and its opacity set. */
const SHAPES = { piece, spark, beam, balloon, star, streamer, coin, bubble, veil, spot, trophy };

/** Draws the pieces at `time` on a 2D context, cleared first; a shape it does not know is skipped. */
export function draw(context, pieces, time, duration, width, height) {
    context.clearRect(0, 0, width, height);

    for (const item of pieces) {
        const alpha = opacity(item, time, duration);
        const shape = SHAPES[item.shape];

        if (0 === alpha || undefined === shape) {
            continue;
        }

        context.globalAlpha = alpha;
        context.fillStyle = item.color;
        shape(context, item, place(item, time), alpha, time, width, height);
    }

    context.globalAlpha = 1;
}

/**
 * Bursts the first balloon or bubble under a point, started and still whole, at `time`: it ends there, and the sparks
 * it bursts into are returned, to add to the pieces; none when nothing is there.
 */
export function pop(pieces, x, y, time) {
    for (const item of pieces) {
        if (('balloon' !== item.shape && 'bubble' !== item.shape) || time < item.delay || item.popped) {
            continue;
        }

        const at = place(item, time);
        const across = 'balloon' === item.shape ? item.size * 0.8 : item.size;

        if (((x - at.x) / across) ** 2 + ((y - at.y) / item.size) ** 2 > 1) {
            continue;
        }

        item.popped = true;
        item.life = time - item.delay;

        return Array.from({ length: 8 }, (_, index) => ({
            shape: 'spark',
            x: at.x,
            y: at.y,
            vx: Math.cos((Math.PI * index) / 4) * 0.15,
            vy: Math.sin((Math.PI * index) / 4) * 0.15,
            size: 2,
            angle: 0,
            spin: 0,
            color: item.color,
            delay: time,
            life: POP_LIFE,
        }));
    }

    return [];
}

/** Gives each piece the colour that stands at the same place in `to` as its own in `from`; the others keep theirs. */
export function recolor(pieces, from, to) {
    for (const item of pieces) {
        const index = from.indexOf(item.color);

        if (index >= 0 && undefined !== to[index]) {
            item.color = to[index];
        }
    }
}

/** The frames of the scope: `requestAnimationFrame` where it has one, a timer of about sixty a second otherwise. */
function frames(scope) {
    if ('function' === typeof scope.requestAnimationFrame) {
        return { frame: (callback) => scope.requestAnimationFrame(callback), cancel: (id) => scope.cancelAnimationFrame(id) };
    }

    return { frame: (callback) => setTimeout(() => callback(performance.now()), FIRST_STEP), cancel: (id) => clearTimeout(id) };
}

/**
 * Paints one celebration at a time on a canvas, told what to do by messages: `start` with the canvas, its pieces, its
 * size, its pixel ratio, its duration, its effect, whether the page is hidden and an `id`; `hold` and `resume` while the
 * page is hidden; `pop` at a point; `recolor` from a list of colours to another; `stop`. It posts `{type: 'end', id}`
 * when the animation is over or stopped, after freeing the bitmap of its canvas. `halt` is asked at each frame whether
 * to stop there.
 */
export class Painter {
    constructor(post, { frame, cancel, halt = () => false } = {}) {
        const timing = undefined === frame ? frames(globalThis) : { frame, cancel };
        this.post = post;
        this.frame = timing.frame;
        this.cancel = timing.cancel;
        this.halt = halt;
        this.canvas = null;
        this.request = null;
        this.id = null;
    }

    receive(message) {
        switch (message?.type) {
            case 'start':
                return this.start(message);
            case 'hold':
                return this.hold();
            case 'resume':
                return this.resume();
            case 'pop':
                return this.pop(message);
            case 'recolor':
                return this.recolor(message);
            case 'stop':
                return this.stop();
            default:
                return undefined;
        }
    }

    /** Takes a canvas and its pieces and paints them from the next frame; false when the canvas has no 2D context. */
    start({ id = null, canvas, pieces, width, height, ratio = 1, duration, effect, hidden = false }) {
        this.stop(false);
        const context = canvas.getContext('2d');

        if (null === context) {
            return false;
        }

        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        context.scale(ratio, ratio);
        this.id = id;
        this.canvas = canvas;
        this.context = context;
        this.pieces = pieces;
        this.width = width;
        this.height = height;
        this.duration = duration;
        this.physics = PHYSICS[effect] ?? PHYSICS.confetti;
        this.elapsed = 0;
        this.last = null;

        if (!hidden) {
            this.schedule();
        }

        return true;
    }

    hold() {
        if (null !== this.request) {
            this.cancel(this.request);
            this.request = null;
        }

        this.last = null;
    }

    resume() {
        if (null !== this.canvas && null === this.request) {
            this.schedule();
        }
    }

    pop({ x, y }) {
        if (null !== this.canvas) {
            this.pieces.push(...pop(this.pieces, x, y, this.elapsed));
        }
    }

    recolor({ from, to }) {
        if (null !== this.canvas) {
            recolor(this.pieces, from, to);
        }
    }

    /** Ends the painting now, the bitmap of its canvas freed; posts the end unless `notify` is false. */
    stop(notify = true) {
        this.hold();

        if (null === this.canvas) {
            return;
        }

        this.canvas.width = 0;
        this.canvas.height = 0;
        this.canvas = null;
        this.context = null;
        this.pieces = null;

        if (notify) {
            this.post({ type: 'end', id: this.id });
        }
    }

    schedule() {
        this.request = this.frame((now) => this.tick(now));
    }

    tick(now) {
        this.request = null;
        const step = null === this.last ? FIRST_STEP : Math.min(LONGEST_STEP, Math.max(0, now - this.last));
        this.last = now;
        this.elapsed += step;

        if (this.halt() || this.elapsed >= this.duration) {
            this.stop();

            return;
        }

        advance(this.pieces, this.elapsed, step, this.physics);
        draw(this.context, this.pieces, this.elapsed, this.duration, this.width, this.height);
        this.schedule();
    }
}

/** Makes a worker's scope paint what its messages ask, and post back to the page; returns its painter. */
export function listen(scope) {
    const painter = new Painter((message) => scope.postMessage(message), frames(scope));
    scope.addEventListener('message', (event) => {
        // A canvas without a 2D context paints nothing: the page is told at once, so that it frees its own.
        if (false === painter.receive(event.data)) {
            scope.postMessage({ type: 'end', id: event.data?.id ?? null });
        }
    });

    return painter;
}

/** Whether a scope is that of a worker, dedicated or not, rather than a page or Node. */
export function inWorker(scope) {
    return 'function' === typeof scope.WorkerGlobalScope && scope instanceof scope.WorkerGlobalScope;
}

// In a worker, it listens; imported by the page, it only exports.
if (inWorker(globalThis)) {
    listen(globalThis);
}
