import { Controller } from '@hotwired/stimulus';

/*
 * Shows a page of the site on a second screen and talks to it:
 *
 *     <div {{ stimulus_controller('phil--pwa-bundle--presentation', {url: path('screen')}) }}>
 *         <button data-action="phil--pwa-bundle--presentation#start">Present</button>
 *         <button data-action="phil--pwa-bundle--presentation#send" data-phil--pwa-bundle--presentation-message-param='{"slide": 2}'>Next</button>
 *         <button data-action="phil--pwa-bundle--presentation#stop">Stop</button>
 *
 * and on the page shown, `{{ stimulus_controller('phil--pwa-bundle--presentation', {receiver: true}) }}`.
 *
 * With the Presentation API, `start()` asks the browser for a display — a second screen, a cast device — and opens the
 * page there; messages go through the presentation connection as JSON. Without it, or when no display is found,
 * `start()` opens the page in a window of its own — once, while it stays open —, which the person moves to the second screen and puts in full screen,
 * and messages go through a BroadcastChannel of the site's own origin named by `channel`. Events: connected (with the
 * mode, "presentation" or "window"), cancelled, message (with its data), disconnected.
 */
export default class extends Controller {
    static values = { url: String, channel: { type: String, default: 'phil-pwa-presentation' }, receiver: Boolean };

    connect() {
        if (!this.receiverValue) {
            return;
        }

        if (navigator.presentation?.receiver) {
            navigator.presentation.receiver.connectionList.then((list) => {
                for (const connection of list.connections) {
                    this.attach(connection);
                }

                list.addEventListener('connectionavailable', (event) => this.attach(event.connection));
            });
        }

        this.listen();
    }

    disconnect() {
        this.channel?.close();
        this.channel = null;
    }

    async start() {
        if (typeof PresentationRequest === 'function') {
            try {
                this.attach(await new PresentationRequest([this.urlValue]).start());
                this.dispatch('connected', { detail: { mode: 'presentation' } });

                return;
            } catch (error) {
                if (error?.name === 'NotAllowedError' || error?.name === 'AbortError') {
                    this.dispatch('cancelled');

                    return;
                }
            }
        }

        if (!this.window || this.window.closed) {
            this.window = window.open(this.urlValue, this.channelValue, 'popup');
        }

        this.listen();
        this.dispatch('connected', { detail: { mode: 'window' } });
    }

    send(event) {
        const message = event?.params?.message ?? event?.detail ?? null;

        if (this.connection) {
            this.connection.send(JSON.stringify(message));
        } else {
            this.channel?.postMessage(message);
        }
    }

    stop() {
        this.connection?.terminate();
        this.connection = null;
        this.window?.close();
        this.window = null;
        this.disconnect();
        this.dispatch('disconnected');
    }

    attach(connection) {
        this.connection = connection;
        connection.addEventListener('message', (event) => this.received(this.parse(event.data)));
        connection.addEventListener('terminate', () => this.dispatch('disconnected'));
    }

    listen() {
        if (!this.channel && typeof BroadcastChannel === 'function') {
            this.channel = new BroadcastChannel(this.channelValue);
            this.channel.addEventListener('message', (event) => this.received(event.data));
        }
    }

    received(data) {
        this.dispatch('message', { detail: { data } });
    }

    parse(data) {
        try {
            return JSON.parse(data);
        } catch {
            return data;
        }
    }
}
