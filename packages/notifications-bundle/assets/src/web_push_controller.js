// Web Push for the current recipient: the button turns this browser's notifications on — the permission asked, the
// browser subscribed with the application's key, the subscription sent to the site — and off again. It stays hidden
// in a browser that cannot receive them, and without JavaScript.
//
//     <twig:PhilNotifications:WebPush />

import { Controller } from '@hotwired/stimulus';

import { contentEncoding, isSupported, keyBytes, subscriptionBody } from './lib/push.js';

export default class extends Controller {
    static targets = ['button', 'status'];

    static values = { config: Object };

    async connect() {
        this.config = this.configValue ?? {};
        this.window = this.element.ownerDocument.defaultView;
        this.registration = null;

        if (!isSupported(this.window) || 'string' !== typeof this.config.worker) {
            return;
        }

        try {
            this.registration = await this.window.navigator.serviceWorker.register(this.config.worker);
            const subscription = await this.registration.pushManager.getSubscription();
            this.pressed(null !== subscription);
            this.element.hidden = false;
        } catch {
            this.registration = null;
        }
    }

    async toggle() {
        if (null === this.registration) {
            return;
        }

        this.buttonTarget.disabled = true;

        try {
            const current = await this.registration.pushManager.getSubscription();

            if (null !== current) {
                await this.post('unsubscribe', JSON.stringify({ endpoint: current.endpoint }));
                await current.unsubscribe();
                this.pressed(false);
                this.say('off');

                return;
            }

            if ('granted' !== (await this.window.Notification.requestPermission())) {
                this.say('denied');

                return;
            }

            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyBytes(this.config.key),
            });

            if (
                !(await this.post(
                    'subscribe',
                    subscriptionBody(subscription, contentEncoding(this.window.PushManager)),
                ))
            ) {
                await subscription.unsubscribe();
                this.say('failed');

                return;
            }

            this.pressed(true);
            this.say('on');
        } catch {
            this.say('failed');
        } finally {
            this.buttonTarget.disabled = false;
        }
    }

    async post(action, body) {
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
        headers[this.config.header ?? 'X-Phil-Notifications-Token'] = this.config.token ?? '';
        const response = await globalThis.fetch(this.config.urls?.[action] ?? '', {
            method: 'POST',
            headers,
            body,
            credentials: 'same-origin',
        });

        return response.ok;
    }

    pressed(on) {
        this.buttonTarget.setAttribute('aria-pressed', String(on));
    }

    say(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = this.config.messages?.[message] ?? '';
        }
    }
}
