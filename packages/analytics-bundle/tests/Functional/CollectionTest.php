<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\AnalyticsBundle\Application\Collection\CollectionOutcome;
use Phil\AnalyticsBundle\Application\Collection\Collector;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;
use Phil\AnalyticsBundle\Application\Reading\CounterReader;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\Reading\ReaderScope;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Infrastructure\Collection\ConfiguredCollectionProjection;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\CounterTables;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterStore;
use Phil\AnalyticsBundle\Infrastructure\Http\CollectController;
use Phil\AnalyticsBundle\Infrastructure\RateLimiter\SymfonyCollectionRateLimiter;
use Phil\AnalyticsBundle\Infrastructure\Salt\CacheSaltStore;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The bundle with its default adapters — a site declared in the configuration, the counters in DBAL, the daily salt in
 * the application's cache, the Symfony Rate Limiter budget — and a page view sent to the collection route.
 */
#[CoversClass(CollectController::class)]
#[CoversClass(Collector::class)]
#[CoversClass(CollectionOutcome::class)]
#[CoversClass(ConfiguredCollectionProjection::class)]
#[CoversClass(ServerEventCounter::class)]
#[CoversClass(CounterReader::class)]
#[CoversClass(DbalCounterStore::class)]
#[CoversClass(CacheSaltStore::class)]
#[CoversClass(SymfonyCollectionRateLimiter::class)]
final class CollectionTest extends FunctionalTestCase
{
    private const string FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';

