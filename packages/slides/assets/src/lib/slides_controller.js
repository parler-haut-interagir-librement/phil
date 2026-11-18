/*
 * The base of the two controllers of phil/slides: the position in the deck, its fragments, the keyboard, the live
 * region and the BroadcastChannel that keeps every window of the same deck at the same position, with no server.
 * A window that opens says `hello`; the others answer with where they stand; a move is sent to all, and a window that
 * receives one follows it without sending it again.
 */
import { Controller } from '@hotwired/stimulus';

import { CHANNEL_PREFIX, command, format, go, Position, read } from './deck.js';

export default class extends Controller {
    static targets = ['slide', 'status', 'counter'];

    static values = {
        channel: String,
        fragmentClass: { type: String, default: 'fragment' },
        status: String,
        titledStatus: String,
        counter: String,
    };

    connect() {
        this.sender = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
        const selector = `.${globalThis.CSS?.escape?.(this.fragmentClassValue) ?? this.fragmentClassValue}`;
        this.fragments = this.slideTargets.map((slide) => [...slide.querySelectorAll(selector)]);
        this.position = new Position(this.fragments.map((fragments) => fragments.length));
        this.start();
        this.channel =
            typeof globalThis.BroadcastChannel === 'function'
                ? new globalThis.BroadcastChannel(CHANNEL_PREFIX + this.channelValue)
                : null;

        if (this.channel) {
            this.channel.onmessage = (event) => this.receive(event.data);
            this.channel.postMessage({ type: 'hello', sender: this.sender });
        }

        this.render(false);
    }

    disconnect() {
        this.channel?.close();
        this.channel = null;
    }

    /** Where the deck starts; the first slide. */
    start() {}

    /** Whether a key pressed in the page is the deck's. */
    owns() {
        return true;
    }

    key(event) {
        const name = this.owns() ? command(event) : null;

        if (name === null || !this.run(name)) {
            return;
        }

        event.preventDefault();
    }

    /** Runs a command; whether the deck took it. */
    run(name) {
        if (['next', 'previous', 'first', 'last'].includes(name)) {
            this.move(this.position[name]());

            return true;
        }

        return false;
    }

    next() {
        this.move(this.position.next());
    }

    previous() {
        this.move(this.position.previous());
    }

    move(moved) {
        if (!moved) {
            return;
        }

        this.render(true);
        this.channel?.postMessage(go(this.position, this.sender));
    }

    receive(data) {
        const message = read(data, this.sender);

        if (message?.type === 'hello') {
            this.channel?.postMessage(go(this.position, this.sender));
        } else if (message?.type === 'go' && this.position.go(message.slide, message.fragment)) {
            this.render(true);
        }
    }

    /** Shows the position; `announce` tells assistive technologies through the live region. */
    render(announce) {
        const { slide, fragment } = this.position;

        this.slideTargets.forEach((element, index) => {
            element.classList.toggle('isCurrent', index === slide);
            this.fragments[index].forEach((item, rank) => {
                item.classList.toggle('isPending', index > slide || (index === slide && rank >= fragment));
            });
        });

        const values = { number: slide + 1, count: this.position.count, title: this.titleOf(slide) };

        if (this.hasCounterTarget) {
            this.counterTarget.textContent = format(this.counterValue, values);
        }

        if (announce && this.hasStatusTarget) {
            this.statusTarget.textContent = format(values.title ? this.titledStatusValue : this.statusValue, values);
        }
    }

    titleOf(index) {
        return this.slideTargets[index]?.querySelector('h1, h2, h3, h4, h5, h6')?.textContent?.trim() ?? '';
    }
}
