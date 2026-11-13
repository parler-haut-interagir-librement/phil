import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { MAX_HEIGHT, PROTOCOL, VERSION, message, parseMessage } from '../../assets/src/lib/message.js';

const fixtures = JSON.parse(readFileSync(new URL('../Fixtures/messages.json', import.meta.url), 'utf8'));

test('the messages read as the protocol reference reads them', () => {
    for (const { data, expected } of fixtures) {
        assert.deepEqual(parseMessage(data), expected, JSON.stringify(data));
    }
});

test('a message is a plain object, not one of a class', () => {
    class Lookalike {
        constructor() {
            this.protocol = 'phil-embed';
            this.version = 1;
            this.type = 'ready';
        }
    }

    assert.equal(parseMessage(new Lookalike()), null);
    assert.equal(parseMessage(Object.assign(Object.create(null), { protocol: 'phil-embed', version: 1, type: 'ready' })), null);
    assert.equal(parseMessage(undefined), null);
});

test('a message to post', () => {
    assert.deepEqual([PROTOCOL, VERSION, MAX_HEIGHT], ['phil-embed', 1, 100000]);
    assert.deepEqual(message('ready'), { protocol: 'phil-embed', version: 1, type: 'ready' });
    assert.deepEqual(message('resize', { height: 5 }), { protocol: 'phil-embed', version: 1, type: 'resize', height: 5 });
    assert.deepEqual(parseMessage(message('theme', { theme: 'dark' })), { type: 'theme', theme: 'dark' });
});
