<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

/**
 * The field holds this choice.
 */
final readonly class ChoiceCriterion
{
    public function __construct(
        public string $field,
        public string $value,
    ) {
    }
}
