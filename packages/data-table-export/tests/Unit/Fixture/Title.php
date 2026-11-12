<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Tests\Unit\Fixture;

use Override;
use Stringable;

final readonly class Title implements Stringable
{
    public function __construct(
        private string $title,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->title;
    }
}
