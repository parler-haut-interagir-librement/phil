<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

use Phil\DataTableBundle\Application\Query\Sort;

/**
 * A table as it was declared and checked by `TableBuilder`. Its labels are translation keys of its domain.
 */
final readonly class TableDefinition
{
    /**
     * @param array<string, Column> $columns   by name, in their order
     * @param array<string, Filter> $filters   by name, in their order
     * @param array<string, Action> $actions   by name, in their order
     * @param list<Sort>            $sorts     the sort by default, by column names
     * @param list<int>             $pageSizes the sizes offered, the first by default
     * @param Export|null           $export    the export offered, none when null
     */
    public function __construct(
        public string $name,
        public string $caption,
        public string $domain,
        public string $identifier,
        public ?string $version,
        public ?string $attribute,
        public array $columns,
        public array $filters,
        public array $actions,
        public array $sorts,
        public array $pageSizes,
        public int $maxOffset,
        public ?Export $export = null,
    ) {
    }
}
