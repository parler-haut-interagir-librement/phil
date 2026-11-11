import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { setTimeout as sleep } from 'node:timers/promises';

import DialogController, { hasInvokers, hasLightDismiss, isOutside } from '../../dialog/assets/controllers/dialog_controller.js';
import TooltipController, { GRACE } from '../../tooltip/assets/controllers/tooltip_controller.js';
import { browser, FakeElement, keydown, stimulate } from './fake.mjs';

let listeners;
beforeEach(() => {
    listeners = browser();
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

/** A click on a button, with or without `commandfor`. */
const click = (attributes = {}) => ({ currentTarget: new FakeElement({ attributes }) });

const dialog = ({ open = false } = {}) => {
    const trigger = new FakeElement();
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
    const controller = stimulate(DialogController, {
        targets: { trigger: [trigger], dialog: [element] },
        values: { open },
    });

    return { controller, trigger, element };
};

test('dialog: outside a box', () => {
    const box = { top: 10, bottom: 20, left: 10, right: 20 };
    assert.equal(isOutside({ clientX: 15, clientY: 15 }, box), false);
    assert.equal(isOutside({ clientX: 5, clientY: 15 }, box), true);
    assert.equal(isOutside({ clientX: 25, clientY: 15 }, box), true);
    assert.equal(isOutside({ clientX: 15, clientY: 5 }, box), true);
    assert.equal(isOutside({ clientX: 15, clientY: 25 }, box), true);
});

test('dialog: opened and closed, the trigger says so', () => {
    const { controller, trigger, element } = dialog();
    controller.connect();
    assert.equal(element.open, false);
    controller.open();
    controller.open();
    assert.equal(element.calls.filter(([name]) => name === 'showModal').length, 1);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    controller.close();
    controller.close();
    assert.equal(element.calls.filter(([name]) => name === 'close').length, 1);
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');
});

test('dialog: open from its value; closed natively, the trigger follows', () => {
    const { controller, trigger, element } = dialog({ open: true });
    controller.connect();
    assert.equal(element.open, true);
    element.open = false;
    controller.closed();
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');
});

test('dialog: a click on the backdrop closes it, a click on its padding does not', () => {
    const { controller, element } = dialog();
    controller.open();
    controller.closeOnClickOutside({ target: new FakeElement(), clientX: 0, clientY: 0 });
    assert.equal(element.open, true);
    controller.closeOnClickOutside({ target: element, clientX: 200, clientY: 200 });
    assert.equal(element.open, true);
    controller.closeOnClickOutside({ target: element, clientX: 50, clientY: 50 });
    assert.equal(element.open, false);
});

test('dialog: what the browser does natively is detected', () => {
    assert.equal(hasInvokers(), false);
    assert.equal(hasLightDismiss(), false);
    globalThis.HTMLButtonElement = class {};
    globalThis.HTMLDialogElement = class {};
    assert.equal(hasInvokers(), false);
    assert.equal(hasLightDismiss(), false);
    nativeHtml();
    assert.equal(hasInvokers(), true);
    assert.equal(hasLightDismiss(), true);
});

test('dialog: without invokers, the controller opens and closes from the buttons', () => {
    const { controller, trigger, element } = dialog();
    controller.open(click({ commandfor: 'd', command: 'show-modal' }));
    assert.equal(element.open, true);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    controller.close(click({ commandfor: 'd', command: 'close' }));
    assert.equal(element.open, false);
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');
});

test('dialog: with invokers, the buttons are left to the browser and the trigger still says so', () => {
    nativeHtml();
    const { controller, trigger, element } = dialog();
    controller.open(click({ commandfor: 'd', command: 'show-modal' }));
    assert.deepEqual(element.calls, []);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    element.open = true;
    controller.close(click({ commandfor: 'd', command: 'close' }));
    assert.deepEqual(element.calls, []);
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');
});

test('dialog: with invokers, a button without commandfor and the value still go through the controller', () => {
    nativeHtml();
    const { controller, element } = dialog({ open: true });
    controller.connect();
    assert.equal(element.open, true);
    controller.close(click());
    assert.equal(element.open, false);
    controller.open(click());
    assert.equal(element.open, true);
    controller.close({});
    assert.equal(element.open, false);
});

test('dialog: with closedby, a click on the backdrop is left to the browser', () => {
    nativeHtml();
    const { controller, element } = dialog();
    controller.open();
    controller.closeOnClickOutside({ target: element, clientX: 50, clientY: 50 });
    assert.equal(element.open, true);
});

test('dialog: reopened as a modal when put back in the page', () => {
    const { controller, element } = dialog();
    controller.open();
    controller.disconnect();
    assert.equal(element.open, false);
    controller.connect();
    assert.equal(element.open, true);
    controller.close();
    controller.disconnect();
    controller.connect();
    assert.equal(element.open, false);
});

const tooltip = ({ delay = 0, withTargets = true } = {}) => {
    const trigger = new FakeElement({ rect: { top: 100, bottom: 140, left: 100, right: 200 } });
    const wrapper = new FakeElement({ dataset: { side: 'top', sideOffset: '2' } });
    const content = new FakeElement();
    const arrow = new FakeElement();

    if (!withTargets) {
        document.querySelector = (selector) => ({ w: wrapper, c: content, a: arrow })[selector];
    }

    const targets = withTargets
        ? { trigger: [trigger], wrapper: [wrapper], content: [content], arrow: [arrow] }
        : { trigger: [trigger], wrapper: [], content: [], arrow: [] };
    const controller = stimulate(TooltipController, {
        targets,
        values: { delayDuration: delay, wrapperSelector: 'w', contentSelector: 'c', arrowSelector: 'a' },
    });

    return { controller, trigger, wrapper, content, arrow };
};

test('tooltip: shown at once and placed, its side written on the content and the arrow', () => {
    const { controller, wrapper, content, arrow } = tooltip();
    wrapper.offsetHeight = 500;
    controller.show();
    assert.equal(wrapper.popoverOpen, true);
    assert.equal(wrapper.style.inset, '150px auto auto 100px');
    assert.equal(content.dataset.side, 'bottom');
    assert.equal(arrow.dataset.side, 'bottom');
    assert.equal(wrapper.dataset.side, 'top');
    assert.ok(listeners.document.has('keydown'));
    controller.show();
    assert.equal(wrapper.calls.filter(([name]) => name === 'showPopover').length, 1);
    controller.disconnect();
    assert.equal(wrapper.popoverOpen, false);
});

test('tooltip: the delay, and leaving before it', async () => {
    const { controller, wrapper } = tooltip({ delay: 20 });
    controller.show();
    assert.equal(wrapper.popoverOpen, false);
    await sleep(40);
    assert.equal(wrapper.popoverOpen, true);
    controller.disconnect();

    const other = tooltip({ delay: 20 });
    other.controller.show();
    other.controller.hide();
    await sleep(40);
    assert.equal(other.wrapper.popoverOpen, false);
    other.controller.disconnect();
});

test('tooltip: stays when the pointer reaches it, hides once left', async () => {
    const { controller, wrapper } = tooltip();
    controller.show();
    controller.hide();
    controller.show();
    await sleep(GRACE + 30);
    assert.equal(wrapper.popoverOpen, true);
    controller.hide();
    await sleep(GRACE + 30);
    assert.equal(wrapper.popoverOpen, false);
    assert.equal(listeners.document.size, 0);
});

test('tooltip: Escape dismisses it, another key does not', () => {
    const { controller, wrapper } = tooltip();
    controller.show();
    listeners.document.get('keydown')(keydown('a', null));
    assert.equal(wrapper.popoverOpen, true);
    listeners.document.get('keydown')(keydown('Escape', null));
    assert.equal(wrapper.popoverOpen, false);
});

test('tooltip: found by its selectors when the targets are not in the controller', () => {
    const { controller, wrapper, content } = tooltip({ withTargets: false });
    controller.show();
    assert.equal(wrapper.popoverOpen, true);
    assert.equal(content.dataset.side, 'top');
    controller.disconnect();
});