    #[Override]
    protected static function analyticsConfig(): array
    {
        return [
            'sites' => [
                'spring-con' => [
                    'origins' => ['https://example.com'],
                    'routes' => ['home' => '/', 'talk' => '/talks/{slug}', 'thanks' => '/tickets/thanks'],
                    'goals' => ['ticket' => ['route' => 'thanks']],
                    'dimensions' => ['plan' => ['free', 'pro']],
                    'server_events' => ['signup'],
                ],
            ],
            'rate_limiter' => ['limit' => 3, 'interval' => '1 hour'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $pool = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $manager = $connection->createSchemaManager();
        foreach ([CounterTables::DAILY, CounterTables::MONTHLY] as $name) {
            if ($manager->tablesExist([$name])) {
                $manager->dropTable($name);
            }
        }

        foreach (CounterTables::configure($manager->introspectSchema())->getTables() as $table) {
            $manager->createTable($table);
        }
    }

    public function testAPageViewFromASearchEngineIsCounted(): void
    {
        self::assertSame(204, $this->send(['path' => '/talks/opening?code=secret', 'referrer' => 'https://www.google.ca', 'verdict' => 'human']));

        self::assertSame(['talk' => 1], $this->counts(MeasureNames::PAGEVIEWS, 'route'));
        self::assertSame(['desktop' => 1], $this->counts(MeasureNames::PAGEVIEWS, 'device'));
        self::assertSame(['(unknown)' => 1], $this->counts(MeasureNames::PAGEVIEWS, 'country'));
        self::assertSame(['search' => 1], $this->counts(MeasureNames::VISITS, 'source'));
    }

    public function testAGoalReachedByItsRouteIsCounted(): void
    {
        $this->send(['path' => '/tickets/thanks', 'verdict' => 'human']);
        $this->send(['path' => '/tickets/thanks', 'verdict' => 'human']);

        self::assertSame(['ticket' => 2], $this->counts(MeasureNames::GOALS, 'goal'));
    }

    public function testTheServerCountsADeclaredEventForItsSite(): void
    {
        $events = $this->service(ServerEventCounter::class, ServerEventCounter::class);

        self::assertTrue($events->count(SiteId::fromString('spring-con'), 'signup', ['plan' => 'pro']));
        self::assertFalse($events->count(SiteId::fromString('spring-con'), 'login'));
        self::assertFalse($events->count(SiteId::fromString('elsewhere'), 'signup'));

        self::assertSame(['signup' => 1], $this->counts(MeasureNames::SERVER_EVENTS, 'event'));
        self::assertSame(['pro' => 1], $this->counts(MeasureNames::SERVER_EVENTS.'.signup', 'plan'));
    }

    public function testADashboardReadsItsOwnSiteOnlyAndUnderTheSmallNumberRule(): void
    {
        $events = $this->service(ServerEventCounter::class, ServerEventCounter::class);
        $reader = $this->service(CounterReader::class, CounterReader::class);
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(ClockInterface::class, $clock);
        $today = UtcDay::of($clock->now());
        $site = SiteId::fromString('spring-con');

        for ($i = 0; $i < 6; ++$i) {
            $events->count($site, 'signup');
        }

        self::assertSame(['signup' => 6], $reader->read(ReaderScope::ofSites($site), $site, MeasureNames::SERVER_EVENTS, 'event', $today, $today)?->shown);
        self::assertNull($reader->read(ReaderScope::ofSites(SiteId::fromString('autumn')), $site, MeasureNames::SERVER_EVENTS, 'event', $today, $today));
    }

    public function testAnOriginNoSiteDeclaresIsRefused(): void
    {
        self::assertSame(403, $this->send(['path' => '/', 'verdict' => 'human'], ['HTTP_ORIGIN' => 'https://elsewhere.example']));
        self::assertSame([], $this->counts(MeasureNames::PAGEVIEWS, 'route'));
    }

    public function testDoNotTrackCountsNothing(): void
    {
        self::assertSame(204, $this->send(['path' => '/', 'verdict' => 'human'], ['HTTP_DNT' => '1']));
        self::assertSame([], $this->counts(MeasureNames::PAGEVIEWS, 'route'));
    }

    public function testARobotVerdictAdvancesOnlyTheRobotCounter(): void
    {
        self::assertSame(204, $this->send(['path' => '/', 'verdict' => 'robot']));

        self::assertSame([], $this->counts(MeasureNames::PAGEVIEWS, 'route'));
        self::assertSame(['robot' => 1], $this->counts(MeasureNames::ROBOT_VERDICTS, 'verdict'));
    }

    public function testADeclaredCrawlerCountsNothing(): void
    {
        self::assertSame(204, $this->send(['path' => '/', 'verdict' => 'human'], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']));
        self::assertSame([], $this->counts(MeasureNames::PAGEVIEWS, 'route'));
    }

    public function testTheBudgetOfAClientIsBounded(): void
    {
        foreach ([204, 204, 204, 429] as $status) {
            self::assertSame($status, $this->send(['path' => '/', 'verdict' => 'human']));
        }

        // Another address has its own budget.
        self::assertSame(204, $this->send(['path' => '/', 'verdict' => 'human'], ['REMOTE_ADDR' => '198.51.100.7']));
        self::assertSame(['home' => 4], $this->counts(MeasureNames::PAGEVIEWS, 'route'));
    }

    public function testAnAnnouncedLongBodyIsRefusedBeforeItIsRead(): void
    {
        self::assertSame(413, $this->send(['path' => '/'], ['CONTENT_LENGTH' => (string) (Collector::MAX_BODY_BYTES + 1)]));
    }

    public function testALongBodyIsRefused(): void
    {
        self::assertSame(413, $this->send(['path' => '/', 'padding' => str_repeat('x', Collector::MAX_BODY_BYTES)]));
    }

    public function testOnlyPostIsRouted(): void
    {
        $response = $this->handle(Request::create('/analytics/collect', 'GET'));

        self::assertSame(405, $response);
    }

    public function testTheResponseIsNeverCached(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $response = $kernel->handle($this->request(['path' => '/', 'verdict' => 'human'], []));

        self::assertSame('', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $server
     */
    private function send(array $payload, array $server = []): int
    {
        return $this->handle($this->request($payload, $server));
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $server
     */
    private function request(array $payload, array $server): Request
    {
        return Request::create('/analytics/collect', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_USER_AGENT' => self::FIREFOX,
            'CONTENT_TYPE' => 'text/plain',
            ...$server,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    private function handle(Request $request): int
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return $kernel->handle($request)->getStatusCode();
    }

    /**
     * @return array<string, int>
     */
    private function counts(string $metric, string $dimension): array
    {
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(ClockInterface::class, $clock);
        $store = $this->service(CounterStore::class, CounterStore::class);
        $today = UtcDay::of($clock->now());

        return $store->counts(SiteId::fromString('spring-con'), $metric, $dimension, $today, $today);
    }
}
