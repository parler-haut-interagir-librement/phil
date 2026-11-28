<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

/**
 * The outcome of one item, with the translation key of its reason when the action or the library gave one.
 */
final readonly class ItemResult
{
    public function __construct(
        public string $item,
        public ItemOutcome $outcome,
        public ?string $reason = null,
    ) {
    }
}
