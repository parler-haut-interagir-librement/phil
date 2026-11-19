<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Transition;

use DateTimeImmutable;
use Phil\EditorialCalendar\Moment;
use Phil\Resource\ResourceReference;

/**
 * A transition planned for a resource at a moment, kept in UTC: what a scheduler will apply.
 */
final readonly class PlannedTransition
{
    public DateTimeImmutable $at;

    public function __construct(
        public ResourceReference $resource,
        public string $transition,
        DateTimeImmutable $at,
    ) {
        $this->at = Moment::utc($at);
    }
}
