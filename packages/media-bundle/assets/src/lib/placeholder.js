/** A tiny image inline in base 64, of a type the library keeps as a preview: the only one painted. */
const PREVIEW = /^data:image\/(?:webp|avif|jpeg|png);base64,[A-Za-z0-9+/]+={0,2}$/;

/** A position in whole percents, as the bundle writes the focal point: `25% 100%`. */
const POSITION = /^(?:100|[1-9]?[0-9])% (?:100|[1-9]?[0-9])%$/;

/** The properties that paint the preview, taken away once the image has loaded. */
export const PROPERTIES = ['background-image', 'background-position', 'background-size', 'background-repeat'];

/** The longest preview the library keeps. */
export const PREVIEW_MAX = 4096;

/** Whether a value is a preview the controller paints. */
export function isPreview(value) {
    return typeof value === 'string' && value.length <= PREVIEW_MAX && PREVIEW.test(value);
}

/** The position of the preview: the one given when it reads as the bundle writes it, the centre otherwise. */
export function position(value) {
    return typeof value === 'string' && POSITION.test(value) ? value : '50% 50%';
}

/**
 * The CSS properties that paint the preview behind the image, covering its box around the position; `null` for a value
 * that is no preview.
 */
export function backgroundOf(preview, at) {
    if (!isPreview(preview)) {
        return null;
    }

    return {
        'background-image': `url("${preview}")`,
        'background-position': position(at),
        'background-size': 'cover',
        'background-repeat': 'no-repeat',
    };
}
