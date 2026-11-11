/*
 * The Stimulus controller of phil/bot-detection-bundle's verdict, on the root element or on a hidden field:
 *
 *     <html data-controller="phil--bot-detection-bundle--verdict">
 *     <input type="hidden" name="verdict" data-controller="phil--bot-detection-bundle--verdict">
 *
 * When it connects, it runs the detectors and writes the verdict — `human` or `robot`, nothing else — as the root
 * element's `data-phil-bot-verdict`, where phil/analytics-bundle's script reads it, and into its field when it is
 * one, for the form to submit. Nothing is stored and nothing is sent by this controller.
 */
import { Controller } from '@hotwired/stimulus';
import { environment, verdict } from './detectors.js';

export default class extends Controller {
    connect() {
        const found = verdict(environment());
        const root = globalThis.document?.documentElement;

        if (root?.dataset) {
            root.dataset.philBotVerdict = found;
        }

        if ('value' in this.element) {
            this.element.value = found;
        }
    }
}
