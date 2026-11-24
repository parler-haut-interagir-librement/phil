// The controller of the questions, under Node's test runner, on a page made of plain objects.

import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import FaqController from '../../assets/src/faq_controller.js';

globalThis.CSS = { escape: (value) => value };

class Element {
    constructor(tag, attributes = {}, children = [], text = '') {
        this.tagName = tag.toUpperCase();
        this.attributes = { ...attributes };
        this.children = children;
        this.text = text;
        this.hidden = false;
        this.open = false;
        this.dataset = {};
        this.ownerDocument = null;
        children.forEach((child) => { child.parent = this; });
    }

    get id() {
        return this.attributes.id ?? '';
    }

    get textContent() {
        return [this.text, ...this.children.map((child) => child.textContent)].join(' ');
    }

    set textContent(value) {
        this.text = value;
        this.children = [];
    }

    setAttribute(name, value) {
        this.attributes[name] = value;
    }

    descendants() {
        return this.children.flatMap((child) => [child, ...child.descendants()]);
    }

    querySelector(selector) {
        if (selector === 'details:not([hidden])') {
            return this.descendants().find((one) => one.tagName === 'DETAILS' && !one.hidden) ?? null;
        }

        const anchor = /^form\[data-anchor="(.+)"\]$/.exec(selector);
        if (anchor) {
            return this.descendants().find((one) => one.tagName === 'FORM' && one.dataset.anchor === anchor[1]) ?? null;
        }

        return this.descendants().find((one) => one.tagName === selector.toUpperCase()) ?? null;
    }

    replaceChildren(...children) {
        this.children = children;
    }
}

const listeners = {};
const storage = new Map();
const fetched = [];
let answer = { ok: true, status: 200 };

const view = {
    location: { hash: '' },
    localStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) },
    addEventListener: (name, listener) => { listeners[name] = listener; },
    removeEventListener: (name) => { delete listeners[name]; },
};

const doc = { defaultView: view, createElement: (tag) => new Element(tag) };

const question = (anchor, wording, answerText) => {
    const legend = new Element('legend', {}, [], 'Was this answer useful?');
    const fieldset = new Element('fieldset', {}, [legend]);
    const form = new Element('form', {}, [fieldset]);
    form.dataset.anchor = anchor;
    form.action = `/faq/vote/${anchor}`;
    form.ownerDocument = doc;
    const details = new Element('details', { id: anchor }, [new Element('summary', {}, [], wording), new Element('div', {}, [form], answerText)]);

    return { details, form, fieldset };
};

const page = () => {
    const pay = question('how-do-i-pay', 'How do I pay?', 'By card or transfer.');
    const order = question('where-is-my-order', 'Where is my order?', 'At the Café.');
    const ret = question('returns', 'Can I return a game?', 'Within 30 days.');
    const orders = new Element('section', {}, [pay.details, order.details]);
    const returns = new Element('section', {}, [ret.details]);
    const element = new Element('div', {}, [orders, returns]);
    element.ownerDocument = doc;

    return { element, pay, order, ret, orders, returns };
};

const controller = (built, values = {}) => {
    const faq = new FaqController();
    faq.element = built.element;
    faq.questionTargets = [built.pay.details, built.order.details, built.ret.details];
    faq.sectionTargets = [built.orders, built.returns];
    faq.inputTarget = { value: '' };
    faq.statusTarget = { textContent: '' };
    faq.noneTarget = { hidden: true };
    Object.assign(faq, {
        unansweredUrlValue: '/faq/unanswered',
        tokenValue: 'csrf-token',
        foundNoneValue: 'No question matches “%query%”.',
        foundOneValue: 'One question matches “%query%”.',
        foundManyValue: '%count% questions match “%query%”.',
        thanksValue: 'Thank you.',
        failedValue: 'Failed.',
        delayValue: 1500,
    }, values);
    faq.connect();

    return faq;
};

beforeEach(() => {
    storage.clear();
    fetched.length = 0;
    view.location.hash = '';
    answer = { ok: true, status: 200 };
    globalThis.fetch = async (url, options) => {
        fetched.push({ url, options });
        if (answer.throws) {
            throw new Error('offline');
        }

        return answer;
    };
    globalThis.FormData = class {
        constructor(form, submitter) {
            this.entries = [];
            this.form = form;
            this.submitter = submitter;
        }

        append(name, value) {
            this.entries.push([name, value]);
        }
    };
});

afterEach(() => {
    delete listeners.hashchange;
});

test('typing filters the questions and the sections, accents and case aside', () => {
    const built = page();
    const faq = controller(built);

    faq.inputTarget.value = ' CAFE ';
    assert.equal(faq.filter(), 1);
    assert.deepEqual([built.pay.details.hidden, built.order.details.hidden, built.ret.details.hidden], [true, false, true]);
    assert.deepEqual([built.orders.hidden, built.returns.hidden], [false, true]);
    assert.equal(faq.statusTarget.textContent, 'One question matches “CAFE”.');
    assert.equal(faq.noneTarget.hidden, true);

    faq.inputTarget.value = 'a';
    assert.equal(faq.filter(), 3);
    assert.equal(faq.statusTarget.textContent, '3 questions match “a”.');

    faq.inputTarget.value = '';
    assert.equal(faq.filter(), 3);
    assert.equal(faq.statusTarget.textContent, '');
    assert.deepEqual([built.orders.hidden, built.returns.hidden], [false, false]);
});

