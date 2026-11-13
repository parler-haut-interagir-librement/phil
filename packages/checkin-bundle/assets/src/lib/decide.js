// The admission policy of phil/checkin, for a device offline: the same order of rules as `AdmissionPolicy::decide()`,
// on an entry of the manifest and its attendance kept on the device. Instants are Unix timestamps in seconds.

/**
 * The outcome of a scan: `revoked`, then for an exit `checked_out` or `not_inside`; for an entry `not_yet_valid`,
 * `expired`, `already_admitted` under anti-passback or with the entries used, and otherwise `admitted`.
 */
export function decide(entry, policy, direction, now) {
    if (entry.revoked) {
        return 'revoked';
    }

    if ('out' === direction) {
        return entry.inside ? 'checked_out' : 'not_inside';
    }

    if (null !== entry.validFrom && now < entry.validFrom - (policy.earlySeconds ?? 0)) {
        return 'not_yet_valid';
    }

    if (null !== entry.validUntil && now > entry.validUntil) {
        return 'expired';
    }

    if (
        (false !== policy.antiPassback && entry.inside) ||
        (null !== entry.maxEntries && entry.entries >= entry.maxEntries)
    ) {
        return 'already_admitted';
    }

    return 'admitted';
}

/** The entry once an outcome is applied to it: an admission adds an entry and puts the holder inside, a check-out lets them out. */
export function apply(entry, outcome) {
    if ('admitted' === outcome) {
        return { ...entry, entries: entry.entries + 1, inside: true };
    }

    if ('checked_out' === outcome) {
        return { ...entry, inside: false };
    }

    return entry;
}
