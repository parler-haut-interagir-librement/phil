<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit;

use Phil\Geo\Distance;
use Phil\Geo\Exception\InvalidCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Distance::class)]
final class DistanceTest extends TestCase
{
    public function testADistanceIsInMetersKilometersOrMiles(): void
    {
        self::assertSame(1_500.0, Distance::meters(1_500)->meters);
        self::assertSame(2_500.0, Distance::kilometers(2.5)->meters);
        self::assertSame(1_609.344, Distance::miles(1)->meters);
        self::assertSame(1.5, Distance::meters(1_500)->inKilometers());
        self::assertSame(2.0, Distance::meters(3_218.688)->inMiles());
        self::assertSame(0.0, Distance::meters(0)->meters);
    }

    public function testDistancesCompare(): void
    {
        $short = Distance::meters(10);
        $long = Distance::meters(20);

        self::assertTrue($short->isShorterThan($long));
        self::assertFalse($long->isShorterThan($short));
        self::assertFalse($short->isShorterThan(Distance::meters(10)));
        self::assertSame([-1, 0, 1], [$short->compare($long), $short->compare(Distance::meters(10)), $long->compare($short)]);
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function badDistances(): iterable
    {
        yield 'negative' => [-0.001, '-0.001'];
        yield 'infinite' => [\INF, 'INF'];
        yield 'infinitely negative' => [-\INF, '-INF'];
        yield 'not a number' => [\NAN, 'NAN'];
    }

    #[DataProvider('badDistances')]
    public function testADistanceIsZeroOrMoreAndFinite(float $meters, string $shown): void
    {
        $this->expectException(InvalidCoordinates::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A distance is zero meters or more, and finite; %s is not.', $shown));

        Distance::meters($meters);
    }
}
