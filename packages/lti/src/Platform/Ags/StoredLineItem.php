<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Ags;

use Phil\Lti\Service\Ags\LineItem;

/**
 * A line item the platform keeps: its identifier, the tool it belongs to, its context and its fields. Its address is
 * made by the application from its identifier.
 */
final readonly class StoredLineItem
{
    public function __construct(
        public string $id,
        public string $clientId,
        public string $contextId,
        public LineItem $lineItem,
    ) {
    }
}
