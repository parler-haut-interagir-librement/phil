<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\Lti\Platform\Ags\StoredLineItem;

/**
 * A line item a tool created or changed on the platform.
 */
final readonly class LineItemSaved
{
    public function __construct(
        public StoredLineItem $lineItem,
        public bool $created,
    ) {
    }
}
