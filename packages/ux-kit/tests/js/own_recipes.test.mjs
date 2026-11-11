import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { setTimeout as sleep } from 'node:timers/promises';

import ColorPickerController, { normalize } from '../../color-picker/assets/controllers/color_picker_controller.js';
import BeforeAfterController from '../../before-after/assets/controllers/before_after_controller.js';
import CopyButtonController from '../../copy-button/assets/controllers/copy_button_controller.js';
import DatePickerController, {
    addMonths,
    iso,
    parse,
    weeks,
} from '../../date-picker/assets/controllers/date_picker_controller.js';
import FileInputController, { accepted, sizeText } from '../../file-input/assets/controllers/file_input_controller.js';
import NumberInputController from '../../number-input/assets/controllers/number_input_controller.js';
import RelativeTimeController, { relative } from '../../relative-time/assets/controllers/relative_time_controller.js';
import TagController from '../../tag/assets/controllers/tag_controller.js';
import { sideClasses } from '../../tooltip/assets/controllers/tooltip_controller.js';
import TreeController from '../../tree/assets/controllers/tree_controller.js';
import { browser, FakeElement, stimulate } from './fake.mjs';

let listeners;
beforeEach(() => {
    listeners = browser();
});

const element = (properties = {}) => Object.assign(new FakeElement(), properties);

test('tooltip: the class of each side, mirrored right to left', () => {
    assert.deepEqual(sideClasses('top', false), { isTop: true, isBottom: false, isStart: false, isEnd: false });
    assert.deepEqual(sideClasses('bottom', true), { isTop: false, isBottom: true, isStart: false, isEnd: false });
    assert.equal(sideClasses('left', false).isStart, true);
    assert.equal(sideClasses('left', true).isEnd, true);
    assert.equal(sideClasses('right', false).isEnd, true);
    assert.equal(sideClasses('right', true).isStart, true);
});

const copyButton = (values = {}) => {
    const trigger = element({ hidden: true });
    const status = element();
    const controller = stimulate(CopyButtonController, {
        targets: { trigger: [trigger], status: [status] },
        values: { text: 'hello', source: '', label: 'Copy', copiedLabel: 'Done', failedLabel: 'No', duration: 5, ...values },
    });

    return { controller, trigger, status };
};

const clipboard = (writeText) => {
    Object.defineProperty(globalThis, 'navigator', { value: { clipboard: { writeText } }, configurable: true });
};

test('copy button: shown only with a clipboard', () => {
    Object.defineProperty(globalThis, 'navigator', { value: {}, configurable: true });
    const absent = copyButton();
    absent.controller.connect();
    assert.equal(absent.trigger.hidden, true);
    clipboard(async () => {});
    const present = copyButton();
    present.controller.connect();
    assert.equal(present.trigger.hidden, false);
});

test('copy button: says it copied, then goes back to its label', async () => {
    const written = [];
    clipboard(async (text) => written.push(text));
    const { controller, trigger, status } = copyButton();
    await controller.copy();
    assert.deepEqual(written, ['hello']);
    assert.equal(trigger.textContent, 'Done');
    assert.equal(status.textContent, 'Done');
    assert.equal(controller.dispatched[0][0], 'copied');
    await sleep(15);
    assert.equal(trigger.textContent, 'Copy');
    assert.equal(status.textContent, '');
    controller.disconnect();
});

test('copy button: from another element, by its value or its text; a refusal is said', async () => {
    const written = [];
    clipboard(async (text) => written.push(text));
    const source = copyButton({ source: '#a' });
    document.querySelector = () => ({ value: 'field' });
    await source.controller.copy();
    document.querySelector = () => ({ textContent: 'text' });
    await source.controller.copy();
    document.querySelector = () => null;
    await source.controller.copy();
    assert.deepEqual(written, ['field', 'text', '']);
    source.controller.disconnect();

    clipboard(async () => {
        throw new Error('denied');
    });
    const { controller, status } = copyButton();
    await controller.copy();
    assert.equal(status.textContent, 'No');
    assert.equal(controller.dispatched.length, 0);
    controller.disconnect();
});

