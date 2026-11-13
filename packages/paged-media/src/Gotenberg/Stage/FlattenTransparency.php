<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;

/**
 * Replaces transparent content with opaque content, for printers and archival formats that do not take transparency.
 */
final readonly class FlattenTransparency implements Stage
{
    #[Override]
    public function operation(): Operation
    {
        return Operation::FlattenTransparency;
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
