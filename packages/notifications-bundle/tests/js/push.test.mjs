// What the Web Push controller reads of the browser and sends to the site.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { contentEncoding, isSupported, keyBytes, subscriptionBody } from '../../assets/src/lib/push.js';

test('a browser that can subscribe has a service worker, push and notifications', () => {
    const win = { navigator: { serviceWorker: {} }, PushManager: class {}, Notification: class {} };

    assert.equal(isSupported(win), true);
    assert.equal(isSupported({ ...win, navigator: {} }), false);
    assert.equal(isSupported({ ...win, navigator: null }), false);
    assert.equal(isSupported({ navigator: { serviceWorker: {} }, Notification: class {} }), false);
    assert.equal(isSupported({ navigator: { serviceWorker: {} }, PushManager: class {} }), false);
    assert.equal(isSupported(null), false);
    assert.equal(isSupported(undefined), false);
    assert.equal(isSupported('window'), false);
});

test('the encoding is aes128gcm unless the browser names only aesgcm', () => {
    assert.equal(contentEncoding({ supportedContentEncodings: ['aes128gcm', 'aesgcm'] }), 'aes128gcm');
    assert.equal(contentEncoding({ supportedContentEncodings: ['aesgcm'] }), 'aesgcm');
    assert.equal(contentEncoding({ supportedContentEncodings: [] }), 'aes128gcm');
    assert.equal(contentEncoding({ supportedContentEncodings: 'aesgcm' }), 'aes128gcm');
    assert.equal(contentEncoding({}), 'aes128gcm');
    assert.equal(contentEncoding(undefined), 'aes128gcm');
});

test('the key is decoded from URL-safe Base64, padded or not', () => {
    assert.deepEqual([...keyBytes('BAEC_-8')], [4, 1, 2, 255, 239]);
    assert.deepEqual([...keyBytes('BAEC_-8=')], [4, 1, 2, 255, 239]);
    assert.deepEqual([...keyBytes('AAAA')], [0, 0, 0]);
    assert.deepEqual([...keyBytes('AA')], [0]);
    assert.equal(keyBytes('').length, 0);
});

test('the body holds the endpoint, the two keys and the encoding', () => {
    const subscription = {
        toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/x', expirationTime: null, keys: { p256dh: 'P', auth: 'A' } }),
    };

    assert.equal(
        subscriptionBody(subscription, 'aesgcm'),
        '{"endpoint":"https://fcm.googleapis.com/x","keys":{"p256dh":"P","auth":"A"},"contentEncoding":"aesgcm"}',
    );
    assert.equal(
        subscriptionBody({ toJSON: () => ({ endpoint: 'e' }) }, 'aes128gcm'),
        '{"endpoint":"e","keys":{},"contentEncoding":"aes128gcm"}',
    );
});
