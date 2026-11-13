import { Controller } from '@hotwired/stimulus';

/*
 * Registers the service worker of phil/pwa-bundle and tells the page what it can do:
 *
 *     <body {{ stimulus_controller('phil--pwa-bundle--pwa', {url: phil_pwa_service_worker_path(), scope: phil_pwa_scope()}) }}>
 *         <button hidden data-phil--pwa-bundle--pwa-target="install" data-action="phil--pwa-bundle--pwa#install">Install</button>
 *         <button hidden data-phil--pwa-bundle--pwa-target="update" data-action="phil--pwa-bundle--pwa#update">Update</button>
 *         <p hidden data-phil--pwa-bundle--pwa-target="offline">You are offline.</p>
 *
 * The install targets show when the browser offers to install the site, the update targets when a new worker waits,
 * the offline targets while the browser is offline, which also sets `data-offline` on the element. Each change is
 * dispatched as an event: registered, unsupported, failed, installable, installed, install-choice, update-available,
 * online, offline. `fullscreen()` enters or leaves the full screen.
 */
export default class extends Controller {
    static values = { url: String, scope: { type: String, default: '/' } };

    static targets = ['install', 'update', 'offline'];

    connect() {
        this.onConnectivity = () => this.connectivity();
        this.onPrompt = (event) => this.installable(event);
        this.onInstalled = () => this.installed();
        window.addEventListener('online', this.onConnectivity);
        window.addEventListener('offline', this.onConnectivity);
        window.addEventListener('beforeinstallprompt', this.onPrompt);
        window.addEventListener('appinstalled', this.onInstalled);
        this.connectivity();

        return this.register();
    }

    disconnect() {
        window.removeEventListener('online', this.onConnectivity);
        window.removeEventListener('offline', this.onConnectivity);
        window.removeEventListener('beforeinstallprompt', this.onPrompt);
        window.removeEventListener('appinstalled', this.onInstalled);
    }

    async register() {
        if (!('serviceWorker' in navigator) || this.urlValue === '') {
            this.dispatch('unsupported');

            return;
        }

        try {
            const registration = await navigator.serviceWorker.register(this.urlValue, { scope: this.scopeValue });
            this.watch(registration);
            this.dispatch('registered', { detail: { scope: registration.scope } });
        } catch (error) {
            this.dispatch('failed', { detail: { error } });
        }
    }

    watch(registration) {
        if (registration.waiting && navigator.serviceWorker.controller) {
            this.waiting(registration.waiting);
        }

        registration.addEventListener('updatefound', () => {
            const worker = registration.installing;

            worker?.addEventListener('statechange', () => {
                if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                    this.waiting(worker);
                }
            });
        });
    }

    waiting(worker) {
        this.worker = worker;
        this.show(this.updateTargets, true);
        this.dispatch('update-available');
    }

    update() {
        if (!this.worker) {
            return;
        }

        navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload(), { once: true });
        this.worker.postMessage({ type: 'skip-waiting' });
    }

    installable(event) {
        event.preventDefault();
        this.deferred = event;
        this.show(this.installTargets, true);
        this.dispatch('installable');
    }

    async install() {
        const prompt = this.deferred;

        if (!prompt) {
            return;
        }

        this.deferred = null;
        this.show(this.installTargets, false);
        await prompt.prompt();
        const choice = await prompt.userChoice;
        this.dispatch('install-choice', { detail: { outcome: choice.outcome } });
    }

    installed() {
        this.deferred = null;
        this.show(this.installTargets, false);
        this.dispatch('installed');
    }

    fullscreen() {
        if (document.fullscreenElement) {
            return document.exitFullscreen();
        }

        return document.documentElement.requestFullscreen?.();
    }

    connectivity() {
        const offline = navigator.onLine === false;
        this.element.toggleAttribute('data-offline', offline);
        this.show(this.offlineTargets, offline);
        this.dispatch(offline ? 'offline' : 'online');
    }

    show(targets, visible) {
        for (const target of targets) {
            target.hidden = !visible;
        }
    }
}
