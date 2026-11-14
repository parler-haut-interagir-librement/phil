<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * A column in the list of those a viewer can show or hide.
 */
final readonly class ColumnOptionView
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $visible,
    ) {
    }
}
