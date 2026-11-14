<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Read;

/**
 * The order rows are read in: by instant, then segment, then sequence, so that two rows of the same instant always
 * come in the same order and a cursor never skips nor repeats one.
 */
enum Order: string
{
    /** The latest first: the log as a feed. */
    case NewestFirst = 'newest';

    /** The earliest first: the history of a record, as it happened. */
    case OldestFirst = 'oldest';
}
