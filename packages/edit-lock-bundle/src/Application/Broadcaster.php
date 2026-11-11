<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use DateTimeImmutable;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Document;

/**
 * Tells the other writers of a resource what changed: who edits which part, a block saved, a block removed.
 */
interface Broadcaster
{
    /**
     * The leases active on a resource, after one of them changed.
     *
     * @param list<Lease> $leases
     */
    public function presence(string $resource, array $leases, DateTimeImmutable $now): void;

    /**
     * A block saved by its holder: `$block` is a document of that one block, in canonical form; `$list` the list it
     * is in, `root` or a nested list's key; `$after` the block before it in that list, `null` when it comes first.
     */
    public function saved(string $resource, Holder $holder, Document $block, string $list, ?string $after): void;

    public function removed(string $resource, Holder $holder, string $blockId): void;
}