const tree = (links = []) => {
    const item = (value, attributes = {}, parent = null) => {
        const created = new FakeElement({ attributes, dataset: { value } });
        created.parentElement = { closest: () => (parent?.getAttribute('aria-expanded') === 'false' ? parent : parent) };
        created.parentItem = parent;
        created.textContent = value;
        created.closest = () => created;

        return created;
    };
    const root = item('Docs', { 'aria-expanded': 'false', 'aria-selected': 'false' });
    const child = item('Report', { 'aria-selected': 'false' }, root);
    const photos = item('Photos', { 'aria-selected': 'true' });

    for (const candidate of [root, child, photos]) {
        candidate.parentElement = {
            closest: (selector) => {
                if (selector.includes('aria-expanded')) {
                    return candidate.parentItem?.getAttribute('aria-expanded') === 'false' ? candidate.parentItem : null;
                }

                return candidate.parentItem ?? null;
            },
        };
    }

    const toggle = new FakeElement();
    toggle.closest = () => root;
    const controller = stimulate(TreeController, { targets: { item: [root, child, photos], toggle: [toggle], link: links } });

    return { controller, root, child, photos, toggle };
};

const key = (target, name, extra = {}) => ({
    key: name,
    target,
    preventDefault() {
        this.prevented = true;
    },
    stopPropagation() {},
    ...extra,
});

test('tree: one stop of the Tab key, on the item chosen', () => {
    const { controller, root, photos } = tree();
    controller.connect();
    assert.equal(photos.tabIndex, 0);
    assert.equal(root.tabIndex, -1);
    controller.disconnect();
});

test('tree: the arrows skip what is folded, open and leave a branch', () => {
    const { controller, root, child, photos } = tree();
    controller.navigate(key(root, 'ArrowDown'));
    assert.equal(FakeElement.focused, photos);
    controller.navigate(key(photos, 'ArrowUp'));
    assert.equal(FakeElement.focused, root);
    controller.navigate(key(root, 'ArrowRight'));
    assert.equal(root.getAttribute('aria-expanded'), 'true');
    controller.navigate(key(root, 'ArrowRight'));
    assert.equal(FakeElement.focused, child);
    controller.navigate(key(child, 'ArrowLeft'));
    assert.equal(FakeElement.focused, root);
    controller.navigate(key(root, 'ArrowLeft'));
    assert.equal(root.getAttribute('aria-expanded'), 'false');
    controller.navigate(key(photos, 'Home'));
    assert.equal(FakeElement.focused, root);
    controller.navigate(key(root, 'End'));
    assert.equal(FakeElement.focused, photos);
});

test('tree: right to left, the arrows are mirrored', () => {
    browser({ direction: 'rtl' });
    const { controller, root } = tree();
    controller.navigate(key(root, 'ArrowLeft'));
    assert.equal(root.getAttribute('aria-expanded'), 'true');
    controller.navigate(key(root, 'ArrowRight'));
    assert.equal(root.getAttribute('aria-expanded'), 'false');
});

test('tree: the Space bar chooses and folds; a modifier and an unknown key are left alone', () => {
    const { controller, root, photos } = tree();
    controller.navigate(key(root, ' '));
    assert.equal(root.getAttribute('aria-selected'), 'true');
    assert.equal(photos.getAttribute('aria-selected'), 'false');
    assert.equal(root.getAttribute('aria-expanded'), 'true');
    assert.deepEqual(controller.dispatched[0][1], { detail: { value: 'Docs' } });

    const modified = key(root, 'ArrowDown', { ctrlKey: true });
    controller.navigate(modified);
    assert.equal(modified.prevented, undefined);
    const unknown = key(root, 'F2');
    controller.navigate(unknown);
    assert.equal(unknown.prevented, undefined);
    controller.navigate(key({ closest: () => null }, 'ArrowDown'));
});

