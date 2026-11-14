<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * A cell: its text, already written for the locale, or what its block needs.
 */
final readonly class CellView
{
    /**
     * @param array<string, mixed>|object $row the row itself, for a block
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $header,
        public string $text,
        public mixed $value,
        public array|object $row,
        public ?string $href,
        public ?string $template,
        public ?string $block,
        public ?string $editHref,
        public ?EditView $edit,
    ) {
    }
}
