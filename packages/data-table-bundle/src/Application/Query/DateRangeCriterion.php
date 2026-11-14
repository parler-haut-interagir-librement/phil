<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

use DateTimeImmutable;

/**
 * The field is at or after `$from` and strictly before `$before`, each bound being optional.
 */
final readonly class DateRangeCriterion
{
    public function __construct(
        public string $field,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $before,
    ) {
    }
}