test('tree: Enter follows the link of the item', () => {
    const link = new FakeElement();
    const { controller, photos } = tree([link]);
    link.closest = () => photos;
    controller.navigate(key(photos, 'Enter'));
    assert.deepEqual(link.calls, [['click']]);
});

test('tree: first letters, wrapping around and forgotten', async () => {
    const { controller, root, photos } = tree();
    controller.navigate(key(root, 'P'));
    assert.equal(FakeElement.focused, photos);
    controller.navigate(key(photos, 'h'));
    assert.equal(FakeElement.focused, photos);
    controller.disconnect();
    await sleep(1);
});

test('tree: a click on the row chooses and folds, on the toggle folds alone', () => {
    const { controller, root, toggle } = tree();
    controller.activate({ target: toggle });
    assert.equal(root.getAttribute('aria-expanded'), 'true');
    assert.equal(controller.dispatched.length, 0);
    controller.activate({ target: root });
    assert.equal(root.getAttribute('aria-expanded'), 'false');
    assert.equal(controller.dispatched.length, 1);
    controller.activate({ target: { closest: () => null } });
    assert.equal(controller.dispatched.length, 1);
});

const numberInput = ({ value = 2, min = '1', max = '3' } = {}) => {
    const input = element({
        valueAsNumber: value,
        min,
        max,
        readOnly: false,
        stepUp() {
            this.valueAsNumber += 1;
        },
        stepDown() {
            this.valueAsNumber -= 1;
        },
    });
    const decrement = element({ hidden: true });
    const increment = element({ hidden: true });
    const controller = stimulate(NumberInputController, {
        targets: { input: [input], decrement: [decrement], increment: [increment] },
    });

    return { controller, input, decrement, increment };
};

test('number input: the buttons show, step, tell the page and stop at the limits', () => {
    const { controller, input, decrement, increment } = numberInput();
    controller.connect();
    assert.equal(decrement.hidden, false);
    assert.equal(increment.disabled, false);
    controller.increment();
    assert.equal(input.valueAsNumber, 3);
    assert.equal(increment.disabled, true);
    assert.deepEqual(input.calls, [
        ['dispatchEvent', 'input'],
        ['dispatchEvent', 'change'],
    ]);
    controller.decrement();
    controller.decrement();
    assert.equal(input.valueAsNumber, 1);
    assert.equal(decrement.disabled, true);
});

test('number input: without limits, empty or locked', () => {
    const free = numberInput({ value: Number.NaN, min: '', max: '' });
    free.controller.refresh();
    assert.equal(free.decrement.disabled, false);
    assert.equal(free.increment.disabled, false);
    const locked = numberInput();
    locked.input.disabled = true;
    locked.controller.refresh();
    assert.equal(locked.decrement.disabled, true);
    assert.equal(locked.increment.disabled, true);
});

test('file input: the types the field takes', () => {
    const png = { name: 'A.PNG', type: 'image/png' };
    assert.equal(accepted(png, ''), true);
    assert.equal(accepted(png, '.png'), true);
    assert.equal(accepted(png, ' .gif , image/*'), true);
    assert.equal(accepted(png, 'image/png'), true);
    assert.equal(accepted(png, '.gif,text/plain,text/*'), false);
});

test('file input: a size in the unit that suits it', () => {
    assert.equal(sizeText(0, 'en'), '0 byte');
    assert.equal(sizeText(1536, 'en'), '1.5 kB');
    assert.equal(sizeText(5 * 1024 ** 2, 'en'), '5 MB');
    assert.equal(sizeText(3 * 1024 ** 4, 'en'), '3,072 GB');
});

