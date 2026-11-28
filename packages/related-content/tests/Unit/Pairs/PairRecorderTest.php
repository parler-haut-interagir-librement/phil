<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit\Pairs;

use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Pairs\InMemoryPairCounts;
use Phil\RelatedContent\Pairs\PairCount;
use Phil\RelatedContent\Pairs\PairRecorder;
use Phil\RelatedContent\Pairs\Signal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PairRecorder::class)]
final class PairRecorderTest extends TestCase
{
    public function testEachPairOfDistinctProductsIsCountedOnce(): void
    {
        $counts = new InMemoryPairCounts();
        $recorder = new PairRecorder($counts);

        self::assertSame(3, $recorder->order(['a', 'b', 'a', 'c']));
        self::assertEquals([new PairCount('b', 1), new PairCount('c', 1)], $counts->top(Signal::BoughtTogether, 'a', 10));
        self::assertEquals([new PairCount('a', 1), new PairCount('b', 1)], $counts->top(Signal::BoughtTogether, 'c', 10));
        self::assertSame(0, $recorder->order(['a']));
        self::assertSame(0, $recorder->order([]));
    }

    public function testNumericIdentifiersArePaired(): void
    {
        $counts = new InMemoryPairCounts();
        new PairRecorder($counts)->order(['1', '2']);

        self::assertEquals([new PairCount('2', 1)], $counts->top(Signal::BoughtTogether, '1', 10));
    }

    public function testPastTheBoundTheFirstProductsArePaired(): void
    {
        $counts = new InMemoryPairCounts();

        self::assertSame(1, new PairRecorder($counts, 2)->order(['a', 'b', 'c']));
        self::assertSame([], $counts->top(Signal::BoughtTogether, 'c', 10));
        self::assertSame(19_900, new PairRecorder(new InMemoryPairCounts(), 200)->order(array_map(strval(...), range(1, 201))));
    }

    public function testAViewNextIsCounted(): void
    {
        $counts = new InMemoryPairCounts();
        new PairRecorder($counts)->viewedNext('a', 'b');

        self::assertEquals([new PairCount('b', 1)], $counts->top(Signal::ViewedNext, 'a', 10));
        self::assertSame([], $counts->top(Signal::BoughtTogether, 'a', 10));
    }

    public function testTheBoundLiesBetweenTwoAndTwoHundred(): void
    {
        foreach ([1, 201] as $bound) {
            try {
                new PairRecorder(new InMemoryPairCounts(), $bound);
                self::fail('No exception for '.$bound.'.');
            } catch (InvalidRule $exception) {
                self::assertSame('maxItemsPerOrder is '.$bound.'; it must lie between 2 and 200.', $exception->getMessage());
            }
        }
    }

    public function testAnOrderPairsFiftyProductsByDefault(): void
    {
        $recorder = new PairRecorder(new InMemoryPairCounts());

        self::assertSame(50 * 49 / 2, $recorder->order(array_map(static fn (int $n): string => 'p'.$n, range(1, 60))));
    }
}
