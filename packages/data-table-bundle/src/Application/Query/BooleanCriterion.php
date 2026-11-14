<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

/**
 * The field is true, or false.
 */
final readonly class BooleanCriterion
{
    public function __construct(
        public string $field,
        public bool $value,
    ) {
    }
}
