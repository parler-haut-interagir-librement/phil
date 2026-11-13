<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;

/**
 * Converts every colour of a PDF to one colour space.
 */
final readonly class ConvertColors implements Stage
{
    public function __construct(public ColorTarget $target)
    {
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::ConvertColors;
    }

    #[Override]
    public function options(): array
    {
        return ['target' => $this->target->value];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
