<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use Override;
use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\Resource\ResourceReference;

/**
 * An entity of the application whose access the bundle decides.
 */
final readonly class Page implements AccessibleItem
{
    public function __construct(
        public string $id,
    ) {
    }

    #[Override]
    public function accessReference(): ResourceReference
    {
        return ResourceReference::of('page', $this->id);
    }
}
