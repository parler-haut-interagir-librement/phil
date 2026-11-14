<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\AccountDeletion;

/**
 * Where an account deletion stands, as the workflow marks it.
 */
enum DeletionPlace: string
{
    /** Asked for: the delay runs, and the person can still cancel. */
    case Requested = 'requested';

    /** The delay is over and the export is written; the erasure is next, and is retried from here if it fails. */
    case Exported = 'exported';

    /** Every module erased the person. */
    case Erased = 'erased';

    /** Withdrawn during the delay. */
    case Cancelled = 'cancelled';
}
