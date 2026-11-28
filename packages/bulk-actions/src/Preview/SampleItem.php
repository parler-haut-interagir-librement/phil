<?php

declare(strict_types=1);

namespace Phil\BulkActions\Preview;

/**
 * One item of the preview's sample: its label, whether the person may change it, and its old and new values.
 */
final readonly class SampleItem
{
    /**
     * @param list<ValueChange> $changes one per field of the change set
     */
    public function __construct(
        public string $item,
        public string $label,
        public bool $granted,
        public array $changes,
    ) {
    }
}
