/*
 * The Stimulus controller of phil/video-bundle's player, as `@PhilVideo/components/Player.html.twig` renders it.
 *
 * - A linked video: the button of the facade is shown, and pressing it puts the platform's player in the facade's
 *   place, from the address without cookies the server gave, `embed`; nothing of the platform is fetched before.
 * - A hosted video: where the browser plays HLS, the video element plays the stream `hls` instead of its files.
 * - A link of a chapter or a cue carries its instant in seconds, `data-…-time-param`: the controller seeks the video
 *   there instead of following the link to the file, and the current chapter and cue get `aria-current="true"`.
 * - Only when `tracking` is true — the server's word that the viewer agreed — the parts watched, a hundred, are sent to
 *   `endpoint` with `navigator.sendBeacon()` when the page is hidden or the video ends. Otherwise nothing is sent.
 */
import { Controller } from '@hotwired/stimulus';

export const PARTS = 100;

/**
 * The part of the video an instant falls in, from 0 to 99; -1 while the duration is unknown.
 */
export function partOf(time, duration) {
    if (!(duration > 0) || !(time >= 0)) {
        return -1;
    }

    return Math.min(PARTS - 1, Math.floor((time / duration) * PARTS));
}

/**
 * The index of the last element whose `data-start` is at or before the time, and whose `data-end`, when it has one, is
 * after it; -1 when none is.
 */
export function currentIndex(elements, time) {
    let current = -1;

    elements.forEach((element, index) => {
        const start = Number(element.dataset.start);
        const end = element.dataset.end === undefined ? Number.POSITIVE_INFINITY : Number(element.dataset.end);

        if (start <= time && time < end) {
            current = index;
        }
    });

    return current;
}

export default class extends Controller {
    static targets = ['facade', 'play', 'chapter', 'cue'];
    static values = {
        embed: String,
        title: String,
        width: Number,
        height: Number,
        video: String,
        hls: String,
        tracking: Boolean,
        endpoint: String,
    };

    connect() {
        this.parts = new Array(PARTS).fill(0);
        this.dirty = false;
        this.update = this.update.bind(this);
        this.send = this.send.bind(this);
        this.hidden = this.hidden.bind(this);

        if (this.hasPlayTarget) {
            this.playTarget.hidden = false;
        }

        this.media = this.element.querySelector?.('video') ?? null;

        if (!this.media) {
            return;
        }

        if (this.hlsValue && this.media.canPlayType('application/vnd.apple.mpegurl')) {
            this.media.src = this.hlsValue;
        }

        this.media.addEventListener('timeupdate', this.update);
        this.media.addEventListener('ended', this.send);
        globalThis.document?.addEventListener('visibilitychange', this.hidden);
    }

    disconnect() {
        this.send();

        if (this.media) {
            this.media.removeEventListener('timeupdate', this.update);
            this.media.removeEventListener('ended', this.send);
            globalThis.document?.removeEventListener('visibilitychange', this.hidden);
        }
    }

    load() {
        const frame = this.element.ownerDocument.createElement('iframe');
        frame.setAttribute('src', this.embedValue);
        frame.setAttribute('title', this.titleValue);
        frame.setAttribute('width', String(this.widthValue));
        frame.setAttribute('height', String(this.heightValue));
        frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
        frame.setAttribute('allowfullscreen', '');
        frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups');
        this.facadeTarget.replaceChildren(frame);
        frame.focus();
    }

    update() {
        const time = this.media.currentTime;
        const part = partOf(time, this.media.duration);

        if (part >= 0 && this.parts[part] === 0) {
            this.parts[part] = 1;
            this.dirty = true;
        }

        this.mark(this.chapterTargets, time);
        this.mark(this.cueTargets, time);
    }

    mark(elements, time) {
        const current = currentIndex(elements, time);

        elements.forEach((element, index) => {
            if (index === current) {
                element.setAttribute('aria-current', 'true');
            } else {
                element.removeAttribute('aria-current');
            }
        });
    }

    seek(event) {
        const time = Number(event.params?.time);

        if (!this.media || !Number.isFinite(time) || time < 0) {
            return;
        }

        event.preventDefault();
        this.media.currentTime = time;
        this.update();
    }

    hidden() {
        if (globalThis.document?.visibilityState === 'hidden') {
            this.send();
        }
    }

    send() {
        if (!this.trackingValue || !this.endpointValue || !this.dirty) {
            return;
        }

        this.dirty = false;
        globalThis.navigator?.sendBeacon?.(
            this.endpointValue,
            JSON.stringify({ video: this.videoValue, parts: this.parts.join('') }),
        );
    }
}
