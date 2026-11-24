// The helpfulness controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.

import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import HelpfulController from '../../assets/src/helpful_controller.js';

const fetched = [];
let responses = [];

class FakeFormData {
    constructor(form) {
        this.entries = new Map(Object.entries(form.fields));
    }

    set(name, value) {
        this.entries.set(name, value);
    }
}

const button = (value) => {
    const attributes = new Map();

    return {
        name: 'vote',
        value,
        setAttribute: (name, content) => attributes.set(name, content),
        attribute: (name) => attributes.get(name),
    };
};

const controller = ({ label = '{helpful} found it helpful, {unhelpful} did not.', targets = true } = {}) => {
    const instance = new HelpfulController();
    const submitted = [];
    instance.element = { action: '/reviews/review/r1/vote', fields: { _token: 't' }, requestSubmit: (by) => submitted.push(by) };
    instance.submitted = submitted;
    instance.labelValue = label;
    instance.hasCountsTarget = targets;
    instance.hasHelpfulTarget = targets;
    instance.hasUnhelpfulTarget = targets;
    instance.countsTarget = { textContent: '0 found it helpful, 0 did not.' };
    instance.helpfulTarget = button('helpful');
    instance.unhelpfulTarget = button('unhelpful');

    return instance;
};

const click = (target) => {
    const event = { currentTarget: target, prevented: false, preventDefault() { this.prevented = true; } };

    return event;
};

beforeEach(() => {
    fetched.length = 0;
    responses = [];
    globalThis.FormData = FakeFormData;
    globalThis.fetch = async (url, options) => {
        fetched.push([url, options]);
        const next = responses.shift() ?? { ok: true, json: { helpful: 1, unhelpful: 0, vote: true } };

        if (next instanceof Error) {
            throw next;
        }

        return { ok: next.ok, status: next.ok ? 200 : 422, json: async () => next.json };
    };
});

afterEach(() => {
    delete globalThis.FormData;
    delete globalThis.fetch;
});

test('a vote is posted in the background and the counts and buttons are written again', async () => {
    const instance = controller();
    const event = click(instance.helpfulTarget);

    await instance.vote(event);

    assert.equal(event.prevented, true);
    assert.equal(fetched.length, 1);
    const [url, options] = fetched[0];
    assert.equal(url, '/reviews/review/r1/vote');
    assert.equal(options.method, 'POST');
    assert.equal(options.credentials, 'same-origin');
    assert.deepEqual(options.headers, { Accept: 'application/json' });
    assert.deepEqual([...options.body.entries], [['_token', 't'], ['vote', 'helpful']]);
    assert.equal(instance.countsTarget.textContent, '1 found it helpful, 0 did not.');
    assert.equal(instance.helpfulTarget.attribute('aria-pressed'), 'true');
    assert.equal(instance.helpfulTarget.value, 'retract');
    assert.equal(instance.unhelpfulTarget.attribute('aria-pressed'), 'false');
    assert.equal(instance.unhelpfulTarget.value, 'unhelpful');
    assert.deepEqual(instance.submitted, []);
    assert.equal(instance.sending, false);
});

test('a vote against and a vote taken back press the right button', async () => {
    const instance = controller();
    responses = [{ ok: true, json: { helpful: 0, unhelpful: 2, vote: false } }, { ok: true, json: { helpful: 0, unhelpful: 1, vote: null } }];

    await instance.vote(click(instance.unhelpfulTarget));
    assert.equal(instance.countsTarget.textContent, '0 found it helpful, 2 did not.');
    assert.equal(instance.unhelpfulTarget.attribute('aria-pressed'), 'true');
    assert.equal(instance.unhelpfulTarget.value, 'retract');
    assert.equal(instance.helpfulTarget.attribute('aria-pressed'), 'false');

    await instance.vote(click(instance.unhelpfulTarget));
    assert.equal(instance.unhelpfulTarget.attribute('aria-pressed'), 'false');
    assert.equal(instance.unhelpfulTarget.value, 'unhelpful');
    assert.equal(instance.helpfulTarget.value, 'helpful');
});

test('a refused vote sends the form as usual, once', async () => {
    const instance = controller();
    responses = [{ ok: false, json: { error: 'Your own review takes no vote of yours.' } }];
    const target = instance.helpfulTarget;

    await instance.vote(click(target));

    assert.deepEqual(instance.submitted, [target]);
    assert.equal(instance.countsTarget.textContent, '0 found it helpful, 0 did not.');

    const again = click(target);
    await instance.vote(again);
    assert.equal(again.prevented, false, 'The form sent as usual is not stopped again.');
    assert.equal(fetched.length, 1);
});

test('a failed request sends the form as usual', async () => {
    const instance = controller();
    responses = [new Error('offline')];

    await instance.vote(click(instance.helpfulTarget));

    assert.deepEqual(instance.submitted, [instance.helpfulTarget]);
});

test('without targets or label nothing is written', async () => {
    const instance = controller({ label: '', targets: false });

    await instance.vote(click(instance.helpfulTarget));

    assert.equal(instance.countsTarget.textContent, '0 found it helpful, 0 did not.');
    assert.equal(instance.helpfulTarget.attribute('aria-pressed'), undefined);
});

test('the counts are written with a label that has its counts once each', async () => {
    const instance = controller({ label: '{helpful} yes / {unhelpful} no' });
    responses = [{ ok: true, json: { helpful: 12, unhelpful: 3, vote: true } }];

    await instance.vote(click(instance.helpfulTarget));

    assert.equal(instance.countsTarget.textContent, '12 yes / 3 no');
});