const fileInput = ({ accept = '', multiple = true, maxSize = 0, files = [] } = {}) => {
    globalThis.DataTransfer = class {
        constructor() {
            this.added = [];
            this.items = { add: (file) => this.added.push(file) };
        }

        get files() {
            return this.added;
        }
    };
    document.documentElement = { lang: 'en' };
    document.createElement = () => new FakeElement();

    const input = element({ accept, multiple, files, value: 'x' });
    const zone = new FakeElement();
    const error = new FakeElement();
    const clear = element({ hidden: true });
    const list = element({
        replaceChildren(...children) {
            this.children = children;
        },
    });
    const controller = stimulate(FileInputController, {
        targets: { zone: [zone], input: [input], list: [list], error: [error], clear: [clear] },
        values: { maxSize, rejectedLabel: 'Left: {count}' },
    });
    controller.itemClasses = ['row'];
    controller.draggingClasses = ['drag'];

    return { controller, input, zone, error, clear, list };
};

test('file input: what it holds is listed as text', () => {
    const file = { name: '<b>.png', type: 'image/png', size: 2048 };
    const { controller, error, clear, list } = fileInput({ files: [file] });
    controller.list();
    assert.equal(list.children[0].textContent, '<b>.png (2 kB)');
    assert.equal(list.children[0].classes.has('row'), true);
    assert.equal(clear.hidden, false);
    assert.equal(error.textContent, '');
});

test('file input: the sieve of the types, the size and the number', () => {
    const small = { name: 'a.png', type: 'image/png', size: 1 };
    const big = { name: 'b.png', type: 'image/png', size: 100 };
    const text = { name: 'c.txt', type: 'text/plain', size: 1 };
    const sieved = fileInput({ accept: '.png', maxSize: 10, files: [small, big, text] });
    sieved.controller.list();
    assert.deepEqual(sieved.input.files, [small]);
    assert.equal(sieved.error.textContent, 'Left: 2');

    const single = fileInput({ multiple: false, files: [small, big] });
    single.controller.list();
    assert.deepEqual(single.input.files, [small]);
    assert.equal(single.error.textContent, 'Left: 1');
});

test('file input: dragged over, left and dropped', () => {
    const file = { name: 'a.png', type: 'image/png', size: 1 };
    const { controller, input, zone } = fileInput();
    let prevented = 0;
    const event = { preventDefault: () => prevented++, dataTransfer: { files: [file] } };
    controller.over(event);
    assert.equal(zone.classes.has('drag'), true);
    controller.leave();
    assert.equal(zone.classes.has('drag'), false);
    controller.over(event);
    controller.drop(event);
    assert.equal(prevented, 3);
    assert.equal(zone.classes.has('drag'), false);
    assert.deepEqual(input.files, [file]);
    assert.deepEqual(input.calls, [['dispatchEvent', 'change']]);
});

test('file input: a disabled field takes nothing', () => {
    const { controller, input, zone } = fileInput();
    input.disabled = true;
    const event = { preventDefault() {}, dataTransfer: { files: [{}] } };
    controller.over(event);
    controller.drop(event);
    assert.equal(zone.classes.has('drag'), false);
    assert.deepEqual(input.files, []);
});

test('file input: emptied, it says so and keeps the focus', () => {
    const { controller, input, clear, error, list } = fileInput();
    list.children = [new FakeElement()];
    error.textContent = 'x';
    controller.clear();
    assert.equal(input.value, '');
    assert.equal(error.textContent, '');
    assert.deepEqual(list.children, []);
    assert.equal(clear.hidden, true);
    assert.equal(FakeElement.focused, input);
});

const tag = ({ cancelled = false, next = null, previous = null, parent = element() } = {}) => {
    const remove = element({ hidden: true });
    const root = element({
        parentElement: parent,
        nextElementSibling: next,
        previousElementSibling: previous,
        remove() {
            this.removed = true;
        },
    });
    const controller = stimulate(TagController, { element: root, targets: { remove: [remove] }, values: { name: 'One' } });
    controller.identifier = 'tag';
    controller.dispatch = (name, options) => {
        controller.dispatched.push([name, options]);

        return { defaultPrevented: cancelled };
    };

    return { controller, remove, root, parent };
};

const sibling = () => {
    const button = new FakeElement();

    return { button, tag: element({ querySelector: () => button }) };
};

