// The double-submit token of Symfony's stateless CSRF protection: a new random token for each request, sent in a
// header or a form field and, in the name of a cookie the page sets for that request, back to the server, which
// finds the two equal. Another site can neither read nor set this site's cookies, so it cannot send both. The name
// of the cookie is the one the server gives, the value of the token before the page replaces it.

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
 * Sets the cookie that carries a new token back to the server, `<name>_<token>=<name>`, for the whole site, same-site
 * only, for a minute, prefixed with `__Host-` and secure over HTTPS; the token, or null when the name is not one the
 * server gives.
 */
export function csrfToken(name, doc = globalThis.document, random = globalThis.crypto) {
    if ('string' !== typeof name || !NAME.test(name)) {
        return null;
    }

    const token = newToken(random);
    const secure = 'https:' === doc.location?.protocol;
    doc.cookie = `${secure ? '__Host-' : ''}${name}_${token}=${name}; path=/; max-age=60; samesite=strict${secure ? '; secure' : ''}`;

    return token;
}

/** The header of a request carrying a new token, named as the cookie; none when the server gave no name. */
export function csrfHeaders(name, doc = globalThis.document, random = globalThis.crypto) {
    const token = csrfToken(name, doc, random);

    return null === token ? {} : { [name]: token };
}

/**
 * Puts a new token in the form's hidden field `_csrf_token`, in place of the name the server rendered there, before
 * the form is sent; the field keeps the name for the next submission, read from `data-csrf-cookie`.
 */
export function fillForm(form, doc = globalThis.document, random = globalThis.crypto) {
    const field = form.querySelector('input[name="_csrf_token"]');

    if (null === field) {
        return null;
    }

    field.dataset.csrfCookie ??= field.value;
    const token = csrfToken(field.dataset.csrfCookie, doc, random);

    if (null !== token) {
        field.value = token;
    }

    return token;
}
