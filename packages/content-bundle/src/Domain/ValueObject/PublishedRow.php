<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\ValueObject;

use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * A localized live row a locale serves, and the shared row of the same version when there is one: the two rows
 * `ContentAggregator::mergeRows()` merges into what the page shows.
 */
final readonly class PublishedRow
{
    public function __construct(
        public DimensionContent $localized,
        public ?DimensionContent $shared,
    ) {
    }
}
