// The browser side of the consent, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { afterEach, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';

import ConsentController from '../../assets/src/consent_controller.js';

let reloads;
let templates;

class FakeFormData {
    constructor(form, submitter) {
        this.form = form;
        this.submitter = submitter;
    }
}

const controller = (granted = [], dismiss = true) => {
    const instance = new ConsentController();
    instance.grantedValue = granted;
    instance.dismissValue = dismiss;
    instance.element = { hidden: false };
    instance.events = [];
    instance.dispatch = (name, options) => instance.events.push([name, options.detail]);

    return instance;
};

const submitEvent = () => {
    const form = {
        action: '/consent',
        submitted: [],
        requestSubmit(submitter) {
            this.submitted.push(submitter);
        },
    };

    return {
        target: form,
        submitter: { name: 'decision', value: 'accept' },
        prevented: false,
        preventDefault() {
            this.prevented = true;
        },
    };
};

const template = (category) => ({
    dataset: { privacyConsent: category },
    content: `content of ${category}`,
    replacedWith: null,
    replaceWith(node) {
        this.replacedWith = node;
    },
});

beforeEach(() => {
    reloads = 0;
    templates = [];
    globalThis.FormData = FakeFormData;
    globalThis.window = { location: { reload: () => reloads++ } };
    globalThis.document = {
        querySelectorAll: (selector) => {
            assert.equal(selector, 'template[data-privacy-consent]');

            return templates;
        },
        importNode: (content, deep) => `${content} (deep: ${deep})`,
    };
});

afterEach(() => {
    delete globalThis.fetch;
});

test('a decision is sent in the background, with the button that made it', async () => {
    const event = submitEvent();
    let request;
    globalThis.fetch = async (url, options) => {
        request = [url, options];

        return { ok: true, json: async () => ({ granted: ['analytics'] }) };
    };
    const instance = controller();

    await instance.submit(event);

    assert.equal(event.prevented, true);
    assert.equal(request[0], '/consent');
    assert.equal(request[1].method, 'POST');
    assert.equal(request[1].headers.Accept, 'application/json');
    assert.equal(request[1].credentials, 'same-origin');
    assert.equal(request[1].body.form, event.target);
    assert.equal(request[1].body.submitter, event.submitter);
    assert.deepEqual(instance.grantedValue, ['analytics']);
    assert.equal(instance.element.hidden, true);
    assert.deepEqual(event.target.submitted, []);
});

test('a refused request falls back on the plain form, once', async () => {
    const event = submitEvent();
    globalThis.fetch = async () => ({ ok: false, status: 400 });
    const instance = controller();

    await instance.submit(event);

    assert.deepEqual(event.target.submitted, [event.submitter]);

    const again = submitEvent();
    await instance.submit(again);
    assert.equal(again.prevented, false);
});

test('a network failure falls back on the plain form', async () => {
    const event = submitEvent();
    event.submitter = undefined;
    globalThis.fetch = async () => {
        throw new Error('offline');
    };

    await controller().submit(event);

    assert.deepEqual(event.target.submitted, [undefined]);
});

test('the new categories are announced and their templates come to life', () => {
    const analytics = template('analytics');
    const marketing = template('marketing');
    templates = [analytics, marketing];
    const instance = controller();

    instance.apply(['analytics']);

    assert.deepEqual(instance.events, [['changed', { granted: ['analytics'] }]]);
    assert.equal(analytics.replacedWith, 'content of analytics (deep: true)');
    assert.equal(marketing.replacedWith, null);
    assert.equal(reloads, 0);
});

test('a consent withdrawn reloads the page and leaves the banner alone', () => {
    const analytics = template('analytics');
    templates = [analytics];
    const instance = controller(['analytics', 'marketing']);

    instance.apply(['analytics']);

    assert.equal(reloads, 1);
    assert.equal(instance.element.hidden, false);
    assert.equal(analytics.replacedWith, null);
    assert.deepEqual(instance.events, [['changed', { granted: ['analytics'] }]]);
    assert.deepEqual(instance.grantedValue, ['analytics']);
});

test('a choice that keeps everything does not reload', () => {
    const instance = controller(['analytics']);

    instance.apply(['analytics', 'marketing']);

    assert.equal(reloads, 0);
});

test('the page of the choices stays open', () => {
    const instance = controller([], false);

    instance.apply(['analytics']);

    assert.equal(instance.element.hidden, false);
});
