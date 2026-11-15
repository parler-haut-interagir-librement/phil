/*
 * The Stimulus controller of phil/audio-bundle's player, as `@PhilAudio/components/Player.html.twig` renders it: the
 * browser's audio element stays the control, and the controller only adds to it.
 *
 * - It draws the waveform, the amplitudes of `peaks` from 0 to 1, as bars of an SVG path, twice: the bars heard so far
 *   are clipped to the time played. A click on the waveform seeks to that point; the keyboard has the audio element.
 * - A link of a chapter or a cue carries its instant in seconds, `data-…-time-param`: the controller seeks the audio
 *   there instead of following the link to the file, and keeps it playing or paused as it was.
 * - As the audio plays, the current chapter and cue get `aria-current="true"`.
 */
import { Controller } from '@hotwired/stimulus';

const SVG = 'http://www.w3.org/2000/svg';

/**
 * The SVG path of the bars of a waveform, one per amplitude, in a box as wide as the amplitudes and 100 high.
 */
export function barsPath(peaks) {
    return peaks
        .map((amplitude, index) => {
            const height = Math.max(1, Math.min(1, Math.max(0, Number(amplitude) || 0)) * 100);
            const top = (100 - height) / 2;

            return `M${index + 0.1} ${top.toFixed(2)}h0.8v${height.toFixed(2)}h-0.8z`;
        })
        .join('');
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
    static targets = ['audio', 'waveform', 'chapter', 'cue'];
    static values = { peaks: Array };

    connect() {
        this.update = this.update.bind(this);
        this.draw();

        if (this.hasAudioTarget) {
            this.audioTarget.addEventListener('timeupdate', this.update);
        }
    }

    disconnect() {
        if (this.hasAudioTarget) {
            this.audioTarget.removeEventListener('timeupdate', this.update);
        }
    }

    draw() {
        if (!this.hasWaveformTarget || this.peaksValue.length === 0) {
            return;
        }

        const svg = this.waveformTarget;
        const document = svg.ownerDocument;
        const clipId = `${this.element.id || 'phil-audio'}-played`;
        const clip = document.createElementNS(SVG, 'clipPath');
        clip.setAttribute('id', clipId);
        this.played = document.createElementNS(SVG, 'rect');
        this.played.setAttribute('height', '100');
        this.played.setAttribute('width', '0');
        clip.appendChild(this.played);

        const path = barsPath(this.peaksValue);
        const all = document.createElementNS(SVG, 'path');
        all.setAttribute('d', path);
        all.setAttribute('class', 'phil-audio__bars');
        const heard = document.createElementNS(SVG, 'path');
        heard.setAttribute('d', path);
        heard.setAttribute('class', 'phil-audio__bars phil-audio__bars--played');
        heard.setAttribute('clip-path', `url(#${clipId})`);
        svg.replaceChildren(clip, all, heard);
    }

    update() {
        const audio = this.audioTarget;
        const time = audio.currentTime;

        if (this.played && audio.duration > 0) {
            this.played.setAttribute('width', String((time / audio.duration) * this.peaksValue.length));
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

        if (!this.hasAudioTarget || !Number.isFinite(time) || time < 0) {
            return;
        }

        event.preventDefault();
        this.audioTarget.currentTime = time;
        this.update();
    }

    scrub(event) {
        const audio = this.audioTarget;
        const width = this.waveformTarget.getBoundingClientRect().width;

        if (!(audio.duration > 0) || !(width > 0)) {
            return;
        }

        audio.currentTime = Math.min(1, Math.max(0, event.offsetX / width)) * audio.duration;
        this.update();
    }
}
