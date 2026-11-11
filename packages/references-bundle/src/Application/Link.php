<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * A link a collector found in a source: where, and to what.
 */
final readonly class Link
{
    public function __construct(
        public string $field,
        public ResourceReference $target,
    ) {
    }
}
