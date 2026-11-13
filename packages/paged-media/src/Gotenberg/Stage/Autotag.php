<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;

/**
 * Infers a structure tree for an untagged PDF, so screen readers can follow it.
 */
final readonly class Autotag implements Stage
{
    #[Override]
    public function operation(): Operation
    {
        return Operation::Autotag;
    }

    #[Override]
    public function options(): array
    {
        return [];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
