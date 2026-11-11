<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit\Application;

use DateTimeImmutable;
use LogicException;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Application\Captured;
use Phil\TrashBundle\Application\Event\ResourceRestored;
use Phil\TrashBundle\Application\Event\ResourceTrashed;
use Phil\TrashBundle\Application\Event\TrashItemPurged;
use Phil\TrashBundle\Application\Trash;
use Phil\TrashBundle\Application\TrashHandler;
use Phil\TrashBundle\Domain\Exception\ParentMissing;
use Phil\TrashBundle\Domain\Exception\UnknownResource;
use Phil\TrashBundle\Domain\Model\TrashItem;
use Phil\TrashBundle\Tests\Unit\Fixtures\InMemoryTrash;
use Phil\TrashBundle\Tests\Unit\Fixtures\Page;
use Phil\TrashBundle\Tests\Unit\Fixtures\PageHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;
use Symfony\Component\Clock\NativeClock;

#[CoversClass(Trash::class)]
#[CoversClass(Captured::class)]
#[CoversClass(ResourceTrashed::class)]
#[CoversClass(ResourceRestored::class)]
#[CoversClass(TrashItemPurged::class)]
final class TrashTest extends TestCase
{
    private InMemoryTrash $items;

    private PageHandler $pages;

    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->items = new InMemoryTrash();
        $this->pages = new PageHandler();
        $this->pages->pages = ['1' => new Page('1', 'Home'), '42' => new Page('42', 'About us', '1')];
    }

    public function testAResourceGoesToTheTrashAndComesBack(): void
    {
        $trash = $this->trash('2026-11-03 12:00');
        $item = $trash->trash($this->pages->page('42'), 'sub-1');

        self::assertArrayNotHasKey('42', $this->pages->pages);
        self::assertCount(1, $this->pages->pages);
        self::assertSame([$item], array_values($this->items->items));
        self::assertSame(['page:42', 'About us', 'page:1', 'sub-1', '2026-11-03T12:00:00+00:00', '2026-12-03T12:00:00+00:00'], [$item->resource()->toString(), $item->title(), $item->parent()?->toString(), $item->trashedBy(), $item->trashedAt()->format('c'), $item->purgeAfter()->format('c')]);
        self::assertInstanceOf(ResourceTrashed::class, $this->dispatched[0]);
        self::assertSame($item, $this->dispatched[0]->item);

        $page = $trash->restore($item);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame(['42', 'About us', '1'], [$page->id, $page->title, $page->parent]);
        self::assertSame([], $this->items->items);
        self::assertInstanceOf(ResourceRestored::class, $this->dispatched[1]);
        self::assertSame([$item, $page], [$this->dispatched[1]->item, $this->dispatched[1]->resource]);
    }

    public function testAResourceWhoseParentIsGoneComesBackUnderAnother(): void
    {
        $trash = $this->trash('2026-11-03 12:00');
        $item = $trash->trash($this->pages->page('42'));
        $this->pages->forget('1');
        $this->pages->pages['2'] = new Page('2', 'Company');

        try {
            $trash->restore($item);
            self::fail('A resource came back under a parent that is gone.');
        } catch (ParentMissing $parentMissing) {
            self::assertSame(['page:42', 'page:1'], [$parentMissing->resource->toString(), $parentMissing->parent->toString()]);
            self::assertSame([$item], array_values($this->items->items));
        }

        $page = $trash->restore($item, ResourceReference::of('page', '2'));
        self::assertInstanceOf(Page::class, $page);
        self::assertSame('2', $page->parent);

        $root = $trash->trash($this->pages->page('2'));
        self::assertNull($root->parent());
        self::assertInstanceOf(Page::class, $trash->restore($root));
    }

    public function testRetentionIsByKind(): void
    {
        self::assertSame('2026-11-10T12:00:00+00:00', $this->trash('2026-11-03 12:00', 7)->trash($this->pages->page('1'))->purgeAfter()->format('c'));
        self::assertSame('2027-02-01T12:00:00+00:00', $this->trash('2026-11-03 12:00', 7, ['page' => 90])->trash($this->pages->page('42'))->purgeAfter()->format('c'));
    }

    public function testTheTrashIsPurgedOnceRetentionIsOver(): void
    {
        $this->trash('2026-11-03 12:00')->trash($this->pages->page('42'));
        $this->trash('2026-11-04 12:00')->trash($this->pages->page('1'));
        $other = TrashItem::of(ResourceReference::of('post', '9'), 'Old post', [], null, new DateTimeImmutable('2026-01-01 UTC'), null, new DateTimeImmutable('2026-02-01 UTC'));
        $this->items->save($other);

        self::assertSame(0, $this->trash('2026-11-03 13:00')->purge(0));
        self::assertSame(1, $this->trash('2026-12-03 12:00')->purge(1));
        self::assertSame(1, $this->trash('2026-12-03 12:00')->purge(10));
        self::assertSame(['42'], $this->pages->purged);
        self::assertCount(1, $this->items->items);
        $purged = \array_slice($this->dispatched, 2);
        self::assertCount(2, $purged);
        self::assertInstanceOf(TrashItemPurged::class, $purged[0]);
        self::assertSame($other, $purged[0]->item);
    }

    public function testAnUnknownResourceIsRefused(): void
    {
        try {
            $this->trash('2026-11-03 12:00')->trash(new stdClass());
            self::fail('An unknown resource was trashed.');
        } catch (UnknownResource $unknownResource) {
            self::assertSame('No trash handler supports stdClass: register a TrashHandler for it.', $unknownResource->getMessage());
        }

        $item = TrashItem::of(ResourceReference::of('post', '9'), 'Post', [], null, new DateTimeImmutable(), null, new DateTimeImmutable('+1 day'));
        $this->expectException(UnknownResource::class);
        $this->expectExceptionMessageIsOrContains('No trash handler restores "post" resources');

        $this->trash('2026-11-03 12:00')->restore($item);
    }

    public function testWithoutClockOrDispatcher(): void
    {
        $item = new Trash($this->items, [$this->pages], new NativeClock())->trash($this->pages->page('42'));

        self::assertEqualsWithDelta(time(), $item->trashedAt()->getTimestamp(), 5);
        self::assertEqualsWithDelta(time() + 30 * 86_400, $item->purgeAfter()->getTimestamp(), 5);
        self::assertInstanceOf(Page::class, new Trash($this->items, [$this->pages], new NativeClock())->restore($item));
        self::assertSame(0, new Trash($this->items, [$this->pages], new NativeClock())->purge(10));
        $this->items->save(TrashItem::of(ResourceReference::of('page', '7'), 'Old', [], null, new DateTimeImmutable('2026-01-01 UTC'), null, new DateTimeImmutable('2026-02-01 UTC')));
        self::assertSame(1, new Trash($this->items, [$this->pages], new NativeClock())->purge(10));
    }

    /**
     * @param array<string, int> $retention
     */
    private function trash(string $now, int $days = 30, array $retention = []): Trash
    {
        $clock = new readonly class(new DateTimeImmutable($now.' UTC')) implements ClockInterface {
            public function __construct(
                private DateTimeImmutable $now,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
        $events = new readonly class($this) implements EventDispatcherInterface {
            public function __construct(
                private TrashTest $test,
            ) {
            }

            public function dispatch(object $event): object
            {
                $this->test->record($event);

                return $event;
            }
        };

        return new Trash($this->items, [new class implements TrashHandler {
            public function resourceKey(): string
            {
                return 'never';
            }

            public function supports(object $resource): bool
            {
                return false;
            }

            public function capture(object $resource): Captured
            {
                throw new LogicException();
            }

            public function remove(object $resource): void
            {
            }

            public function parentExists(ResourceReference $parent): bool
            {
                return false;
            }

            public function restore(TrashItem $item, ?ResourceReference $parent): object
            {
                throw new LogicException();
            }

            public function purge(TrashItem $item): void
            {
            }
        }, $this->pages], $clock, $days, $retention, $events);
    }

    public function record(object $event): void
    {
        $this->dispatched[] = $event;
    }
}
