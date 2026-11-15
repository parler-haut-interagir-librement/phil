import assert from 'node:assert/strict';
import { test } from 'node:test';

import Comments from '../../assets/src/comments_controller.js';
import { translator } from '../../assets/src/lib/i18n.js';

const pending = { id: 't1', status: 'open', suggestion: { text: 'x', state: 'pending' }, comments: [] };
const kept = { id: 't1', status: 'resolved', suggestion: { text: 'x', state: 'rejected' }, comments: [] };

/**
 * A controller without its DOM: the panel's live region, a client answering one decision, the threads the server keeps,
 * and every render counted.
 */
function controller(answer, locale = 'en') {
    const comments = new Comments();
    const region = { textContent: '' };
    const loads = [];
    const renders = [];

    comments.element = { querySelector: (selector) => ('.phil-comments__status' === selector ? region : null) };
    comments.t = translator(locale);
    comments.threads = [pending];
    comments.client = {
        act: async () => answer,
        threads: async () => {
            loads.push(true);

            return { ok: true, status: 200, data: { threads: [kept] } };
        },
    };
    comments.render = () => renders.push(comments.message);

    return { comments, region, loads, renders };
}

/** Clicks the button of an action on the thread. */
async function click(comments, name) {
    const button = { dataset: { commentsDo: name, commentsId: 't1' } };

    await comments.clicked({ target: { closest: (selector) => ('[data-comments-do]' === selector ? button : null) } });
    await comments.reloading;
}

test('a decision that came second says so and reloads the threads once', async () => {
    const { comments, region, loads, renders } = controller({ ok: false, status: 409, data: { error: 'decided' } });

    await click(comments, 'reject');

    assert.equal(region.textContent, 'This suggestion has already been decided.');
    assert.equal(loads.length, 1);
    assert.deepEqual(comments.threads, [kept]);
    assert.deepEqual(renders, ['This suggestion has already been decided.']);
});

test('the message of a decision that came second is translated', async () => {
    const { comments, region } = controller({ ok: false, status: 409, data: { error: 'decided' } }, 'fr_CA');

    await click(comments, 'reject');

    assert.equal(region.textContent, 'Cette suggestion a déjà été décidée.');
});

test('another conflict fails without a reload', async () => {
    for (const data of [{ error: 'other' }, null, {}]) {
        const { comments, region, loads, renders } = controller({ ok: false, status: 409, data });

        await click(comments, 'reject');

        assert.equal(region.textContent, 'The request failed.');
        assert.equal(loads.length, 0);
        assert.deepEqual(comments.threads, [pending]);
        assert.deepEqual(renders, []);
    }
});

test('a refusal is said as before, without a reload', async () => {
    for (const data of [{ error: 'refused' }, { error: 'decided' }]) {
        const { comments, region, loads } = controller({ ok: false, status: 403, data });

        await click(comments, 'resolve');

        assert.equal(region.textContent, 'You may not do this.');
        assert.equal(loads.length, 0);
    }
});

test('the body names decided on another status: failed, without a reload', async () => {
    const { comments, region, loads } = controller({ ok: false, status: 400, data: { error: 'decided' } });

    await click(comments, 'reject');

    assert.equal(region.textContent, 'The request failed.');
    assert.equal(loads.length, 0);
});

test('a decision kept puts the answered thread in place and clears the region', async () => {
    const { comments, region, loads, renders } = controller({ ok: true, status: 200, data: { thread: kept } });
    region.textContent = 'This suggestion has already been decided.';

    await click(comments, 'reject');

    assert.equal(region.textContent, '');
    assert.equal(loads.length, 0);
    assert.deepEqual(comments.threads, [kept]);
    assert.deepEqual(renders, ['']);
});
