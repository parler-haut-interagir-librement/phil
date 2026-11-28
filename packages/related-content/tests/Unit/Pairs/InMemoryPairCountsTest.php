<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit\Pairs;

use Phil\RelatedContent\Exception\InvalidItem;
use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Pairs\InMemoryPairCounts;
use Phil\RelatedContent\Pairs\PairCount;
use Phil\RelatedContent\Pairs\Signal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryPairCounts::class)]
#[CoversClass(PairCount::class)]
final class InMemoryPairCountsTest extends TestCase
{
    public function testAPairBoughtTogetherIsTheSameBothWays(): void
    {
        $counts = new InMemoryPairCounts();
        $counts->add(Signal::BoughtTogether, 'a', 'b');
        $counts->add(Signal::BoughtTogether, 'b', 'a', 2);

        self::assertEquals([new PairCount('b', 3)], $counts->top(Signal::BoughtTogether, 'a', 10));
        self::assertEquals([new PairCount('a', 3)], $counts->top(Signal::BoughtTogether, 'b', 10));
        self::assertSame([], $counts->top(Signal::ViewedNext, 'a', 10));
    }

    public function testAViewIsCountedFromTheFirstToTheSecondOnly(): void
    {
        $counts = new InMemoryPairCounts();
        $counts->add(Signal::ViewedNext, 'a', 'b');

        self::assertEquals([new PairCount('b', 1)], $counts->top(Signal::ViewedNext, 'a', 10));
        self::assertSame([], $counts->top(Signal::ViewedNext, 'b', 10));
    }

    public function testTheHighestCountsComeFirstThenTheIdentifiers(): void
    {
        $counts = new InMemoryPairCounts();
        $counts->add(Signal::BoughtTogether, 'a', 'c', 2);
        $counts->add(Signal::BoughtTogether, 'a', '10', 5);
        $counts->add(Signal::BoughtTogether, 'a', 'b', 2);
        $counts->add(Signal::BoughtTogether, 'a', 'd', 1);

        self::assertEquals(
            [new PairCount('10', 5), new PairCount('b', 2), new PairCount('c', 2)],
            $counts->top(Signal::BoughtTogether, 'a', 3),
        );
        self::assertSame('10', $counts->top(Signal::BoughtTogether, 'a', 1)[0]->other);
        self::assertSame([], $counts->top(Signal::BoughtTogether, 'a', 0));
        self::assertSame([], $counts->top(Signal::BoughtTogether, 'a', -1));
    }

    public function testAProductIsNeverPairedWithItself(): void
    {
        $counts = new InMemoryPairCounts();
        $counts->add(Signal::BoughtTogether, 'a', 'a');

        self::assertSame([], $counts->top(Signal::BoughtTogether, 'a', 10));
    }

    public function testARetiredItemLeavesEveryPair(): void
    {
        $counts = new InMemoryPairCounts();
        $counts->add(Signal::BoughtTogether, 'a', 'b');
        $counts->add(Signal::BoughtTogether, 'a', 'c');
        $counts->add(Signal::ViewedNext, 'c', 'a');
        $counts->add(Signal::ViewedNext, 'c', 'b');
        $counts->forget('a');

        self::assertSame([], $counts->top(Signal::BoughtTogether, 'a', 10));
        self::assertSame([], $counts->top(Signal::BoughtTogether, 'b', 10));
        self::assertSame([], $counts->top(Signal::BoughtTogether, 'c', 10));
        self::assertEquals([new PairCount('b', 1)], $counts->top(Signal::ViewedNext, 'c', 10));
    }

    public function testResetEmptiesTheCounts(): void
    {
        $counts = new InMemoryPairCounts();
        $counts->add(Signal::BoughtTogether, 'a', 'b');
        $counts->reset();

        self::assertSame([], $counts->top(Signal::BoughtTogether, 'a', 10));
    }

    public function testACountIsPositive(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('count is 0; it must lie between 1 and');

        new InMemoryPairCounts()->add(Signal::BoughtTogether, 'a', 'b', 0);
    }

    public function testTheSourceIsValidated(): void
    {
        $this->expectException(InvalidItem::class);

        new InMemoryPairCounts()->add(Signal::BoughtTogether, '', 'b');
    }

    public function testTheTargetIsValidated(): void
    {
        $this->expectException(InvalidItem::class);

        new InMemoryPairCounts()->add(Signal::BoughtTogether, 'a', '');
    }
}
