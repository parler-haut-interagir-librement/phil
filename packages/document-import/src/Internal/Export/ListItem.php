<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

/**
 * An item of a list to write: its runs, whether it is checked when the list is a checklist, and its own items.
 *
 * @internal
 */
final readonly class ListItem
{
    /**
     * @param list<Run>      $runs
     * @param list<ListItem> $items
     */
    public function __construct(
        public array $runs,
        public ?bool $checked = null,
        public array $items = [],
    ) {
    }
}
