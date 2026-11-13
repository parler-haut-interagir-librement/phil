<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Keeps the pages listed, in the order listed.
 */
final readonly class Select implements Stage
{
    public Pages $pages;

    public function __construct(Pages|string $pages)
    {
        $this->pages = $pages instanceof Pages ? $pages : Pages::of($pages);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Select;
    }

    #[Override]
    public function options(): array
    {
        return ['pages' => $this->pages->value];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
