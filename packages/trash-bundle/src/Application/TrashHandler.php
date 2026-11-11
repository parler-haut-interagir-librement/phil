<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Application;

use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Domain\Model\TrashItem;

/**
 * How one kind of resource goes to the trash and comes back. The application writes one per kind; the bundle finds
 * them by autoconfiguration.
 */
interface TrashHandler
{
    /**
     * The kind this handler keeps: `page`, `product`.
     */
    public function resourceKey(): string;

    public function supports(object $resource): bool;

    /**
     * What to keep of the resource before it is removed.
     */
    public function capture(object $resource): Captured;

    /**
     * Removes the resource's rows — its own and its children's — from the unit of work, without flushing: the trash
     * flushes once, after the handler, so that the resource leaves its tables and enters the trash together. Its files
     * stay where they are, for `restore()` needs them; `purge()` deletes them.
     */
    public function remove(object $resource): void;

    /**
     * Whether a parent a resource may come back under still exists.
     */
    public function parentExists(ResourceReference $parent): bool;

    /**
     * Brings the resource back, under this parent, and returns it.
     */
    public function restore(TrashItem $item, ?ResourceReference $parent): object;

    /**
     * Forgets for good what the resource left outside the trash and its tables, if anything: its files, once its
     * retention is over.
     */
    public function purge(TrashItem $item): void;
}
