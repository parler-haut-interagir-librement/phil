// <phil-embed>: the element another site pastes. It holds a plain link, which a reader without JavaScript follows;
// with JavaScript, it puts the page in a frame, at the height the frame asks for, and shows it once the frame says it
// is ready — or keeps the link if the frame never does. Messages are read from that frame alone, and only from the
// origin of its address; the theme is sent to that origin alone.
//
// This module runs on other sites, without Stimulus: it is the one place of the bundle written as a plain element.

import { message, parseMessage } from './lib/message.js';

const SANDBOX = 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox';
const TIMEOUT = 10000;

export class PhilEmbed extends HTMLElement {
    static observedAttributes = ['theme'];

    connectedCallback() {
        if (this.frame) {
            return;
        }

        const address = this.address();

        if (!address) {
            this.dataset.state = 'fallback';

            return;
        }

        this.origin = address.origin;
        this.frame = document.createElement('iframe');
        this.frame.src = address.href;
        this.frame.title = this.getAttribute('title') || this.textContent.trim() || address.host;
        this.frame.loading = 'lazy';
        this.frame.referrerPolicy = 'no-referrer';
        this.frame.setAttribute('sandbox', this.getAttribute('sandbox') ?? SANDBOX);
        this.frame.style.cssText = 'display:block;width:100%;border:0;';
        this.frame.style.height = `${this.initialHeight()}px`;
        this.frame.hidden = true;
        this.onMessage = this.receive.bind(this);
        window.addEventListener('message', this.onMessage);
        this.append(this.frame);
        this.dataset.state = 'loading';
        this.timer = window.setTimeout(() => this.fallBack(), this.timeout());
    }

    disconnectedCallback() {
        window.removeEventListener('message', this.onMessage);
        window.clearTimeout(this.timer);
        this.frame?.remove();
        this.frame = null;
    }

    attributeChangedCallback(name, previous, value) {
        if (name === 'theme' && value !== previous && this.dataset.state === 'ready' && value) {
            this.frame?.contentWindow?.postMessage(message('theme', { theme: value }), this.origin);
        }
    }

    receive(event) {
        if (!this.frame || event.source !== this.frame.contentWindow || event.origin !== this.origin) {
            return;
        }

        const received = parseMessage(event.data);

        if (received?.type === 'ready') {
            window.clearTimeout(this.timer);
            this.frame.hidden = false;

            for (const child of this.children) {
                if (child !== this.frame) {
                    child.hidden = true;
                }
            }

            this.dataset.state = 'ready';
            this.dispatchEvent(new CustomEvent('phil-embed:ready', { bubbles: true }));
        } else if (received?.type === 'resize') {
            this.frame.style.height = `${received.height}px`;
            this.dispatchEvent(
                new CustomEvent('phil-embed:resize', { bubbles: true, detail: { height: received.height } }),
            );
        }
    }

    fallBack() {
        window.removeEventListener('message', this.onMessage);
        this.frame?.remove();
        this.frame = null;
        this.dataset.state = 'fallback';
    }

    address() {
        try {
            const url = new URL(this.getAttribute('src') ?? '', document.baseURI);

            if (!['https:', 'http:'].includes(url.protocol)) {
                return null;
            }

            const theme = this.getAttribute('theme');

            if (theme) {
                url.searchParams.set('embed_theme', theme);
            }

            return url;
        } catch {
            return null;
        }
    }

    initialHeight() {
        const height = Number.parseInt(this.getAttribute('height') ?? '', 10);

        return Number.isInteger(height) && height >= 0 && height <= 100000 ? height : 150;
    }

    timeout() {
        const timeout = Number.parseInt(this.getAttribute('timeout') ?? '', 10);

        return Number.isInteger(timeout) && timeout > 0 ? timeout : TIMEOUT;
    }
}

if (!customElements.get('phil-embed')) {
    customElements.define('phil-embed', PhilEmbed);
}
