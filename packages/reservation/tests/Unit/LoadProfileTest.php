<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Unit;

use Phil\Reservation\LoadProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadProfile::class)]
final class LoadProfileTest extends TestCase
{
    public function testAnEmptyProfileHoldsNothing(): void
    {
        self::assertSame(0, LoadProfile::of([])->peak(0, 100));
    }

    public function testItReadsThePeakOfASpan(): void
    {
        // 10–20: 1; 15–30: 2; 20–25: 4; touching ends free their units.
        $profile = LoadProfile::of([[10, 20, 1], [15, 30, 2], [20, 25, 4]]);

        self::assertSame(0, $profile->peak(0, 10));
        self::assertSame(1, $profile->peak(0, 11));
        self::assertSame(1, $profile->peak(10, 15));
        self::assertSame(3, $profile->peak(10, 16));
        self::assertSame(3, $profile->peak(15, 20));
        self::assertSame(6, $profile->peak(15, 21));
        self::assertSame(6, $profile->peak(22, 23));
        self::assertSame(6, $profile->peak(20, 21));
        self::assertSame(2, $profile->peak(25, 26));
        self::assertSame(2, $profile->peak(29, 40));
        self::assertSame(0, $profile->peak(30, 40));
        self::assertSame(6, $profile->peak(0, 100));
    }

    public function testSpansEndingWhenOthersStartDoNotAddUp(): void
    {
        $profile = LoadProfile::of([[0, 10, 1], [10, 20, 1], [10, 20, 1]]);

        self::assertSame(1, $profile->peak(0, 10));
        self::assertSame(2, $profile->peak(9, 11));
        self::assertSame(2, $profile->peak(10, 20));
    }

    public function testAnEndMeetingAnEarlierStartAddsUp(): void
    {
        $profile = LoadProfile::of([[10, 20, 1], [0, 10, 1]]);

        self::assertSame(1, $profile->peak(10, 20));
        self::assertSame(1, $profile->peak(0, 20));
    }

    public function testItSearchesAmongManyChanges(): void
    {
        $spans = [];

        for ($i = 0; $i < 100; ++$i) {
            $spans[] = [$i * 10, $i * 10 + 5, $i % 7 + 1];
        }

        $profile = LoadProfile::of($spans);

        self::assertSame(4, $profile->peak(30, 35));
        self::assertSame(0, $profile->peak(35, 40));
        self::assertSame(7, $profile->peak(60, 61));
        self::assertSame(1, $profile->peak(700, 702));
        self::assertSame(7, $profile->peak(0, 1000));
        self::assertSame(2, $profile->peak(992, 993));
    }
}
