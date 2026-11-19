<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

/**
 * A column of a day: a host, a venue, a tag — whatever the items are shared between.
 */
final readonly class CalendarResource
{
    public function __construct(
        public string $key,
        public string $label,
    ) {
    }
}
