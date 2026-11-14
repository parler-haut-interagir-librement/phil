<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\State;

/**
 * What the address and the component say of a table, as they say it: nothing in it has been checked yet.
 */
final readonly class TableState
{
    /**
     * @param array<array-key, mixed> $filters
     * @param array<array-key, mixed> $columns
     * @param array<array-key, mixed> $selected
     * @param array<array-key, mixed> $preserved the other parameters of the address, kept in its links
     * @param list<string>            $errors    what stopped the edit under way
     * @param string|null             $draft     the value of that edit, when it is to be shown again
     */
    public function __construct(
        public string $sort = '',
        public int $page = 1,
        public int $size = 0,
        public array $filters = [],
        public array $columns = [],
        public array $selected = [],
        public string $confirm = '',
        public string $edit = '',
        public string $row = '',
        public string $urlPrefix = '',
        public array $preserved = [],
        public array $errors = [],
        public ?string $draft = null,
    ) {
    }
}
