<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

/**
 * What an edit starts from: the value as its control writes it, and the row's version.
 */
final readonly class EditStart
{
    public function __construct(
        public string $value,
        public ?string $version,
    ) {
    }
}