test('a search that still finds nothing after a pause is counted once', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const faq = controller(page());

    faq.inputTarget.value = 'gift cards';
    assert.equal(faq.filter(), 0);
    assert.equal(faq.noneTarget.hidden, false);
    assert.equal(faq.statusTarget.textContent, 'No question matches “gift cards”.');

    t.mock.timers.tick(1499);
    assert.equal(fetched.length, 0);
    t.mock.timers.tick(1);
    assert.equal(fetched.length, 1);
    assert.equal(fetched[0].url, '/faq/unanswered');
    assert.deepEqual(fetched[0].options.body.entries, [['q', 'gift cards'], ['_token', 'csrf-token']]);
    assert.equal(fetched[0].options.keepalive, true);
    assert.equal(fetched[0].options.method, 'POST');

    faq.filter();
    t.mock.timers.tick(1500);
    assert.equal(fetched.length, 1);
});

test('a pause on a search that finds something counts nothing, nor a short one', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const faq = controller(page());

    faq.inputTarget.value = 'zz';
    faq.filter();
    faq.inputTarget.value = 'card';
    faq.filter();
    t.mock.timers.tick(5000);

    assert.equal(fetched.length, 0);
});

test('submitting filters at once and counts a search without answer at once', () => {
    const faq = controller(page());
    let prevented = 0;
    const event = { preventDefault: () => { prevented += 1; } };

    faq.inputTarget.value = 'card';
    faq.submit(event);
    assert.equal(fetched.length, 0);

    faq.inputTarget.value = 'refund policy';
    faq.submit(event);
    assert.equal(prevented, 2);
    assert.deepEqual(fetched[0].options.body.entries[0], ['q', 'refund policy']);
});

test('nothing is counted without an address to count at', () => {
    const faq = controller(page(), { unansweredUrlValue: '' });

    faq.count('refund policy');
    assert.equal(fetched.length, 0);
});

test('the question of the address opens, now and when the address changes', () => {
    view.location.hash = '#where-is-my-order';
    const built = page();
    const faq = controller(built);
    assert.equal(built.order.details.open, true);
    assert.equal(built.pay.details.open, false);

    view.location.hash = '#returns';
    listeners.hashchange();
    assert.equal(built.ret.details.open, true);

    view.location.hash = '#nothing';
    listeners.hashchange();
    faq.disconnect();
    assert.equal(listeners.hashchange, undefined);
});

test('a vote is sent in JSON, remembered, and thanked', async () => {
    const built = page();
    const faq = controller(built);
    let prevented = false;
    const submitter = { name: 'helpful', value: 'yes' };

    await faq.vote({ preventDefault: () => { prevented = true; }, target: built.pay.form, submitter });

    assert.equal(prevented, true);
    assert.equal(fetched[0].url, '/faq/vote/how-do-i-pay');
    assert.equal(fetched[0].options.headers.Accept, 'application/json');
    assert.equal(fetched[0].options.body.submitter, submitter);
    assert.deepEqual(JSON.parse(storage.get('phil-faq-voted')), ['how-do-i-pay']);
    assert.equal(built.pay.fieldset.children.length, 2);
    assert.equal(built.pay.fieldset.children[0].tagName, 'LEGEND');
    assert.deepEqual([built.pay.fieldset.children[1].attributes.role, built.pay.fieldset.children[1].text], ['status', 'Thank you.']);
});

test('a vote this page already gave is thanked too', async () => {
    const built = page();
    const faq = controller(built);
    answer = { ok: false, status: 409 };

    await faq.vote({ preventDefault() {}, target: built.order.form, submitter: null });

    assert.equal(built.order.fieldset.children[1].text, 'Thank you.');
    assert.deepEqual(JSON.parse(storage.get('phil-faq-voted')), ['where-is-my-order']);
});

test('a refused or lost vote says so and is not remembered', async () => {
    const built = page();
    const faq = controller(built);
    answer = { ok: false, status: 429 };

    await faq.vote({ preventDefault() {}, target: built.order.form });
    assert.deepEqual([built.order.fieldset.children[1].attributes.role, built.order.fieldset.children[1].text], ['alert', 'Failed.']);

    answer = { throws: true };
    await faq.vote({ preventDefault() {}, target: built.pay.form });
    assert.equal(built.pay.fieldset.children[1].text, 'Failed.');
    assert.equal(storage.has('phil-faq-voted'), false);
});

test('the questions this browser voted for are thanked when the page opens', () => {
    storage.set('phil-faq-voted', JSON.stringify(['returns', 'gone']));
    const built = page();
    controller(built);

    assert.equal(built.ret.fieldset.children[1].text, 'Thank you.');
    assert.equal(built.pay.fieldset.children.length, 1);
});

test('a page without a window works all the same', () => {
    const built = page();
    built.element.ownerDocument = { createElement: (tag) => new Element(tag) };
    const faq = controller(built);

    assert.equal(faq.storage(), null);
    faq.disconnect();
});

test('a storage that throws counts as none', () => {
    const built = page();
    const blocked = { ...view };
    Object.defineProperty(blocked, 'localStorage', { get() { throw new Error('blocked'); } });
    built.element.ownerDocument = { defaultView: blocked, createElement: (tag) => new Element(tag) };

    assert.equal(controller(built).storage(), null);
});
