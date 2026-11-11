// The Turbo Streams of the bundle read into actions, on a small stand-in for the parsed DOM.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { BLOCK, PRESENCE, readStreams } from '../../assets/src/lib/streams.js';

function element(attributes = {}, children = [], text = '') {
    return {
        getAttribute: (name) => attributes[name] ?? null,
        textContent: text,
        firstElementChild: children[0] ?? null,
        querySelectorAll: (selector) => children.filter((child) => child.tag === selector),
        querySelector: (selector) => children.find((child) => child.tag === selector) ?? null,
    };
}

const tag = (name, attributes, children, text) => ({ ...element(attributes, children, text), tag: name });
const template = (children) => ({ tag: 'template', content: element({}, children) });
const stream = (attributes, children = []) => tag('turbo-stream', attributes, children);
const ujo = (block) => JSON.stringify({ blocks: [block] });
const block = { id: 'b1', type: 'paragraph', data: { text: 'x' } };

test('presence, saves and removals are read', () => {
    const root = element({}, [
        stream({ action: 'update', target: PRESENCE + 'abc' }, [template([
            tag('li', { 'data-holder': '7', 'data-part': 'b1', 'data-expires-in': '28' }, [], 'Ada'),
            tag('li', {}, [], 'Nobody'),
        ])]),
        stream({ action: 'replace', target: BLOCK + 'b1', 'data-holder': '7' }, [template([tag('div', { 'data-list': 't:0', 'data-after': 'b0', 'data-ujo': ujo(block) })])]),
        stream({ action: 'replace', target: BLOCK + 'b1', 'data-holder': '7' }, [template([tag('div', { 'data-ujo': ujo(block) })])]),
        stream({ action: 'remove', target: BLOCK + 'b9', 'data-holder': '8' }),
    ]);

    assert.deepEqual(readStreams(root), [
        { kind: 'presence', items: [{ holder: '7', part: 'b1', expiresIn: 28, name: 'Ada' }, { holder: '', part: '', expiresIn: 0, name: 'Nobody' }] },
        { kind: 'save', holder: '7', block, list: 't:0', after: 'b0' },
        { kind: 'save', holder: '7', block, list: 'root', after: null },
        { kind: 'remove', holder: '8', id: 'b9' },
    ]);
});

test('anything else is left out', () => {
    const root = element({}, [
        stream({ action: 'update', target: 'elsewhere' }, [template([tag('li', {}, [], 'x')])]),
        stream({ action: 'update', target: PRESENCE + 'abc' }),
        stream({ action: 'append', target: BLOCK + 'b1' }),
        stream({ action: 'replace', target: BLOCK + 'b2' }, [template([tag('div', { 'data-ujo': ujo(block) })])]),
        stream({ action: 'replace', target: BLOCK + 'b1' }, [template([tag('div', { 'data-ujo': '{"blocks":[]}' })])]),
        stream({ action: 'replace', target: BLOCK + 'b1' }, [template([tag('div', { 'data-ujo': JSON.stringify({ blocks: [{ id: 'b1' }] }) })])]),
        stream({ action: 'replace', target: BLOCK + 'b1' }, [template([])]),
        stream({ action: 'replace', target: BLOCK + 'b1' }),
        stream({ action: 'remove', target: 'other-b1' }),
        stream({ target: BLOCK + 'b1' }),
    ]);

    assert.deepEqual(readStreams(root), [{ kind: 'presence', items: [] }]);
});
