<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Override;
use Phil\EditLockBundle\Application\BlockStore;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Block;

final class RecordingBlockStore implements BlockStore
{
    /** @var list<string> */
    public array $kept = [];

    #[Override]
    public function save(string $resource, Block $block, string $list, ?string $after, Holder $holder): void
    {
        $this->kept[] = 'save '.$resource.' '.$block->id.' '.$block->type.' '.$list.' '.($after ?? '-').' '.$holder->id;
    }

    #[Override]
    public function remove(string $resource, string $blockId, Holder $holder): void
    {
        $this->kept[] = 'remove '.$resource.' '.$blockId.' '.$holder->id;
    }
}
