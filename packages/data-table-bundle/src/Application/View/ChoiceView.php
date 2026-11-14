<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * An option of a control.
 */
final readonly class ChoiceView
{
    public function __construct(
        public string $value,
        public string $label,
    ) {
    }
}
