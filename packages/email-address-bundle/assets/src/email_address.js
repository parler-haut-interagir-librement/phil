/*
 * What the email field says of an address, from the lists the bundle's route serves — the same the server suggests
 * and warns from. No DOM here: the controller shows it.
 *
 * The suggestion follows the server's: a domain is compared with each known domain of eight characters or more by
 * its optimal string alignment distance, one slip allowed up to nine characters, two for a longer one; the closest
 * wins, the first in the list on a tie. Failing that, a top-level domain that is a common slip is replaced.
 */

const SHORT_DOMAIN = 8;
const LONG_DOMAIN = 10;
const DOMAIN = /^[a-z0-9.-]{1,253}$/;

/**
 * The optimal string alignment distance of two strings: insertions, deletions, substitutions and swaps of two
 * neighbours each cost one.
 */
export function distance(a, b) {
    let before = [];
    let previous = Array.from({ length: b.length + 1 }, (_, j) => j);

    for (let i = 1; i <= a.length; i++) {
        const current = [i];

        for (let j = 1; j <= b.length; j++) {
            let value = Math.min(
                previous[j] + 1,
                current[j - 1] + 1,
                previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1),
            );

            if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                value = Math.min(value, before[j - 2] + 1);
            }

            current[j] = value;
        }

        before = previous;
        previous = current;
    }

    return previous[b.length];
}

/**
 * The address meant, or null when the domain is a known one, close to none, or the text is no address.
 *
 * @param {string} address
 * @param {{domains: string[], slips: Object<string, string>}} lists
 */
export function suggest(address, lists) {
    const text = address.trim();
    const at = text.lastIndexOf('@');

    if (at < 1) {
        return null;
    }

    const domain = text.slice(at + 1).toLowerCase();

    if (lists.domains.includes(domain) || !DOMAIN.test(domain)) {
        return null;
    }

    const meant = closest(domain, lists.domains) ?? slip(domain, lists.slips);

    return meant === null ? null : `${text.slice(0, at)}@${meant}`;
}

/**
 * Whether the address names a role rather than a person; a `+tag` is ignored.
 *
 * @param {string} address
 * @param {string[]} roles the parts before the @ that name a role, in lowercase
 */
export function isRole(address, roles) {
    const text = address.trim();
    const at = text.lastIndexOf('@');

    if (at < 1) {
        return false;
    }

    return roles.includes(text.slice(0, at).toLowerCase().split('+')[0]);
}

/**
 * What to tell the person of an address: a suggestion first, else a role warning, else nothing.
 *
 * @returns {{kind: 'suggestion', address: string}|{kind: 'role'}|null}
 */
export function hint(address, lists) {
    const suggestion = suggest(address, lists);

    if (suggestion !== null) {
        return { kind: 'suggestion', address: suggestion };
    }

    return isRole(address, lists.roles ?? []) ? { kind: 'role' } : null;
}

function closest(domain, domains) {
    let best = null;
    let bestDistance = Infinity;

    for (const known of domains) {
        // A short domain is one slip away from too many real ones.
        if (known.length < SHORT_DOMAIN) {
            continue;
        }

        const limit = known.length >= LONG_DOMAIN ? 2 : 1;

        if (Math.abs(known.length - domain.length) > limit) {
            continue;
        }

        const candidate = distance(domain, known);

        if (candidate <= limit && candidate < bestDistance) {
            best = known;
            bestDistance = candidate;
        }
    }

    return best;
}

function slip(domain, slips) {
    const dot = domain.lastIndexOf('.');

    if (dot < 0) {
        return null;
    }

    const meant = Object.hasOwn(slips, domain.slice(dot + 1)) ? slips[domain.slice(dot + 1)] : null;

    return meant === null ? null : `${domain.slice(0, dot)}.${meant}`;
}
