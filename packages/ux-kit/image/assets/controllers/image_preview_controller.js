import { Controller } from '@hotwired/stimulus';

/** A tiny image inline in base 64, of a raster type every browser decodes: the only preview painted. */
const PREVIEW = /^data:image\/(?:png|jpeg|gif|webp|avif);base64,[A-Za-z0-9+/]+={0,2}$/;

/** Whether a value is a preview the controller paints. */
export function isPreview(value) {
    return typeof value === 'string' && PREVIEW.test(value);
}

/**
 * The preview blurred, as an inline SVG at the ratio of the image: the tiny image stretched over it under a Gaussian
 * blur, its edges kept opaque, so that the blur does not fade at the borders.
 */
export function blurred(preview, width, height) {
    const ratio = width > 0 && height > 0 ? height / width : 1;
    const box = `0 0 100 ${Math.round(100 * ratio * 100) / 100}`;
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='${box}' preserveAspectRatio='none'><filter id='b' color-interpolation-filters='sRGB'><feGaussianBlur stdDeviation='4'/><feComponentTransfer><feFuncA type='discrete' tableValues='1 1'/></feComponentTransfer></filter><image width='100%' height='100%' preserveAspectRatio='none' filter='url(%23b)' href='${preview}'/></svg>`;

    return `url("data:image/svg+xml,${svg.replaceAll('<', '%3C').replaceAll('>', '%3E')}")`;
}

/*
 * The blurred preview of an image while it loads. The template hands it the tiny image in a value and writes no style:
 * the controller paints it behind the image through the CSSOM, which a strict Content Security Policy allows, where a
 * style attribute would be dropped, and takes it away once the image has loaded or failed. An image already loaded or
 * failed, or a value that is no tiny inline image, is left alone.
 */
export default class extends Controller {
    static values = { src: String };

    connect() {
        this.clear = this.clear.bind(this);

        if (this.element.complete || !isPreview(this.srcValue)) {
            return;
        }

        this.element.style.setProperty('background-image', blurred(this.srcValue, Number(this.element.getAttribute('width')), Number(this.element.getAttribute('height'))));
        this.element.addEventListener('load', this.clear, { once: true });
        this.element.addEventListener('error', this.clear, { once: true });
    }

    disconnect() {
        this.clear();
    }

    clear() {
        this.element.style.removeProperty('background-image');
        this.element.removeEventListener('load', this.clear);
        this.element.removeEventListener('error', this.clear);
    }
}
