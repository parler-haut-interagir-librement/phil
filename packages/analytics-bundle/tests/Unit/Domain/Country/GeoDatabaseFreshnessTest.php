<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Country;

use DateTimeImmutable;
use Phil\AnalyticsBundle\Domain\Country\GeoDatabaseFreshness;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeoDatabaseFreshness::class)]
final class GeoDatabaseFreshnessTest extends TestCase
{
    public function testADatabaseIsUsableUpToItsMaximumAge(): void
    {
        $freshness = GeoDatabaseFreshness::maxAgeDays(30);
        $built = new DateTimeImmutable('2026-10-01T00:00:00Z');

        self::assertTrue($freshness->isUsable($built, new DateTimeImmutable('2026-10-01T00:00:00Z')));
        self::assertTrue($freshness->isUsable($built, new DateTimeImmutable('2026-10-31T00:00:00Z')));
        self::assertFalse($freshness->isUsable($built, new DateTimeImmutable('2026-10-31T00:00:01Z')));
    }

    /**
     * A build date in the future is not a fresh database: it is a clock or a file that cannot be trusted.
     */
    public function testADatabaseBuiltInTheFutureIsNotUsable(): void
    {
        self::assertFalse(GeoDatabaseFreshness::maxAgeDays(30)->isUsable(
            new DateTimeImmutable('2026-11-02T00:00:00Z'),
            new DateTimeImmutable('2026-11-01T00:00:00Z'),
        ));
    }

    public function testOneDayIsTheShortestMaximumAge(): void
    {
        self::assertSame(1, GeoDatabaseFreshness::maxAgeDays(1)->maxAgeDays);
    }

    public function testAMaximumAgeUnderOneDayIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        GeoDatabaseFreshness::maxAgeDays(0);
    }
}
