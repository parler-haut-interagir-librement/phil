<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

/**
 * What erasing a person does to a store.
 */
enum ErasureEffect: string
{
    case Deleted = 'deleted';

    /** The record survives, without anything that names the person. */
    case Pseudonymised = 'pseudonymised';

    /** Some fields are blanked or replaced; the record stays. */
    case Redacted = 'redacted';

    /** Nothing is removed now; the period runs out. Only meaningful with a fixed period, which is the answer. */
    case AwaitsExpiry = 'awaits_expiry';

    /** Kept on purpose, under a legal obligation or because removing it would undo the request itself. */
    case Survives = 'survives';
}
