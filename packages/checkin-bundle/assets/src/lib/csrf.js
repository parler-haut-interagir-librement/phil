// The double-submitted token of Symfony's stateless CSRF protection: a new random token per request, sent in a header
// named as the server says and, in a cookie of that name, back to the server, which finds the two equal. Another site
// can neither read nor set this site's cookies, so it cannot send both.

const NAME = /^[-A-Za-z0-9_]+$/;

/** A new token: 18 random bytes as 24 URL-safe base64 characters. */
export function newToken(random = globalThis.crypto) {
    const bytes = new Uint8Array(18);
    random.getRandomValues(bytes);

    return btoa(String.fromCharCode(...bytes))
        .replaceAll('+', '-')
        .replaceAll('/', '_');
}

/**
 * The headers of a request that carries a new token, its cookie `<name>_<token>=<name>` set for a minute, same-site,
 * prefixed with `__Host-` and secure over HTTPS; none when the name is not one the server gives.
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
