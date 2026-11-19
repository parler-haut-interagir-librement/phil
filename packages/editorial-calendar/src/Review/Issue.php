<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Review;

use DateTimeImmutable;

/**
 * A conflict or a gap the review found: its kind, the UTC moments it spans, and the items it concerns.
 */
final readonly class Issue
{
    /**
     * @param list<string> $items the identifiers of the items concerned, in their order; empty for a gap
     */
    public function __construct(
        public IssueKind $kind,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public array $items = [],
    ) {
    }
}
