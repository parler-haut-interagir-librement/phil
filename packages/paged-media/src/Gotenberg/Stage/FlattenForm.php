<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;

/**
 * Burns the form fields into the pages, which can then no longer be edited.
 */
final readonly class FlattenForm implements Stage
{
    #[Override]
    public function operation(): Operation
    {
        return Operation::FlattenForm;
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
