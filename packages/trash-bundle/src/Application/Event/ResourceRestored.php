<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Application\Event;

use Phil\TrashBundle\Domain\Model\TrashItem;

/**
 * A resource came back from the trash.
 */
final readonly class ResourceRestored
{
    public function __construct(
        public TrashItem $item,
        public object $resource,
    ) {
    }
}
