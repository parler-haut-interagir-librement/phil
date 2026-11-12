import assert from 'node:assert/strict';
import { test } from 'node:test';

import ImagePreviewController, { blurred, isPreview } from '../../image/assets/controllers/image_preview_controller.js';
import { FakeElement, stimulate } from './fake.mjs';

const PNG = 'data:image/png;base64,iVBORw0KGgo=';

/** An image, loaded or not, whose style and listeners the test reads. */
const image = ({ complete = false, naturalWidth = 0, width = '800', height = '600' } = {}) => {
    const element = Object.assign(new FakeElement({ tagName: 'IMG', attributes: { width, height } }), { complete, naturalWidth, listeners: new Map() });
    element.style = {
        properties: new Map(),
        setProperty(name, value) {
            this.properties.set(name, value);
        },
        removeProperty(name) {
            this.properties.delete(name);
        },
    };
    element.addEventListener = (type, listener, options) => element.listeners.set(type, [listener, options]);
    element.removeEventListener = (type) => element.listeners.delete(type);

    return element;
};

test('a preview is a tiny raster image inline in base 64', () => {
    assert.equal(isPreview(PNG), true);
    assert.equal(isPreview('data:image/webp;base64,UklGRhoAAABXRUJQ+/8='), true);
    assert.equal(isPreview('data:image/avif;base64,AAAA'), true);
    assert.equal(isPreview('data:image/svg+xml;base64,PHN2Zz4='), false);
    assert.equal(isPreview('data:image/png,abc'), false);
    assert.equal(isPreview('/a-tiny.jpg'), false);
    assert.equal(isPreview('data:image/png;base64,ab")c'), false);
    assert.equal(isPreview(undefined), false);
});

test('the preview is blurred in an SVG at the ratio of the image', () => {
    const background = blurred(PNG, 800, 600);

    assert.ok(background.startsWith(`url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 75' preserveAspectRatio='none'%3E`));
    assert.ok(background.includes("%3CfeGaussianBlur stdDeviation='4'/%3E"));
    assert.ok(background.includes(`filter='url(%23b)' href='${PNG}'/%3E%3C/svg%3E")`));
    assert.ok(!background.slice(5, -2).includes('"'), 'nothing ends the URL of CSS early');
    assert.ok(blurred(PNG, 0, 0).includes("viewBox='0 0 100 100'"), 'square without a size');
    assert.ok(blurred(PNG, 3, 1).includes("viewBox='0 0 100 33.33'"));
});

test('the controller paints the preview through the CSSOM until the image loads', () => {
    const element = image();
    const controller = stimulate(ImagePreviewController, { element, values: { src: PNG } });
    controller.connect();

    assert.equal(element.style.properties.get('background-image'), blurred(PNG, 800, 600));
    assert.deepEqual(element.listeners.get('load')[1], { once: true });

    element.listeners.get('load')[0]();

    assert.equal(element.style.properties.has('background-image'), false);
    assert.equal(element.listeners.size, 0);
});

test('a failed image loses its preview too', () => {
    const element = image();
    const controller = stimulate(ImagePreviewController, { element, values: { src: PNG } });
    controller.connect();
    element.listeners.get('error')[0]();

    assert.equal(element.style.properties.has('background-image'), false);
});

test('an image already loaded or failed, or a value that is no preview, is left alone', () => {
    for (const [element, src] of [
        [image({ complete: true, naturalWidth: 800 }), PNG],
        [image({ complete: true, naturalWidth: 0 }), PNG],
        [image(), '/a-tiny.jpg'],
    ]) {
        stimulate(ImagePreviewController, { element, values: { src } }).connect();

        assert.equal(element.style.properties.size, 0);
        assert.equal(element.listeners.size, 0);
    }
});

test('a controller taken off the page takes its preview away', () => {
    const element = image();
    const controller = stimulate(ImagePreviewController, { element, values: { src: PNG } });
    controller.connect();
    controller.disconnect();

    assert.equal(element.style.properties.size, 0);
    assert.equal(element.listeners.size, 0);
});
