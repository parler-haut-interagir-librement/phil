<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Override;
use Phil\EditLockBundle\Application\BlockStore;
use Phil\EditLockBundle\Domain\ValueObject\Fence;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Block;

/**
 * A block store as an application writes one, on the connection of the leases: a block's text and the number of the
 * fence it was written under, replaced only by a write with a greater number, by an `UPDATE` whose row count says
 * whether it applied. The recording store runs first, before the block is written.
 */
final readonly class DbalBlockStore implements BlockStore
{
    public function __construct(
        private Connection $connection,
        private RecordingBlockStore $inner,
    ) {
    }

    #[Override]
    public function save(string $resource, Block $block, string $list, ?string $after, Holder $holder, Fence $fence): void
    {
        $this->inner->save($resource, $block, $list, $after, $holder, $fence);
        $text = $block->data['text'] ?? '';
        $body = \is_string($text) ? $text : '';

        if (0 === $this->connection->executeStatement('UPDATE edit_lock_test_block SET body = ?, fence = ? WHERE id = ? AND fence < ?', [$body, $fence->number, $block->id, $fence->number])) {
            $this->connection->executeStatement('INSERT INTO edit_lock_test_block (id, body, fence) SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM edit_lock_test_block WHERE id = ?)', [$block->id, $body, $fence->number, $block->id]);
        }
    }

    #[Override]
    public function remove(string $resource, string $blockId, Holder $holder, Fence $fence): void
    {
        $this->inner->remove($resource, $blockId, $holder, $fence);
        $this->connection->executeStatement('DELETE FROM edit_lock_test_block WHERE id = ? AND fence < ?', [$blockId, $fence->number]);
    }
}
