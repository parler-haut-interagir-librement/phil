<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * A row.
 */
final readonly class RowView
{
    /**
     * @param string           $label   what names the row: the text of its first cell
     * @param list<CellView>   $cells
     * @param list<ActionView> $actions
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $cells,
        public array $actions,
        public bool $selected,
    ) {
    }
}
