<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

/**
 * What erasing a person does to a store.
 *
 * Only `Deleted` and `Anonymised` leave no personal data behind. A pseudonymised, redacted, expiring or surviving record
 * is still personal data, and the register and the reports say so.
 */
enum ErasureEffect: string
{
    case Deleted = 'deleted';

    /**
     * The record survives as a skeleton judged non-identifiable: nothing left in it, alone or with what the site or
     * anyone else can reasonably use, singles the person out. Reserved to that judgement, made and written down for the
     * store; a skeleton that a key, a hash or a rare combination of values links back to the person is `Pseudonymised`.
     */
    case Anonymised = 'anonymised';

    /**
     * The record survives with the person's identity replaced by a pseudonym, a token, a keyed hash or an internal
     * identifier. It is still personal data for whoever holds the link back, such as the key, the salt or another table.
     */
    case Pseudonymised = 'pseudonymised';

    /** Some fields are blanked or replaced; the record stays, and so may what the other fields say of the person. */
    case Redacted = 'redacted';

    /** Nothing is removed now; the period runs out. Only meaningful with a fixed period, which is the answer. */
    case AwaitsExpiry = 'awaits_expiry';

    /** Kept on purpose, under a legal obligation or because removing it would undo the request itself. */
    case Survives = 'survives';

    /**
     * Whether personal data can be left in the store after the erasure: false only for `Deleted` and `Anonymised`.
     */
    public function leavesPersonalData(): bool
    {
        return self::Deleted !== $this && self::Anonymised !== $this;
    }
}
