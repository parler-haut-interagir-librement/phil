<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\ItemRefused;
use Phil\BulkActions\Exception\VersionConflict;
use Phil\BulkActions\Run\ItemContext;

/**
 * One bulk action of a target, written by the application: what it is called, which voter attribute guards each item,
 * which fields it may set, and how it changes one item.
 */
interface BulkAction
{
    /**
     * The name a request uses, unique within the target.
     */
    public function name(): string;

    /**
     * The voter attribute asked on each item, such as "PRODUCT_EDIT".
     */
    public function attribute(): string;

    /**
     * Whether the action removes or overwrites something a person cannot easily restore: the preview warns of it.
     */
    public function isDestructive(): bool;

    /**
     * The fields a change set may name; an empty list for an action without fields.
     *
     * @return list<string>
     */
    public function fields(): array;

    /**
     * What the preview shows to name an item.
     */
    public function label(object $item): string;

    /**
     * The current value of each field, for the preview's old and new values.
     *
     * @return array<string, scalar|null>
     */
    public function values(object $item): array;

    /**
     * Changes one item, inside the transaction of its chunk. Effects outside the database, such as a mail, are recorded
     * in an outbox under the context's idempotency key, never sent from here.
     *
     * @throws ItemRefused     when this item cannot take the change, with the reason
     * @throws VersionConflict when the item changed since it was read
     */
    public function apply(object $item, ChangeSet $changes, ItemContext $context): void;
}
