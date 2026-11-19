/*
 * The Stimulus controller of phil/error-tracking-bundle, on the page's body:
 *
 *     <body data-controller="phil--error-tracking-bundle--capture"
 *           data-phil--error-tracking-bundle--capture-endpoint-value="{{ path('phil_error_tracking_browser') }}">
 *
 * While it is connected, it reports the window's uncaught errors and unhandled promise rejections to the endpoint: at
 * most `limit` reports per page (10 by default), and the same message once. It sets no cookie and stores nothing.
 */
import { Controller } from '@hotwired/stimulus';
import { reportOf, send } from './capture.js';

export default class extends Controller {
    static values = { endpoint: String, token: String, limit: { type: Number, default: 10 } };

    connect() {
        this.sent = new Set();
        this.onError = (event) => this.report(event.error, event.message);
        this.onRejection = (event) => this.report(event.reason, null);
        globalThis.addEventListener('error', this.onError);
        globalThis.addEventListener('unhandledrejection', this.onRejection);
    }

    disconnect() {
        globalThis.removeEventListener('error', this.onError);
        globalThis.removeEventListener('unhandledrejection', this.onRejection);
    }

    report(thrown, fallbackMessage) {
        const report = reportOf(thrown, fallbackMessage, globalThis.location);

        if (report === null || this.sent.size >= this.limitValue || this.sent.has(report.message)) {
            return;
        }

        this.sent.add(report.message);
        send(this.endpointValue, report, this.tokenValue);
    }
}
