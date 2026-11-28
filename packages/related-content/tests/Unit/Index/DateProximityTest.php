<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit\Index;

use DateTimeImmutable;
use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Index\DateProximity;
use Phil\RelatedContent\Item;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateProximity::class)]
#[CoversClass(InvalidRule::class)]
final class DateProximityTest extends TestCase
{
    private function on(string $date): Item
    {
        return new Item('item-'.$date, date: new DateTimeImmutable($date));
    }

    public function testTheWeightFallsInAStraightLineOverTheWindow(): void
    {
        $index = new DateProximity(40.0, 10);

        self::assertSame(40.0, $index->score($this->on('2026-11-10'), $this->on('2026-11-10')));
        self::assertSame(20.0, $index->score($this->on('2026-11-10'), $this->on('2026-11-15')));
        self::assertSame(20.0, $index->score($this->on('2026-11-15'), $this->on('2026-11-10')));
        self::assertEqualsWithDelta(36.0, $index->score($this->on('2026-11-10'), $this->on('2026-11-11')), 1e-9);
        self::assertSame(0.0, $index->score($this->on('2026-11-10'), $this->on('2026-11-20')));
        self::assertSame(0.0, $index->score($this->on('2026-11-10'), $this->on('2027-11-20')));
        self::assertSame(40.0, $index->weight);
        self::assertSame(10, $index->windowDays);
    }

    public function testHoursCount(): void
    {
        $index = new DateProximity(48.0, 1);

        self::assertEqualsWithDelta(24.0, $index->score($this->on('2026-11-10 00:00'), $this->on('2026-11-10 12:00')), 1e-9);
    }

    public function testNothingWithoutADate(): void
    {
        $index = new DateProximity(40.0);

        self::assertSame(0.0, $index->score(new Item('a'), $this->on('2026-11-10')));
        self::assertSame(0.0, $index->score($this->on('2026-11-10'), new Item('a')));
        self::assertSame(365, $index->windowDays);
    }

    public function testTheWidestWindowIsAccepted(): void
    {
        self::assertSame(36_500, new DateProximity(0.0, 36_500)->windowDays);
    }

    /**
     * @return iterable<string, array{float, int, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'negative weight' => [-1.0, 10, 'date weight is -1; it must lie between 0 and '];
        yield 'infinite weight' => [\INF, 10, 'date weight is INF'];
        yield 'not a number' => [\NAN, 10, 'date weight is NAN'];
        yield 'empty window' => [1.0, 0, 'windowDays is 0; it must lie between 1 and 36500.'];
        yield 'window too wide' => [1.0, 36_501, 'windowDays is 36501; it must lie between 1 and 36500.'];
    }

    #[DataProvider('invalidValues')]
    public function testAValueOutOfBoundsIsRefused(float $weight, int $window, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DateProximity($weight, $window);
    }
}
