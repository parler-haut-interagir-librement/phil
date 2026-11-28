<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Run\ItemContext;

/**
 * An action without fields, destructive: archives products.
 */
final class ArchiveProducts implements BulkAction
{
    public function name(): string
    {
        return 'archive';
    }

    public function attribute(): string
    {
        return 'PRODUCT_ARCHIVE';
    }

    public function isDestructive(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [];
    }

    public function label(object $item): string
    {
        \assert($item instanceof Product);

        return $item->name;
    }

    public function values(object $item): array
    {
        return [];
    }

    public function apply(object $item, ChangeSet $changes, ItemContext $context): void
    {
        \assert($item instanceof Product);
        $item->status = 'archived';
    }
}
