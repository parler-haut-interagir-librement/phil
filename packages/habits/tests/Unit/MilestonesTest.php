<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit;

use Phil\Habits\Exception\InvalidHabit;
use Phil\Habits\Milestones;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Milestones::class)]
final class MilestonesTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $milestones = new Milestones();

        self::assertSame([3, 7, 14, 30, 60, 100, 200, 365], $milestones->streaks);
        self::assertSame([25, 50, 75, 100], $milestones->strengths);
    }

    public function testMilestonesAreSortedWithoutDuplicates(): void
    {
        $milestones = new Milestones([10, 1, 10, 5], [100, 1, 50, 50]);

        self::assertSame([1, 5, 10], $milestones->streaks);
        self::assertSame([1, 50, 100], $milestones->strengths);
        self::assertSame([], new Milestones([], [])->streaks);
    }

    public function testTheMilestonesPassedBetweenTwoValues(): void
    {
        $milestones = new Milestones([3, 7, 14], [25, 50, 75]);

        self::assertSame([3, 7], $milestones->streaksBetween(2, 7));
        self::assertSame([7], $milestones->streaksBetween(3, 13));
        self::assertSame([], $milestones->streaksBetween(7, 7));
        self::assertSame([], $milestones->streaksBetween(7, 0));
        self::assertSame([50], $milestones->strengthsBetween(25, 50));
        self::assertSame([25, 50, 75], $milestones->strengthsBetween(0, 100));
        self::assertSame([], $milestones->strengthsBetween(74, 74));
    }

    /**
     * @return iterable<string, array{list<int>, list<int>, string}>
     */
    public static function invalid(): iterable
    {
        $many = 'An application celebrates at most 50 streak lengths and 50 strengths.';

        yield 'a streak of zero' => [[0], [], 'A streak length to celebrate is a whole number from 1.'];
        yield 'a strength of zero' => [[], [0], 'A strength to celebrate is a percentage from 1 to 100.'];
        yield 'a strength above 100' => [[], [101], 'A strength to celebrate is a percentage from 1 to 100.'];
        yield 'too many streaks' => [range(1, 51), [], $many];
        yield 'too many strengths' => [[], array_merge(range(1, 50), [1]), $many];
    }

    /**
     * @param list<int> $streaks
     * @param list<int> $strengths
     */
    #[DataProvider('invalid')]
    public function testMilestonesOutOfBoundsAreRefused(array $streaks, array $strengths, string $message): void
    {
        $this->expectExceptionObject(InvalidHabit::because($message));

        new Milestones($streaks, $strengths);
    }

    public function testFiftyMilestonesOfEachAreKept(): void
    {
        $milestones = new Milestones(range(1, 50), range(51, 100));

        self::assertCount(50, $milestones->streaks);
        self::assertCount(50, $milestones->strengths);
    }
}