test('tag: the button shows; the focus goes to the next, or the previous tag', () => {
    const next = sibling();
    const previous = sibling();
    const first = tag({ next: next.tag, previous: previous.tag });
    first.controller.connect();
    assert.equal(first.remove.hidden, false);
    first.controller.remove();
    assert.equal(first.root.removed, true);
    assert.equal(FakeElement.focused, next.button);
    assert.deepEqual(first.controller.dispatched[0][1], { cancelable: true, detail: { name: 'One' } });

    tag({ previous: previous.tag }).controller.remove();
    assert.equal(FakeElement.focused, previous.button);
});

test('tag: the last one gives the focus to the list; cancelled, it stays', () => {
    const last = tag();
    last.controller.remove();
    assert.equal(last.parent.tabIndex, -1);
    assert.equal(FakeElement.focused, last.parent);

    const orphan = tag({ parent: null });
    orphan.controller.remove();
    assert.equal(orphan.root.removed, true);

    const cancelled = tag({ cancelled: true });
    cancelled.controller.remove();
    assert.equal(cancelled.root.removed, undefined);
});

test('relative time: the unit, the number and how long it stays right', () => {
    const now = Date.UTC(2026, 0, 1);
    assert.deepEqual(relative(now - 5000, now), { value: -5, unit: 'second', next: 1000 });
    assert.deepEqual(relative(now + 90000, now), { value: 1, unit: 'minute', next: 30000 });
    assert.deepEqual(relative(now, now), { value: 0, unit: 'second', next: 1000 });
    assert.equal(relative(now - 2 * 3600000, now).unit, 'hour');
    assert.equal(relative(now - 86400000, now).unit, 'day');
    assert.equal(relative(now - 8 * 86400000, now).unit, 'week');
    assert.equal(relative(now - 31 * 86400000, now).unit, 'month');
    assert.deepEqual(relative(now - 400 * 86400000, now).value, -1);
    assert.equal(relative(now - 400 * 86400000, now).next, 3600000);
});

const moment = (datetime, values = {}) => {
    document.documentElement = { lang: 'en' };

    return stimulate(RelativeTimeController, {
        element: new FakeElement({ attributes: { datetime } }),
        values: { locale: '', length: 'long', limit: 0, ...values },
    });
};

test('relative time: written in the language of the page, then in its own', () => {
    const controller = moment(new Date(Date.now() - 3 * 86400000).toISOString());
    controller.connect();
    assert.equal(controller.element.textContent, '3 days ago');
    assert.equal(listeners.document.has('visibilitychange'), true);
    controller.disconnect();
    assert.equal(listeners.document.has('visibilitychange'), false);

    const french = moment(new Date(Date.now() + 2 * 86400000 + 3600000).toISOString(), { locale: 'fr' });
    french.connect();
    assert.equal(french.element.textContent, 'après-demain');
    french.disconnect();
});

test('relative time: past the limit or unreadable, the date stays; written again when the page comes back', () => {
    const old = moment(new Date(Date.now() - 86400000).toISOString(), { limit: 60 });
    old.element.textContent = 'date';
    old.connect();
    assert.equal(old.element.textContent, 'date');
    old.disconnect();

    const unreadable = moment('never');
    unreadable.element.textContent = 'date';
    unreadable.connect();
    assert.equal(unreadable.element.textContent, 'date');
    unreadable.disconnect();

    const within = moment(new Date(Date.now() - 2000).toISOString(), { limit: 60 });
    within.connect();
    within.element.textContent = '';
    listeners.document.get('visibilitychange')();
    assert.notEqual(within.element.textContent, '');
    within.disconnect();
});

test('before-after: the cut follows the slider', () => {
    const range = element({ value: '30' });
    const controller = stimulate(BeforeAfterController, { targets: { range: [range], before: [] } });
    controller.connect();
    assert.equal(controller.element.style['--phil-before-after-position'], '30%');
    range.value = '80';
    controller.move();
    assert.equal(controller.element.style['--phil-before-after-position'], '80%');
});

