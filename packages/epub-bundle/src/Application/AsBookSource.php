<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application;

use Attribute;

/**
 * Registers a `BookSource` under a name.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBookSource
{
    public function __construct(
        public string $name,
    ) {
    }
}
