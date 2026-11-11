import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import AccordionController, {
    moved as accordionMoved,
} from '../../accordion/assets/controllers/accordion_controller.js';
import TabsController, { moved as tabsMoved } from '../../tabs/assets/controllers/tabs_controller.js';
import { browser, FakeElement, keydown, stimulate } from './fake.mjs';

beforeEach(() => {
    browser();
});

test('tabs: the keys', () => {
    assert.equal(tabsMoved('ArrowRight', 2, 3), 0);
    assert.equal(tabsMoved('ArrowLeft', 0, 3), 2);
    assert.equal(tabsMoved('ArrowRight', 0, 3, { rtl: true }), 2);
    assert.equal(tabsMoved('ArrowLeft', 0, 3, { rtl: true }), 1);
    assert.equal(tabsMoved('ArrowDown', 0, 3), null);
    assert.equal(tabsMoved('ArrowUp', 0, 3), null);
    assert.equal(tabsMoved('ArrowDown', 0, 3, { vertical: true }), 1);
    assert.equal(tabsMoved('ArrowUp', 0, 3, { vertical: true }), 2);
    assert.equal(tabsMoved('ArrowRight', 0, 3, { vertical: true }), null);
    assert.equal(tabsMoved('ArrowLeft', 0, 3, { vertical: true }), null);
    assert.equal(tabsMoved('Home', 2, 3), 0);
    assert.equal(tabsMoved('End', 0, 3), 2);
    assert.equal(tabsMoved('a', 0, 3), null);
});

const tabs = (active = '') => {
    const triggers = ['a', 'b'].map((id) =>
        Object.assign(new FakeElement({ dataset: { tabId: id } }), { parentElement: new FakeElement() }),
    );
    const panels = ['a', 'b'].map((id) => new FakeElement({ dataset: { tabId: id } }));
    const controller = stimulate(TabsController, {
        targets: { trigger: triggers, tab: panels },
        values: { activeTab: active },
    });

    return { controller, triggers, panels };
};

test('tabs: the first opens when none was asked for, one panel shows', () => {
    const { controller, triggers, panels } = tabs();
    controller.connect();
    assert.equal(controller.activeTabValue, 'a');
    assert.equal(triggers[0].getAttribute('aria-selected'), 'true');
    assert.equal(triggers[0].getAttribute('tabindex'), '0');
    assert.equal(triggers[1].getAttribute('tabindex'), '-1');
    assert.equal(panels[0].hidden, false);
    assert.equal(panels[1].hidden, true);
    assert.equal(panels[1].dataset.state, 'inactive');
});

test('tabs: a click opens, the arrows move the selection and the focus', () => {
    const { controller, triggers, panels } = tabs('a');
    controller.connect();
    controller.open({ currentTarget: triggers[1] });
    assert.equal(panels[1].hidden, false);
    assert.equal(panels[1].dataset.active, 'true');

    const event = keydown('ArrowRight', triggers[1]);
    controller.move(event);
    assert.equal(event.prevented, true);
    assert.equal(controller.activeTabValue, 'a');
    assert.equal(FakeElement.focused, triggers[0]);

    const other = keydown('x', triggers[0]);
    controller.move(other);
    assert.equal(other.prevented, false);
});

test('tabs: a vertical list uses the up and down arrows', () => {
    const { controller, triggers } = tabs('a');
    triggers[0].parentElement.setAttribute('aria-orientation', 'vertical');
    controller.connect();
    controller.move(keydown('ArrowDown', triggers[0]));
    assert.equal(controller.activeTabValue, 'b');
});

test('accordion: the keys', () => {
    assert.equal(accordionMoved('ArrowDown', 2, 3), 0);
    assert.equal(accordionMoved('ArrowUp', 0, 3), 2);
    assert.equal(accordionMoved('ArrowRight', 0, 3), null);
    assert.equal(accordionMoved('ArrowLeft', 0, 3), null);
    assert.equal(accordionMoved('ArrowDown', 0, 3, { orientation: 'horizontal' }), null);
    assert.equal(accordionMoved('ArrowUp', 0, 3, { orientation: 'horizontal' }), null);
    assert.equal(accordionMoved('ArrowRight', 0, 3, { orientation: 'horizontal' }), 1);
    assert.equal(accordionMoved('ArrowLeft', 0, 3, { orientation: 'horizontal' }), 2);
    assert.equal(accordionMoved('ArrowRight', 0, 3, { orientation: 'horizontal', rtl: true }), 2);
    assert.equal(accordionMoved('ArrowLeft', 0, 3, { orientation: 'horizontal', rtl: true }), 1);
    assert.equal(accordionMoved('Home', 2, 3), 0);
    assert.equal(accordionMoved('End', 0, 3), 2);
    assert.equal(accordionMoved('z', 0, 3), null);
});

const accordion = () => {
    const details = [
        { inert: false, open: false },
        { inert: true, open: false },
        { inert: false, open: false },
    ];
    const triggers = details.map((item) =>
        Object.assign(new FakeElement({ tagName: 'SUMMARY' }), { parentDetails: item }),
    );
    const controller = stimulate(AccordionController, {
        targets: { trigger: triggers, item: [], content: [] },
        values: { multiple: false, orientation: 'vertical' },
    });

    return { controller, triggers, details };
};

test('accordion: a summary is left to the browser, another control opens its item', () => {
    const { controller, triggers, details } = accordion();
    controller.toggle({ currentTarget: triggers[0] });
    assert.equal(details[0].open, false);

    const button = Object.assign(new FakeElement({ tagName: 'BUTTON' }), { parentDetails: details[0] });
    controller.toggle({ currentTarget: button });
    assert.equal(details[0].open, true);
    controller.toggle({ currentTarget: new FakeElement({ tagName: 'BUTTON' }) });

    controller.toggle({
        currentTarget: Object.assign(new FakeElement({ tagName: 'BUTTON' }), { parentDetails: details[1] }),
    });
    assert.equal(details[1].open, false);
});

test('accordion: the arrows skip a disabled item', () => {
    const { controller, triggers } = accordion();
    const event = keydown('ArrowDown', triggers[0]);
    controller.handleKeydown(event);
    assert.equal(event.prevented, true);
    assert.equal(FakeElement.focused, triggers[2]);

    FakeElement.focused = null;
    const other = keydown('q', triggers[0]);
    controller.handleKeydown(other);
    assert.equal(other.prevented, false);
    assert.equal(FakeElement.focused, null);
});
