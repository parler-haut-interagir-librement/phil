<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Application\Reading;

use Override;
use Phil\AnalyticsBundle\Application\Reading\CounterReader;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\Reading\ReaderScope;
use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\Reading\SuppressedCounts;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InMemoryCounters;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CounterReader::class)]
final class CounterReaderTest extends TestCase
{
    private InMemoryCounters $store;

    private CounterReader $reader;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryCounters();
        $this->reader = new CounterReader($this->store, new SmallNumberSuppression(SmallNumberThreshold::of(5)));

        $this->add('spring-con', '2026-11-09', 'home', 7);
        $this->add('spring-con', '2026-11-10', 'home', 2);
        $this->add('spring-con', '2026-11-10', 'tickets', 3);
        $this->add('autumn', '2026-11-10', 'tickets', 3);
        $this->add('autumn', '2026-11-10', 'about', 1);
    }

    public function testASiteInTheScopeIsReadUnderTheSmallNumberRule(): void
    {
        $counts = $this->reader->read(ReaderScope::ofSites($this->site('spring-con')), $this->site('spring-con'), MeasureNames::PAGEVIEWS, 'route', $this->day('2026-11-09'), $this->day('2026-11-10'));

        self::assertEquals(new SuppressedCounts(['home' => 9], null, 1), $counts);
    }

    public function testTheRangeIsInclusiveOfBothDays(): void
    {
        $counts = $this->reader->read(ReaderScope::everySite(), $this->site('spring-con'), MeasureNames::PAGEVIEWS, 'route', $this->day('2026-11-10'), $this->day('2026-11-10'));

        self::assertEquals(new SuppressedCounts([], 5, 0), $counts);
    }

    public function testASiteOutsideTheScopeReadsAsASiteThatDoesNotExist(): void
    {
        self::assertNull($this->reader->read(ReaderScope::ofSites($this->site('autumn')), $this->site('spring-con'), MeasureNames::PAGEVIEWS, 'route', $this->day('2026-11-09'), $this->day('2026-11-10')));
    }

    public function testSitesAreSummedBeforeTheSmallNumberRule(): void
    {
        $counts = $this->reader->readAcross(
            ReaderScope::everySite(),
            [$this->site('spring-con'), $this->site('autumn'), $this->site('autumn')],
            MeasureNames::PAGEVIEWS,
            'route',
            $this->day('2026-11-10'),
            $this->day('2026-11-10'),
        );

        self::assertEquals(new SuppressedCounts(['tickets' => 6], null, 2), $counts);
    }

    public function testASiteOutsideTheScopeIsLeftOutOfTheSum(): void
    {
        $counts = $this->reader->readAcross(
            ReaderScope::ofSites($this->site('spring-con')),
            [$this->site('spring-con'), $this->site('autumn')],
            MeasureNames::PAGEVIEWS,
            'route',
            $this->day('2026-11-10'),
            $this->day('2026-11-10'),
        );

        self::assertEquals(new SuppressedCounts([], 5, 0), $counts);
    }

    public function testTheMostSitesAreReadAtOnceAndADuplicateIsNotCounted(): void
    {
        $sites = array_map(fn (int $i): SiteId => $this->site('site-'.$i), range(1, ReaderScope::MAX_SITES));
        $sites[] = $this->site('site-1');
        $this->add('site-1000', '2026-11-10', 'home', 5);

        $counts = $this->reader->readAcross(ReaderScope::everySite(), $sites, MeasureNames::PAGEVIEWS, 'route', $this->day('2026-11-10'), $this->day('2026-11-10'));

        self::assertSame(['home' => 5], $counts->shown);

        $sites[] = $this->site('site-1001');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('At most 1000 sites are read at once.');

        $this->reader->readAcross(ReaderScope::everySite(), $sites, MeasureNames::PAGEVIEWS, 'route', $this->day('2026-11-10'), $this->day('2026-11-10'));
    }

    public function testTheCountOfEachOutboundLinkIsNotReadHere(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('through LinkCountReader');

        $this->reader->read(ReaderScope::everySite(), $this->site('spring-con'), MeasureNames::OUTBOUND_LINK, 'link', $this->day('2026-11-10'), $this->day('2026-11-10'));
    }

    public function testTheCountOfEachOutboundLinkIsNotSummedEither(): void
    {
        $this->expectException(InvalidValue::class);

        $this->reader->readAcross(ReaderScope::everySite(), [], MeasureNames::OUTBOUND_LINK, 'link', $this->day('2026-11-10'), $this->day('2026-11-10'));
    }

    public function testAReversedRangeIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The range from 2026-11-10 to 2026-11-09 is reversed.');

        $this->reader->read(ReaderScope::everySite(), $this->site('spring-con'), MeasureNames::PAGEVIEWS, 'route', $this->day('2026-11-10'), $this->day('2026-11-09'));
    }

    public function testTheLongestRangeIsReadAndOneDayMoreIsRefused(): void
    {
        $longest = $this->reader->read(ReaderScope::everySite(), $this->site('spring-con'), MeasureNames::PAGEVIEWS, 'route', $this->day('2025-10-07'), $this->day('2026-11-10'));

        self::assertSame(['home' => 9], $longest?->shown);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('At most 400 days are read at once.');

        $this->reader->readAcross(ReaderScope::everySite(), [], MeasureNames::PAGEVIEWS, 'route', $this->day('2025-10-06'), $this->day('2026-11-10'));
    }

    private function add(string $site, string $day, string $route, int $times): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $this->store->increment(CounterKey::of($this->site($site), $this->day($day), MeasureNames::PAGEVIEWS, 'route', $route));
        }
    }

    private function site(string $reference): SiteId
    {
        return SiteId::fromString($reference);
    }

    private function day(string $day): UtcDay
    {
        return UtcDay::fromString($day);
    }
}
