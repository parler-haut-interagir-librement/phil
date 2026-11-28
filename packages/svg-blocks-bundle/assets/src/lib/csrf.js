// The double-submit token of Symfony's stateless CSRF protection: a new random token for each request, sent in a
// header named as the cookie the server gives, and set in a cookie `<name>_<token>=<name>` for the server to find
// equal. Another site can neither read nor set this site's cookies, so it cannot send both.

const NAME = /^[-A-Za-z0-9_]+$/;

/** A new random token: 18 random bytes, as 24 URL-safe base64 characters. */
export function newToken(random = globalThis.crypto) {
    const bytes = new Uint8Array(18);
    random.getRandomValues(bytes);

    return btoa(String.fromCharCode(...bytes))
        .replaceAll('+', '-')
        .replaceAll('/', '_');
}

/**
 * The header of a request carrying a new token, its cookie set for a minute, same-site only, prefixed `__Host-` and
 * secure over HTTPS; no header when the name is not one the server gives.
 */
export function csrfHeaders(name, doc = globalThis.document, random = globalThis.crypto) {
    if ('string' !== typeof name || !NAME.test(name)) {
        return {};
    }

    const token = newToken(random);
    const secure = 'https:' === doc.location?.protocol;
    doc.cookie = `${secure ? '__Host-' : ''}${name}_${token}=${name}; path=/; max-age=60; samesite=strict${secure ? '; secure' : ''}`;

    return { [name]: token };
}
