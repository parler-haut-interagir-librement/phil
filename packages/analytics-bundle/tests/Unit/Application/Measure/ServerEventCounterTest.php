<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Application\Measure;

use Override;
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Infrastructure\Collection\ConfiguredCollectionProjection;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InMemoryCounters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ServerEventCounter::class)]
final class ServerEventCounterTest extends TestCase
{
    private InMemoryCounters $store;

    private ServerEventCounter $counter;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryCounters();
        $this->counter = new ServerEventCounter(
            new ConfiguredCollectionProjection([
                'spring-con' => $this->site('active', ['signup', 'ticket_sold'], ['plan' => ['free', 'pro'], 'lang' => ['fr', 'en']]),
                'autumn' => $this->site('active', ['signup'], []),
                'paused' => $this->site('suspended', ['signup'], []),
            ]),
            $this->store,
            // Late in the evening in Montréal is already the next UTC day.
            new MockClock('2026-11-10 21:30:00 America/Toronto'),
        );
    }

    public function testADeclaredEventIsCountedOnTheUtcDayWithItsDeclaredDimensions(): void
    {
        self::assertTrue($this->counter->count(SiteId::fromString('spring-con'), 'signup', ['plan' => 'pro', 'lang' => 'de', 'account' => 'someone']));
        self::assertTrue($this->counter->count(SiteId::fromString('spring-con'), 'signup'));

        self::assertSame([
            'spring-con|2026-11-11|server_events|event|signup' => 2,
            'spring-con|2026-11-11|server_events.signup|plan|pro' => 1,
        ], $this->store->counters);
        self::assertSame(['signup' => 2], $this->store->counts(SiteId::fromString('spring-con'), MeasureNames::SERVER_EVENTS, 'event', UtcDay::fromString('2026-11-11'), UtcDay::fromString('2026-11-11')));
    }

    public function testEachSiteCountsAgainstItsOwnDeclaration(): void
    {
        self::assertTrue($this->counter->count(SiteId::fromString('autumn'), 'signup', ['plan' => 'pro']));
        self::assertFalse($this->counter->count(SiteId::fromString('autumn'), 'ticket_sold'));

        self::assertSame(['autumn|2026-11-11|server_events|event|signup' => 1], $this->store->counters);
    }

    public function testNothingIsCountedForAnUndeclaredEventAnUnknownSiteOrASiteThatDoesNotCollect(): void
    {
        self::assertFalse($this->counter->count(SiteId::fromString('spring-con'), 'login'));
        self::assertFalse($this->counter->count(SiteId::fromString('elsewhere'), 'signup'));
        self::assertFalse($this->counter->count(SiteId::fromString('paused'), 'signup'));

        self::assertSame([], $this->store->counters);
    }

    /**
     * @param list<string>                $events
     * @param array<string, list<string>> $dimensions
     *
     * @return array{origins: list<string>, routes: array<string, string>, status: string, goals: array<string, array{route: ?string, event: ?string, value: ?string}>, forms: list<string>, click_names: list<string>, dimensions: array<string, list<string>>, server_events: list<string>}
     */
    private function site(string $status, array $events, array $dimensions): array
    {
        static $n = 0;
        ++$n;

        return ['origins' => ['https://site'.$n.'.example'], 'routes' => [], 'status' => $status, 'goals' => [], 'forms' => [], 'click_names' => [], 'dimensions' => $dimensions, 'server_events' => $events];
    }
}
