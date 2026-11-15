<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit;

use DateTimeImmutable;
use Phil\ProductFeeds\Exception\InvalidFeedItem;
use Phil\ProductFeeds\SalePeriod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SalePeriod::class)]
#[CoversClass(InvalidFeedItem::class)]
final class SalePeriodTest extends TestCase
{
    public function testAPeriodIsWrittenInUtc(): void
    {
        $period = new SalePeriod(new DateTimeImmutable('2026-11-27 00:00:00-05:00'), new DateTimeImmutable('2026-12-01 00:00:00-05:00'));

        self::assertSame('2026-11-27T05:00:00+00:00/2026-12-01T05:00:00+00:00', $period->toString());
        self::assertSame('UTC', $period->from->getTimezone()->getName());
        self::assertSame('UTC', $period->until->getTimezone()->getName());
    }

    public function testASecondIsAPeriod(): void
    {
        self::assertSame('2026-11-27T00:00:00+00:00/2026-11-27T00:00:01+00:00', new SalePeriod(new DateTimeImmutable('2026-11-27 00:00:00+00:00'), new DateTimeImmutable('2026-11-27 00:00:01+00:00'))->toString());
    }

    public function testAPeriodEndsAfterItStarts(): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('A sale ends after it starts.');

        new SalePeriod(new DateTimeImmutable('2026-11-27 05:00:00+00:00'), new DateTimeImmutable('2026-11-27 00:00:00-05:00'));
    }
}
