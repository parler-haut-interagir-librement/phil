<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\ValueObject;

/**
 * One value of a facet and how many documents have it.
 */
final readonly class FacetValue
{
    public function __construct(
        public string $value,
        public int $count,
    ) {
    }
}
