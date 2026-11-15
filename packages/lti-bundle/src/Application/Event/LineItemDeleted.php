<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\Lti\Platform\Ags\StoredLineItem;

/**
 * A line item a tool deleted on the platform, with its scores.
 */
final readonly class LineItemDeleted
{
    public function __construct(
        public StoredLineItem $lineItem,
    ) {
    }
}
