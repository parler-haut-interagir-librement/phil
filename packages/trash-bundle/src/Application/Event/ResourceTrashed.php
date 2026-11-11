<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Application\Event;

use Phil\TrashBundle\Domain\Model\TrashItem;

/**
 * A resource went to the trash: an audit trail records it.
 */
final readonly class ResourceTrashed
{
    public function __construct(
        public TrashItem $item,
    ) {
    }
}
