<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Application;

use Phil\EditLockBundle\Application\BlockSaves;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Application\Exception\EditRefused;
use Phil\EditLockBundle\Application\Exception\InvalidBlock;
use Phil\EditLockBundle\Application\Exception\LeaseLost;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\EditLockBundle\Infrastructure\Memory\InMemoryLeaseRepository;
use Phil\EditLockBundle\Tests\Unit\Fixture\Policy;
use Phil\EditLockBundle\Tests\Unit\Fixture\RecordingBlockStore;
use Phil\EditLockBundle\Tests\Unit\Fixture\RecordingBroadcaster;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(BlockSaves::class)]
#[CoversClass(InvalidBlock::class)]
final class BlockSavesTest extends TestCase
{
    private InMemoryLeaseRepository $leases;

    private EditLocks $locks;

    private RecordingBroadcaster $told;

    private RecordingBlockStore $kept;

    private BlockSaves $blocks;

    private Holder $bob;

    protected function setUp(): void
    {
        $this->told = new RecordingBroadcaster();
        $this->kept = new RecordingBlockStore();
        $this->leases = new InMemoryLeaseRepository();
        $this->locks = new EditLocks($this->leases, new MockClock('2026-11-06 10:00:00 UTC'), 30, new Policy());
        $this->blocks = new BlockSaves($this->locks, $this->told, new Ujo(), $this->kept);
        $this->bob = Holder::of('bob', 'Bob');
    }

    public function testTheHolderOfABlockSavesIt(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);
        $block = $this->blocks->save('page', $this->bob, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => '<strong>Hi</strong>']], 'root', 'b0');

        self::assertSame(['b1', 'paragraph', ['text' => '<b>Hi</b>']], [$block->id, $block->type, $block->data]);
        self::assertSame(['save page b1 paragraph root b0 bob'], $this->kept->kept);
        $first = $this->told->told;

        $this->blocks->save('page', $this->bob, ['id' => 'b1', 'type' => 'header', 'data' => ['text' => 'T', 'level' => 2]]);
        $this->blocks->save('page', $this->bob, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 't9:12');
        $this->blocks->save('page', $this->bob, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 't9');
        $this->blocks->remove('page', $this->bob, 'b1');

        self::assertSame([
            'save page b1 paragraph root b0 bob',
            'save page b1 header root - bob',
            'save page b1 paragraph t9:12 - bob',
            'save page b1 paragraph t9 - bob',
            'remove page b1 bob',
        ], $this->kept->kept);
        self::assertSame(['saved page by bob in root after b0 {"blocks":[{"id":"b1","type":"paragraph","data":{"text":"<b>Hi</b>"}}]}'], $first);
        self::assertSame([
            'saved page by bob in root after - {"blocks":[{"id":"b1","type":"header","data":{"text":"T","level":2}}]}',
            'saved page by bob in t9:12 after - {"blocks":[{"id":"b1","type":"paragraph","data":{"text":"x"}}]}',
            'saved page by bob in t9 after - {"blocks":[{"id":"b1","type":"paragraph","data":{"text":"x"}}]}',
            'removed page by bob b1',
        ], \array_slice($this->told->told, 1));
    }

    public function testTheWholeResourceCoversEveryBlock(): void
    {
        $this->locks->acquire(LeaseKey::of('page'), $this->bob);
        $blocks = new BlockSaves($this->locks, $this->told);

        self::assertSame('any', $blocks->save('page', $this->bob, ['id' => 'any', 'type' => 'paragraph', 'data' => ['text' => 'x']])->id);
        $blocks->remove('page', $this->bob, 'other');
        self::assertSame(['removed page by bob other'], \array_slice($this->told->told, 1));
    }

    public function testOnlyTheHolderSaves(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'b1'), Holder::of('cy', 'Cy'));

        foreach ([
            function (): void {
                $this->blocks->save('page', $this->bob, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']]);
            },
            function (): void {
                $this->blocks->save('page', $this->bob, ['id' => 'b2', 'type' => 'paragraph', 'data' => ['text' => 'x']]);
            },
            function (): void {
                $this->blocks->remove('page', $this->bob, 'b1');
            },
        ] as $write) {
            try {
                $write();
                self::fail('A block was written without its lease.');
            } catch (LeaseLost) {
            }
        }

        self::assertSame([[], []], [$this->kept->kept, $this->told->told]);

        try {
            $this->blocks->remove('page', $this->bob, 'not an id');
            self::fail('A block without an identifier.');
        } catch (InvalidLease) {
        }
    }

    public function testAHolderNoLongerAllowedToEditWritesNothing(): void
    {
        $this->locks->acquire(LeaseKey::of('page'), $this->bob);
        $revoked = new BlockSaves(new EditLocks($this->leases, new MockClock('2026-11-06 10:00:05 UTC'), 30, new Policy(['ada', 'cy'])), $this->told, new Ujo(), $this->kept);

        foreach ([
            static function () use ($revoked): void {
                $revoked->save('page', Holder::of('bob', 'Bob'), ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']]);
            },
            static function () use ($revoked): void {
                $revoked->remove('page', Holder::of('bob', 'Bob'), 'b1');
            },
        ] as $write) {
            try {
                $write();
                self::fail('A holder the policy refuses wrote a block.');
            } catch (EditRefused $editRefused) {
                self::assertSame('Editing "page" is not allowed.', $editRefused->getMessage());
            }
        }

        self::assertSame([[], []], [$this->kept->kept, $this->told->told]);
    }

    public function testABadBlockIsRefused(): void
    {
        $this->locks->acquire(LeaseKey::of('page'), $this->bob);
        $strict = new BlockSaves($this->locks, $this->told, new Ujo(new Normalizer(BlockTypeRegistry::default()->without('header'))));

        foreach ([
            [$this->blocks, 'nope', 'root', null, 'The block is refused: The UJO document is invalid at blocks[0]: a block must be an object.'],
            [$this->blocks, ['id' => 'b1', 'type' => 'paragraph'], 'root', null, 'The block is refused: '],
            [$this->blocks, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 1]], 'root', null, 'The block is refused: '],
            [$strict, ['id' => 'b1', 'type' => 'header', 'data' => ['text' => 'T', 'level' => 2]], 'root', null, 'The block is refused: '],
            [$this->blocks, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 'a:b', null, 'The list'],
            [$this->blocks, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 'b1:1234', null, 'The list'],
            [$this->blocks, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 'b 1', null, 'The list'],
            [$this->blocks, ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']], 'root', 'b 0', 'The block before'],
        ] as [$blocks, $block, $list, $after, $message]) {
            try {
                $blocks->save('page', $this->bob, $block, $list, $after);
                self::fail($message);
            } catch (InvalidBlock $invalidBlock) {
                self::assertStringStartsWith($message, $invalidBlock->getMessage());
                self::assertSame(0, $invalidBlock->getCode());
                self::assertSame(str_starts_with($message, 'The block is refused: '), $invalidBlock->getPrevious() instanceof UjoException);
            }
        }

        self::assertSame([], $this->told->told);
    }
}