test('colour picker: what is a colour', () => {
    assert.equal(normalize('#ABCDEF'), '#abcdef');
    assert.equal(normalize(' abc '), '#aabbcc');
    assert.equal(normalize('#abcd'), null);
    assert.equal(normalize('red;'), null);
});

const colorPicker = (colors = ['#112233', 'nope']) => {
    const well = element({ value: '#112233' });
    const text = element({ value: '#112233', hidden: true });
    const swatches = colors.map(
        (color) => new FakeElement({ attributes: { 'data-color-picker-color-param': color } }),
    );
    swatches.forEach((swatch) => {
        swatch.hidden = true;
    });
    well.dispatchEvent = (event) => {
        well.calls.push(event.type);

        if (event.type === 'input') {
            controller.pick();
        }
    };
    const controller = stimulate(ColorPickerController, { targets: { well: [well], text: [text], swatch: swatches } });
    controller.identifier = 'color-picker';

    return { controller, well, text, swatches };
};

test('colour picker: the text and the swatches show, each painted', () => {
    const { controller, text, swatches } = colorPicker();
    controller.connect();
    assert.equal(text.hidden, false);
    assert.equal(swatches[0].hidden, false);
    assert.equal(swatches[0].style.backgroundColor, '#112233');
    assert.equal(swatches[1].style.backgroundColor, 'transparent');
    assert.equal(swatches[0].getAttribute('aria-pressed'), 'true');
    assert.equal(swatches[1].getAttribute('aria-pressed'), 'false');
});

test('colour picker: a swatch sets the well, which tells the page and the text', () => {
    const { controller, well, text, swatches } = colorPicker(['#445566', '#112233']);
    controller.choose({ params: { color: '#445566' } });
    assert.equal(well.value, '#445566');
    assert.deepEqual(well.calls, ['input', 'change']);
    assert.equal(text.value, '#445566');
    assert.equal(swatches[0].getAttribute('aria-pressed'), 'true');
    controller.choose({ params: { color: 'nope' } });
    assert.equal(well.value, '#445566');
});

test('colour picker: what is typed is refused or taken, in full', () => {
    const { controller, well, text } = colorPicker();
    text.value = 'nope';
    controller.type();
    assert.equal(text.getAttribute('aria-invalid'), 'true');
    assert.equal(well.value, '#112233');
    text.value = 'abc';
    controller.type();
    assert.equal(well.value, '#aabbcc');
    assert.equal(text.value, '#aabbcc');
    assert.equal(text.getAttribute('aria-invalid'), null);
});

test('date picker: a date and its time', () => {
    assert.equal(iso(Date.UTC(2026, 2, 15)), '2026-03-15');
    assert.equal(parse('2026-03-15'), Date.UTC(2026, 2, 15));
    assert.equal(parse('15/03/2026'), null);
    assert.equal(parse('2026-13-45'), null);
    assert.equal(parse(null), null);
});

test('date picker: six weeks from the first day asked for', () => {
    const monday = weeks(Date.UTC(2026, 2, 15), 1);
    assert.equal(monday.length, 6);
    assert.equal(iso(monday[0][0]), '2026-02-23');
    assert.equal(iso(monday[0][6]), '2026-03-01');
    assert.equal(iso(weeks(Date.UTC(2026, 2, 15), 0)[0][0]), '2026-03-01');
    assert.equal(iso(weeks(Date.UTC(2026, 3, 1), 3)[0][0]), '2026-04-01');
});

test('date picker: months away, kept within the month', () => {
    assert.equal(iso(addMonths(Date.UTC(2026, 0, 31), 1)), '2026-02-28');
    assert.equal(iso(addMonths(Date.UTC(2026, 2, 31), -1)), '2026-02-28');
    assert.equal(iso(addMonths(Date.UTC(2026, 11, 15), 1)), '2027-01-15');
    assert.equal(iso(addMonths(Date.UTC(2024, 1, 29), 12)), '2025-02-28');
});

