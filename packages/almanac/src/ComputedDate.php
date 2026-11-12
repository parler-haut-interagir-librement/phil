<?php

declare(strict_types=1);

namespace Phil\Almanac;

/**
 * One date the almanac found: which definition, and when.
 */
final readonly class ComputedDate
{
    public function __construct(
        public Definition $definition,
        public DateSpan $span,
    ) {
    }
}
