<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;

/**
 * Appends the PDFs into one, in the alphanumeric order of their names; the result takes the name of the first.
 */
final readonly class Merge implements Stage
{
    #[Override]
    public function operation(): Operation
    {
        return Operation::Merge;
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
