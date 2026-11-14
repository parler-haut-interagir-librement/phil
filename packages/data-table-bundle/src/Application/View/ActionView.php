<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * An action offered.
 */
final readonly class ActionView
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $destructive,
        public ?string $href = null,
    ) {
    }
}
