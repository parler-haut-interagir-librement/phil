import { Controller } from '@hotwired/stimulus';
import { backgroundOf, PROPERTIES } from './lib/placeholder.js';

/*
 * The tiny preview of a medium behind its image while the image loads. The `Img` and `Picture` components hand it the
 * preview and the focal point in values and write no style: the controller paints them through the CSSOM, which the
 * default Content Security Policy allows where a `style` attribute would be dropped, and takes them away once the image
 * has loaded or failed. An image already loaded, or a value that is no preview, is left alone.
 */
export default class extends Controller {
    static values = { src: String, position: String };

    connect() {
        this.clear = this.clear.bind(this);
        const background = backgroundOf(this.srcValue, this.positionValue);

        if (this.element.complete || background === null) {
            return;
        }

        for (const [property, value] of Object.entries(background)) {
            this.element.style.setProperty(property, value);
        }

        this.element.addEventListener('load', this.clear, { once: true });
        this.element.addEventListener('error', this.clear, { once: true });
    }

    disconnect() {
        this.clear();
    }

    clear() {
        for (const property of PROPERTIES) {
            this.element.style.removeProperty(property);
        }

        this.element.removeEventListener('load', this.clear);
        this.element.removeEventListener('error', this.clear);
    }
}
