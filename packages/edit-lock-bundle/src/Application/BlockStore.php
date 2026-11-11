<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Block;

/**
 * Where the application keeps a block saved by its holder, in the draft of the resource for instance, so that a
 * writer who opens it later reads the latest blocks. Called before the save is told to the other writers.
 */
interface BlockStore
{
    /**
     * @param string      $list  the list the block is in: `root`, or the key of a nested list
     * @param string|null $after the block before it in that list, `null` when it comes first
     */
    public function save(string $resource, Block $block, string $list, ?string $after, Holder $holder): void;

    public function remove(string $resource, string $blockId, Holder $holder): void;
}