const datePicker = ({ value = '2026-03-15', min = '', max = '', firstDay = 1 } = {}) => {
    const days = new Map();
    const made = (tagName) => {
        const created = new FakeElement({ tagName: tagName.toUpperCase() });
        created.append = (...children) => created.children.push(...children);

        return created;
    };
    document.documentElement = { lang: 'en', clientWidth: 1000, clientHeight: 800 };
    document.createElement = made;
    globalThis.HTMLElement = class {};
    globalThis.HTMLElement.prototype.popover = null;

    const replaceable = () => {
        const created = new FakeElement();
        created.replaceChildren = (...children) => {
            created.children = children;
        };

        return created;
    };
    const weeksTarget = replaceable();
    const collect = () => {
        days.clear();

        for (const row of weeksTarget.children) {
            for (const cell of row.children) {
                days.set(cell.children[0].dataset.date, cell.children[0]);
            }
        }
    };
    weeksTarget.querySelector = (selector) => {
        collect();

        return days.get(/"(.+)"/.exec(selector)[1]) ?? null;
    };

    const input = element({ value, min, max });
    const trigger = new FakeElement({ rect: { top: 100, bottom: 140, left: 800, right: 840 } });
    const panel = new FakeElement();
    const heading = new FakeElement();
    const weekdays = replaceable();
    const controller = stimulate(DatePickerController, {
        targets: {
            input: [input],
            trigger: [trigger],
            panel: [panel],
            heading: [heading],
            weekdays: [weekdays],
            weeks: [weeksTarget],
        },
        values: { locale: 'en', firstDay },
    });
    controller.dayClasses = ['day'];
    controller.weekdayClasses = ['weekday'];
    const open = () => controller.toggled({ newState: 'open' });
    const dayEvent = (name, extra = {}) => ({
        key: name,
        target: { closest: () => FakeElement.focused },
        preventDefault() {
            this.prevented = true;
        },
        ...extra,
    });
    const focused = () => FakeElement.focused.dataset.date;

    return { controller, input, trigger, panel, heading, weekdays, days: collect, day: days, open, dayEvent, focused };
};

test('date picker: the button shows where the popover exists', () => {
    const { controller, trigger } = datePicker();
    trigger.hidden = true;
    controller.connect();
    assert.equal(trigger.hidden, false);
});

test('date picker: opened on its date, written in its language and placed', () => {
    const { controller, trigger, panel, heading, weekdays, focused, days, day } = datePicker();
    controller.toggled({ newState: 'open' });
    days();
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    assert.equal(heading.textContent, 'March 2026');
    assert.equal(focused(), '2026-03-15');
    assert.equal(weekdays.children[0].textContent, 'Mon');
    assert.equal(weekdays.children[0].abbr, 'Monday');
    assert.equal(weekdays.children[0].scope, 'col');
    assert.equal(panel.style.inset, '146px auto auto 740px');
    assert.equal(day.get('2026-03-15').tabIndex, 0);
    assert.equal(day.get('2026-03-16').tabIndex, -1);
    assert.equal(day.get('2026-03-15').getAttribute('aria-pressed'), 'true');
    assert.equal(day.get('2026-03-15').getAttribute('aria-label'), 'Sunday, March 15, 2026');
    assert.equal(day.get('2026-02-23').dataset.outside, 'true');
    assert.equal(day.get('2026-03-01').dataset.outside, undefined);
    assert.equal(day.get('2026-03-01').classes.has('day'), true);
    assert.equal(listeners.window.has('scroll'), true);

    controller.toggled({ newState: 'closed' });
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');
    assert.equal(listeners.window.has('scroll'), false);

    controller.toggled({ newState: 'open' });
    listeners.window.get('resize')();
    controller.disconnect();
    assert.equal(listeners.window.has('resize'), false);
});

