import { Controller } from '@hotwired/stimulus';

import { message, parseMessage } from './lib/message.js';

/**
 * The page of an embed, inside the frame of another site: it tells that site it is ready, then its height each time
 * it changes, posting to the token's origin alone; it applies a theme that site asks for, among the embed's themes,
 * from that site alone. Outside a frame, it does nothing.
 */
export default class extends Controller {
    static values = { origin: String, themes: Array };

    connect() {
        if (window.parent === window || !this.originValue) {
            return;
        }

        this.height = -1;
        this.onMessage = this.receive.bind(this);
        window.addEventListener('message', this.onMessage);
        this.post(message('ready'));
        this.observer = new ResizeObserver(() => this.measure());
        this.observer.observe(document.documentElement);
        this.measure();
    }

    disconnect() {
        this.observer?.disconnect();

        if (this.onMessage) {
            window.removeEventListener('message', this.onMessage);
        }
    }

    measure() {
        const height = Math.min(100000, Math.ceil(document.documentElement.getBoundingClientRect().height));

        if (height !== this.height) {
            this.height = height;
            this.post(message('resize', { height }));
        }
    }

    receive(event) {
        if (event.source !== window.parent || event.origin !== this.originValue) {
            return;
        }

        const received = parseMessage(event.data);

        if (received?.type === 'theme' && this.themesValue.includes(received.theme)) {
            this.element.dataset.theme = received.theme;
        }
    }

    post(data) {
        window.parent.postMessage(data, this.originValue);
    }
}
