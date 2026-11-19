<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Review;

/**
 * What the review of a calendar reports.
 */
enum IssueKind: string
{
    /** The same content in the same language planned more than once. */
    case DuplicateContent = 'duplicate-content';

    /** Two publications closer than the minimum spacing. */
    case TooClose = 'too-close';

    /** An item planned for a moment while it is still an idea or a draft. */
    case NotReady = 'not-ready';

    /** A stretch of time longer than the maximum gap without any publication. */
    case Gap = 'gap';
}
