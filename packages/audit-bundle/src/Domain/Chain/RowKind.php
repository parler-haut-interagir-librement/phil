<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

/**
 * What a row of the trail records.
 */
enum RowKind: string
{
    /** An audited event. */
    case Event = 'event';

    /** A restore of the database: the rows after the last anchor may be gone, and this row says so. */
    case Discontinuity = 'discontinuity';
}
