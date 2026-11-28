<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\RelatedContent\Reason;
use Phil\RelatedContent\Recommendation;
use Phil\RelatedContent\RelatedSet;
use Phil\RelatedContent\Store\InMemoryRelatedStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryRelatedStore::class)]
final class InMemoryRelatedStoreTest extends TestCase
{
    private function set(string $item, string ...$neighbours): RelatedSet
    {
        return new RelatedSet($item, array_map(static fn (string $id): Recommendation => new Recommendation($id, 100.0, [Reason::Indices]), array_values($neighbours)), new DateTimeImmutable('2026-11-21'));
    }

    public function testASetIsKeptAndFoundAgain(): void
    {
        $store = new InMemoryRelatedStore();
        $set = $this->set('a', 'b', 'c');
        $store->save($set);

        self::assertSame($set, $store->find('a'));
        self::assertNull($store->find('b'));
        self::assertSame(['a'], $store->pointingAt('b', 10));
        self::assertSame([], $store->pointingAt('a', 10));
    }

    public function testSavingReplacesTheReverseIndex(): void
    {
        $store = new InMemoryRelatedStore();
        $store->save($this->set('a', 'b', 'c'));
        $store->save($this->set('d', 'b'));
        $store->save($this->set('a', 'c'));

        self::assertSame(['d'], $store->pointingAt('b', 10));
        self::assertSame(['a'], $store->pointingAt('c', 10));
    }

    public function testRemovingLeavesTheOthers(): void
    {
        $store = new InMemoryRelatedStore();
        $store->save($this->set('a', 'b'));
        $store->save($this->set('c', 'b'));
        $store->remove('a');
        $store->remove('unknown');

        self::assertNull($store->find('a'));
        self::assertSame(['c'], $store->pointingAt('b', 10));

        $store->remove('c');

        self::assertSame([], $store->pointingAt('b', 10));
    }

    public function testPointingAtIsBounded(): void
    {
        $store = new InMemoryRelatedStore();
        $store->save($this->set('1', 'x'));
        $store->save($this->set('2', 'x'));
        $store->save($this->set('3', 'x'));

        self::assertSame(['1', '2'], $store->pointingAt('x', 2));
        self::assertSame([], $store->pointingAt('x', 0));
        self::assertSame([], $store->pointingAt('x', -1));
    }

    public function testResetEmptiesTheStore(): void
    {
        $store = new InMemoryRelatedStore();
        $store->save($this->set('a', 'b'));
        $store->reset();

        self::assertNull($store->find('a'));
        self::assertSame([], $store->pointingAt('b', 10));
    }
}
