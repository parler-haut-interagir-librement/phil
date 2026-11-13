<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Removes the pages listed.
 */
final readonly class Delete implements Stage
{
    public Pages $pages;

    public function __construct(Pages|string $pages)
    {
        $this->pages = $pages instanceof Pages ? $pages : Pages::of($pages);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Delete;
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
