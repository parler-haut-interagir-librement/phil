<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * Everything a template needs to draw a table, already translated and formatted.
 */
final readonly class TableView
{
    /**
     * @param list<HeaderView>                         $headers
     * @param list<RowView>                            $rows
     * @param list<FilterView>                         $filters
     * @param list<ColumnOptionView>                   $columnOptions
     * @param list<int>                                $pageSizes
     * @param list<ActionView>                         $bulkActions
     * @param list<array{name: string, value: string}> $hiddenFields
     * @param bool                                     $editLeft      whether the row being edited is no longer on the page
     */
    public function __construct(
        public string $name,
        public string $prefix,
        public string $formId,
        public string $caption,
        public array $headers,
        public array $rows,
        public array $filters,
        public array $columnOptions,
        public PaginationView $pagination,
        public array $pageSizes,
        public int $pageSize,
        public array $bulkActions,
        public ?ConfirmationView $confirmation,
        public int $total,
        public array $hiddenFields,
        public bool $hasRowActions,
        public bool $filtered,
        public string $clearHref,
        public string $currentHref,
        public bool $editLeft,
    ) {
    }

    /**
     * How many columns a row spans.
     */
    public function width(): int
    {
        return \count($this->headers) + ([] === $this->bulkActions ? 0 : 1) + ($this->hasRowActions ? 1 : 0);
    }
}
