<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Application\Event;

use Phil\TrashBundle\Domain\Model\TrashItem;

/**
 * A resource left the trash for good, its retention over.
 */
final readonly class TrashItemPurged
{
    public function __construct(
        public TrashItem $item,
    ) {
    }
}
