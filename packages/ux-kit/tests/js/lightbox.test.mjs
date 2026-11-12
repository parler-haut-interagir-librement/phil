import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

import LightboxController, { hasInvokers, hasLightDismiss, isOutside } from '../../lightbox/assets/controllers/lightbox_controller.js';
import { browser, FakeElement, stimulate } from './fake.mjs';

beforeEach(() => {
    browser();
    delete globalThis.HTMLButtonElement;
    delete globalThis.HTMLDialogElement;
});

/** A browser that runs the invokers of HTML and `closedby` itself. */
const nativeHtml = () => {
    globalThis.HTMLButtonElement = class {
        get commandForElement() {
            return null;
        }
    };
    globalThis.HTMLDialogElement = class {
        get closedBy() {
            return 'auto';
        }
    };
};

const lightbox = () => {
    const element = Object.assign(new FakeElement({ rect: { top: 100, bottom: 300, left: 100, right: 300 } }), {
        open: false,
        showModal() {
            this.open = true;
            this.calls.push(['showModal']);
        },
        close() {
            this.open = false;
            this.calls.push(['close']);
        },
    });

    return { controller: stimulate(LightboxController, { targets: { dialog: [element] } }), element };
};

test('lightbox: the features of the browser', () => {
    assert.equal(hasInvokers(), false);
    assert.equal(hasLightDismiss(), false);
    nativeHtml();
    assert.equal(hasInvokers(), true);
    assert.equal(hasLightDismiss(), true);
    assert.equal(isOutside({ clientX: 5, clientY: 15 }, { top: 10, bottom: 20, left: 10, right: 20 }), true);
    assert.equal(isOutside({ clientX: 15, clientY: 15 }, { top: 10, bottom: 20, left: 10, right: 20 }), false);
});

test('lightbox: where the browser runs no invoker, the controller opens and closes the dialog once', () => {
    const { controller, element } = lightbox();
    controller.open();
    controller.open();
    assert.deepEqual(element.calls, [['showModal']]);
    controller.close();
    controller.close();
    assert.deepEqual(element.calls, [['showModal'], ['close']]);
});

test('lightbox: where the browser runs the invokers and closedby, the controller leaves them to it', () => {
    nativeHtml();
    const { controller, element } = lightbox();
    controller.open();
    controller.closeOnClickOutside({ target: element, clientX: 0, clientY: 0 });
    controller.close();
    assert.deepEqual(element.calls, []);
});

test('lightbox: a click on the backdrop closes it, a click on the image does not', () => {
    const { controller, element } = lightbox();
    controller.open();
    controller.closeOnClickOutside({ target: element, clientX: 200, clientY: 200 });
    assert.equal(element.open, true);
    controller.closeOnClickOutside({ target: new FakeElement(), clientX: 0, clientY: 0 });
    assert.equal(element.open, true);
    controller.closeOnClickOutside({ target: element, clientX: 0, clientY: 0 });
    assert.equal(element.open, false);
});
