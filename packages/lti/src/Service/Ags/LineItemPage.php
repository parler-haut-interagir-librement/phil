<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

use Phil\Url\Url;

/**
 * A page of the line items of a context, and the address of the next page when there is one.
 */
final readonly class LineItemPage
{
    /**
     * @param list<LineItem> $lineItems
     */
    public function __construct(
        public array $lineItems,
        public ?Url $next = null,
    ) {
    }
}
