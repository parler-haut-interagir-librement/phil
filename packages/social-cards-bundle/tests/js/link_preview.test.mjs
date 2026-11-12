// The link preview controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import LinkPreviewController, { firstLink } from '../../assets/src/link_preview_controller.js';

class Node {
    constructor(tag) {
        this.tag = tag;
        this.children = [];
        this.listeners = {};
        this.textContent = '';
    }

    append(...nodes) {
        this.children.push(...nodes);
    }

    replaceChildren(...nodes) {
        this.children = nodes;
    }

    addEventListener(type, listener) {
        this.listeners[type] = listener;
    }

    find(className) {
        if (this.className === className) {
            return this;
        }

        for (const child of this.children) {
            const found = child.find(className);

            if (found) {
                return found;
            }
        }

        return null;
    }
}

const controller = (text = '') => {
    const instance = new LinkPreviewController();
    instance.element = { ownerDocument: { createElement: (tag) => new Node(tag) } };
    instance.sourceTarget = { value: text };
    instance.fieldTarget = { value: '' };
    instance.previewTarget = new Node('div');
    instance.previewTarget.hidden = true;
    instance.endpointValue = '/social-cards/link-preview';
    instance.delayValue = 0;
    instance.removeLabelValue = 'Remove';
    instance.connect();

    return instance;
};

const answers = (byUrl) => {
    const asked = [];
    globalThis.fetch = async (url, options) => {
        asked.push([url, options]);
        const link = decodeURIComponent(url.split('?url=')[1]);
        const answer = byUrl[link];

        if (answer instanceof Error) {
            throw answer;
        }

        return { status: answer ? 200 : 204, json: async () => answer };
    };

    return asked;
};

test('the first link of a text', () => {
    assert.equal(firstLink('See (https://example.org/wiki/A_(b)), then https://example.org/b.'), 'https://example.org/wiki/A_(b)');
    assert.equal(firstLink('« https://example.org/fr »'), 'https://example.org/fr');
    assert.equal(firstLink('[https://example.org/a]'), 'https://example.org/a');
    assert.equal(firstLink('https://example.org/b[1]'), 'https://example.org/b[1]');
    assert.equal(firstLink('xhttps://example.org/ no'), null);
    assert.equal(firstLink('https://.example'), null);
    assert.equal(firstLink('no link'), null);
    assert.equal(firstLink(`https://example.org/${'a'.repeat(2048)}`), null);
    assert.equal(firstLink(`https://example.org/${'a'.repeat(2028)}`).length, 2048);
});

test('the preview of the first link is shown and its address kept in the field', async () => {
    const asked = answers({ 'https://example.org/post': { url: 'https://example.org/post', title: 'The post', site_name: 'Example', description: 'Its summary', image: 'https://example.org/i.png', image_alt: 'A cat' } });
    const instance = controller('Look at https://example.org/post.');

    await instance.update();

    assert.deepEqual(asked, [['/social-cards/link-preview?url=https%3A%2F%2Fexample.org%2Fpost', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }]]);
    assert.equal(instance.fieldTarget.value, 'https://example.org/post');
    assert.equal(instance.previewTarget.hidden, false);
    const card = instance.previewTarget.children[0];
    assert.equal(card.className, 'phil-link-preview');
    assert.equal(card.find('phil-link-preview__title').textContent, 'The post');
    assert.equal(card.find('phil-link-preview__title').tag, 'strong');
    assert.equal(card.find('phil-link-preview__site').textContent, 'Example');
    assert.equal(card.find('phil-link-preview__description').textContent, 'Its summary');
    const image = card.find('phil-link-preview__image');
    assert.deepEqual([image.src, image.alt, image.loading, image.referrerPolicy], ['https://example.org/i.png', 'A cat', 'lazy', 'no-referrer']);
    assert.equal(card.find('phil-link-preview__remove').textContent, 'Remove');
    assert.equal(instance.dispatched[0][0], 'shown');

    await instance.update();
    assert.equal(asked.length, 1, 'The same link is not fetched again.');
});

test('an image that is not an http address is left out, and so are empty parts', async () => {
    answers({ 'https://example.org/a': { title: 'A', image: 'javascript:alert(1)', description: '' } });
    const instance = controller('https://example.org/a');

    await instance.update();

    const card = instance.previewTarget.children[0];
    assert.equal(card.find('phil-link-preview__image'), null);
    assert.equal(card.find('phil-link-preview__description'), null);
    assert.equal(card.find('phil-link-preview__site'), null);
});

test('a removed preview stays removed while its link is in the text', async () => {
    const asked = answers({ 'https://example.org/post': { title: 'The post' } });
    const instance = controller('https://example.org/post');
    await instance.update();

    instance.previewTarget.children[0].find('phil-link-preview__remove').listeners.click();

    assert.equal(instance.fieldTarget.value, '');
    assert.equal(instance.previewTarget.hidden, true);
    assert.deepEqual(instance.previewTarget.children, []);
    assert.deepEqual(instance.dispatched.at(-1), ['removed', { url: 'https://example.org/post' }]);

    instance.sourceTarget.value = 'Edited: https://example.org/post';
    await instance.update();
    instance.sourceTarget.value = 'Nothing';
    await instance.update();
    instance.sourceTarget.value = 'Back: https://example.org/post';
    await instance.update();
    assert.equal(asked.length, 1);
    assert.equal(instance.fieldTarget.value, '');
});

test('a page without a preview, an error or no link clear the preview', async () => {
    answers({ 'https://example.org/post': { title: 'The post' }, 'https://example.org/broken': new Error('offline') });
    const instance = controller('https://example.org/post');
    await instance.update();
    assert.equal(instance.fieldTarget.value, 'https://example.org/post');

    instance.sourceTarget.value = 'https://example.org/dead';
    await instance.update();
    assert.deepEqual([instance.fieldTarget.value, instance.previewTarget.hidden], ['', true]);

    instance.sourceTarget.value = 'https://example.org/broken';
    await instance.update();
    assert.deepEqual([instance.fieldTarget.value, instance.previewTarget.hidden], ['', true]);

    answers({ 'https://example.org/post': { url: 'x' } });
    instance.sourceTarget.value = 'https://example.org/post';
    await instance.update();
    assert.equal(instance.fieldTarget.value, '', 'An answer without a title is no preview.');

    instance.sourceTarget.value = 'no link';
    await instance.update();
    assert.equal(instance.current, null);
});

test('an answer that comes after the text changed is dropped', async () => {
    let release;
    globalThis.fetch = () =>
        new Promise((resolve) => {
            release = () => resolve({ status: 200, json: async () => ({ title: 'Late' }) });
        });
    const instance = controller('https://example.org/slow');
    const pending = instance.update();

    instance.sourceTarget.value = 'gone';
    await instance.update();
    release();
    await pending;

    assert.deepEqual([instance.fieldTarget.value, instance.previewTarget.children], ['', []]);
});

test('the text is scanned after the delay, once', async () => {
    const asked = answers({ 'https://example.org/post': { title: 'The post' } });
    const instance = controller('https://example.org/post');
    instance.delayValue = 5;

    instance.scan();
    instance.scan();
    await new Promise((resolve) => setTimeout(resolve, 30));

    assert.equal(asked.length, 1);

    instance.sourceTarget.value = 'https://example.org/other';
    instance.scan();
    instance.disconnect();
    await new Promise((resolve) => setTimeout(resolve, 30));
    assert.equal(asked.length, 1, 'Nothing is scanned once the controller is gone.');
});