test('date picker: an empty field opens on today, marked', () => {
    const { controller, days, day } = datePicker({ value: '' });
    controller.toggled({ newState: 'open' });
    days();
    const now = new Date();
    const today = iso(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
    assert.equal(day.get(today).getAttribute('aria-current'), 'date');
    assert.equal(day.get(today).tabIndex, 0);
    assert.equal(day.get(today).getAttribute('aria-pressed'), 'false');
});

test('date picker: the keys of the grid', () => {
    const { controller, open, dayEvent, focused, heading } = datePicker();
    open();
    const press = (name, extra) => {
        const event = dayEvent(name, extra);
        controller.navigate(event);

        return event;
    };
    assert.equal(press('ArrowRight').prevented, true);
    assert.equal(focused(), '2026-03-16');
    press('ArrowLeft');
    assert.equal(focused(), '2026-03-15');
    press('ArrowDown');
    assert.equal(focused(), '2026-03-22');
    press('ArrowUp');
    assert.equal(focused(), '2026-03-15');
    press('Home');
    assert.equal(focused(), '2026-03-09');
    press('End');
    assert.equal(focused(), '2026-03-15');
    press('PageDown');
    assert.equal(focused(), '2026-04-15');
    assert.equal(heading.textContent, 'April 2026');
    press('PageUp');
    assert.equal(focused(), '2026-03-15');
    press('PageDown', { shiftKey: true });
    assert.equal(focused(), '2027-03-15');
    press('PageUp', { shiftKey: true });
    assert.equal(focused(), '2026-03-15');
    assert.equal(press('Tab').prevented, undefined);
    assert.equal(controller.navigate({ target: { closest: () => null } }), undefined);
});

test('date picker: right to left, the arrows are mirrored', () => {
    browser({ direction: 'rtl' });
    const { controller, open, dayEvent, focused } = datePicker();
    open();
    controller.navigate(dayEvent('ArrowLeft'));
    assert.equal(focused(), '2026-03-16');
    controller.navigate(dayEvent('ArrowRight'));
    assert.equal(focused(), '2026-03-15');
});

test('date picker: the limits hold the keys, and disable the days', () => {
    const { controller, open, dayEvent, focused, days, day } = datePicker({ min: '2026-03-10', max: '2026-03-20' });
    open();
    days();
    assert.equal(day.get('2026-03-09').disabled, true);
    assert.equal(day.get('2026-03-10').disabled, false);
    assert.equal(day.get('2026-03-20').disabled, false);
    assert.equal(day.get('2026-03-21').disabled, true);
    controller.navigate(dayEvent('PageDown'));
    assert.equal(focused(), '2026-03-20');
    controller.navigate(dayEvent('PageUp', { shiftKey: true }));
    assert.equal(focused(), '2026-03-10');
});

test('date picker: the buttons of the month', () => {
    const { controller, open, heading } = datePicker();
    open();
    controller.next();
    assert.equal(heading.textContent, 'April 2026');
    controller.previous();
    controller.previous();
    assert.equal(heading.textContent, 'February 2026');
});

test('date picker: a day chosen fills the field, tells the page, closes and gives the focus back', () => {
    const { controller, open, input, panel, trigger, days, day } = datePicker();
    open();
    days();
    panel.popoverOpen = true;
    controller.choose({ target: { closest: () => day.get('2026-03-20') } });
    assert.equal(input.value, '2026-03-20');
    assert.deepEqual(input.calls, [
        ['dispatchEvent', 'input'],
        ['dispatchEvent', 'change'],
    ]);
    assert.equal(panel.popoverOpen, false);
    assert.equal(FakeElement.focused, trigger);
});

test('date picker: a day disabled or a click elsewhere is nothing', () => {
    const { controller, input } = datePicker();
    controller.choose({ target: { closest: () => ({ disabled: true, dataset: { date: '2026-01-01' } }) } });
    controller.choose({ target: { closest: () => null } });
    assert.equal(input.value, '2026-03-15');
});

test('date picker: the first day of the week of its language, or Monday', () => {
    const { controller, open, weekdays } = datePicker({ firstDay: -1 });
    open();
    assert.equal(weekdays.children[0].textContent, 'Sun');
});
