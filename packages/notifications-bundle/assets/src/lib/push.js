// What the Web Push controller needs without a browser of its own: whether the browser can subscribe, the encoding
// its push service takes, the application's key as bytes, and the body sent to the site.

/**
 * Whether the window can register a service worker, subscribe to push and show notifications.
 */
export function isSupported(win) {
    return (
        null !== win &&
        'object' === typeof win &&
        'object' === typeof win.navigator &&
        null !== win.navigator &&
        'serviceWorker' in win.navigator &&
        'PushManager' in win &&
        'Notification' in win
    );
}

/**
 * The content encoding the browser's push service takes: `aes128gcm` (RFC 8291), or `aesgcm` for an older browser that
 * names only that one.
 */
export function contentEncoding(pushManager) {
    const encodings = pushManager?.supportedContentEncodings;

    return Array.isArray(encodings) && !encodings.includes('aes128gcm') && encodings.includes('aesgcm')
        ? 'aesgcm'
        : 'aes128gcm';
}

/**
 * The application's public key, written in URL-safe Base64, as the bytes `pushManager.subscribe()` takes.
 */
export function keyBytes(base64url) {
    const base64 = String(base64url).replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    const binary = globalThis.atob(padded);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}

/**
 * What the site keeps of a subscription: its endpoint, its two keys, and the encoding chosen.
 */
export function subscriptionBody(subscription, encoding) {
    const json = subscription.toJSON();

    return JSON.stringify({
        endpoint: json.endpoint,
        keys: { p256dh: json.keys?.p256dh, auth: json.keys?.auth },
        contentEncoding: encoding,
    });
}
