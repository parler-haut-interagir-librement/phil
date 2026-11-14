<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * A page number of the pagination.
 */
final readonly class PageLink
{
    public function __construct(
        public int $number,
        public string $href,
        public bool $current,
    ) {
    }
}
