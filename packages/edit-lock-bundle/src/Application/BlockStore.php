<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use Phil\EditLockBundle\Domain\ValueObject\Fence;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Block;

/**
 * Where the application keeps a block saved by its holder, in the draft of the resource for instance, so that a
 * writer who opens it later reads the latest blocks. Called before the save is told to the other writers.
 *
 * Each call runs within the claim on the resource that checked the holder's lease: no other claim runs until it
 * returns, and with the Doctrine store a write on the same connection is part of that claim's transaction. A store on
 * another connection, or one that may apply a write later, keeps the fence's number with the block and writes only
 * when the new number is greater, by one conditional `UPDATE` whose row count it checks, so that a late write never
 * replaces a newer one.
 */
interface BlockStore
{
    /**
     * @param string      $list  the list the block is in: `root`, or the key of a nested list
     * @param string|null $after the block before it in that list, `null` when it comes first
     * @param Fence       $fence the fencing token of the write: its key, its holder and the number of its claim
     */
    public function save(string $resource, Block $block, string $list, ?string $after, Holder $holder, Fence $fence): void;

    /**
     * @param Fence $fence the fencing token of the removal: its key, its holder and the number of its claim
     */
    public function remove(string $resource, string $blockId, Holder $holder, Fence $fence): void;
}
