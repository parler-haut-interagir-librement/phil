<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

/**
 * Whether an access request returns a store.
 */
enum AccessResponse: string
{
    /** Returned field by field, with its retention period. */
    case Returned = 'returned';

    /** Named with its retention period, without extracting its records: logs, backups. */
    case AsCategory = 'as_category';

    case NotReturned = 'not_returned';
}
