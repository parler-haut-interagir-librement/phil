<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Application\Collection;

use Override;
use Phil\AnalyticsBundle\Application\Collection\CollectionOutcome;
use Phil\AnalyticsBundle\Application\Collection\CollectionRequest;
use Phil\AnalyticsBundle\Application\Collection\Collector;
use Phil\AnalyticsBundle\Application\Contract\CollectionProjection;
use Phil\AnalyticsBundle\Application\Contract\CollectionRateLimiter;
use Phil\AnalyticsBundle\Application\Contract\CountryResolver;
use Phil\AnalyticsBundle\Application\Measure\MeasureCounters;
use Phil\AnalyticsBundle\Domain\Collection\AdmissionRule;
use Phil\AnalyticsBundle\Domain\Collection\ClientDigester;
use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Measure\ClosedList;
use Phil\AnalyticsBundle\Domain\Measure\Goal;
use Phil\AnalyticsBundle\Domain\Measure\MeasurePlan;
use Phil\AnalyticsBundle\Domain\Source\ReferrerClassifier;
use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\CountryCode;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoute;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;
use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Infrastructure\Collection\NoOutboundLinks;
use Phil\AnalyticsBundle\Infrastructure\Collection\UserAgentCrawlerDetector;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InMemoryCounters;
use Phil\IpAnonymizer\FixedSalt;
use Phil\IpAnonymizer\Salt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Collector::class)]
#[CoversClass(CollectionRequest::class)]
#[CoversClass(CollectionOutcome::class)]
final class CollectorTest extends TestCase
{
    private const string FIREFOX = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0';

    private InMemoryCounters $store;

    /**
     * @var list<string> the digests the limiter was asked about
     */
    private array $consumed = [];

    private bool $accept = true;

    /**
     * @var list<string>
     */
    private array $countryAsked = [];

    protected function setUp(): void
    {
        $this->store = new InMemoryCounters();
    }

    public function testAPageViewAdvancesItsRouteDeviceCountryAndSource(): void
    {
        self::assertSame(CollectionOutcome::Counted, $this->collect(['path' => '/talks/x', 'referrer' => 'https://www.bing.com', 'verdict' => 'human']));

        self::assertSame([
            's|2026-11-11|pageviews|route|talk' => 1,
            's|2026-11-11|pageviews|device|desktop' => 1,
            's|2026-11-11|pageviews|country|CA' => 1,
            's|2026-11-11|visits|source|search' => 1,
        ], $this->store->counters);
        self::assertSame(['192.0.2.1'], $this->countryAsked);
    }

    public function testAnInternalPageViewIsNotAVisit(): void
    {
        $this->collect(['path' => '/', 'referrer' => 'https://example.com', 'verdict' => 'human']);

        self::assertArrayNotHasKey('s|2026-11-11|visits|source|internal', $this->store->counters);
        self::assertSame(1, $this->store->counters['s|2026-11-11|pageviews|route|home']);
    }

    public function testAGoalOfThePageViewIsCounted(): void
    {
        $this->collect(['path' => '/thanks', 'verdict' => 'human']);

        self::assertSame(1, $this->store->counters['s|2026-11-11|goals|goal|ticket']);
    }

    public function testAnEventAdvancesItsOwnCountersAndNoPageView(): void
    {
        self::assertSame(CollectionOutcome::Counted, $this->collect(['event' => 'form_submit', 'form' => 'contact', 'verdict' => 'human']));

        self::assertSame(['s|2026-11-11|events|form|contact' => 1], $this->store->counters);
    }

    public function testARobotVerdictAdvancesTheRobotCounterOnly(): void
    {
        self::assertSame(CollectionOutcome::Counted, $this->collect(['path' => '/', 'verdict' => 'robot']));

        self::assertSame(['s|2026-11-11|robot_verdicts|verdict|robot' => 1], $this->store->counters);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, ?string>}>
     */
    public static function acceptedNotCounted(): iterable
    {
        yield 'no verdict' => [['path' => '/'], []];
        yield 'a verdict that is not one' => [['path' => '/', 'verdict' => ['human']], []];
        yield 'Do Not Track' => [['path' => '/', 'verdict' => 'human'], ['dnt' => '1']];
        yield 'Global Privacy Control' => [['path' => '/', 'verdict' => 'human'], ['gpc' => '1']];
        yield 'a declared crawler' => [['path' => '/', 'verdict' => 'human'], ['userAgent' => 'curl/8.0']];
        yield 'referrer spam' => [['path' => '/', 'verdict' => 'human', 'referrer' => 'https://spam.test'], []];
    }

    /**
     * @param array<string, mixed>   $payload
     * @param array<string, ?string> $request
     */
    #[DataProvider('acceptedNotCounted')]
    public function testAcceptedButCountedNowhere(array $payload, array $request): void
    {
        self::assertSame(CollectionOutcome::NotCounted, $this->collect($payload, ...$request));
        self::assertSame([], $this->store->counters);
        self::assertCount(1, $this->consumed);
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function unknownOrigins(): iterable
    {
        yield 'no origin at all' => [null, null];
        yield 'an origin no site declares' => ['https://elsewhere.example', null];
        yield 'a referer no site declares' => [null, 'https://elsewhere.example/page'];
    }

    #[DataProvider('unknownOrigins')]
    public function testAnUnknownOriginIsRefusedBeforeTheLimiter(?string $origin, ?string $referer): void
    {
        self::assertSame(CollectionOutcome::Refused, $this->collect(['path' => '/', 'verdict' => 'human'], origin: $origin, referer: $referer));
        self::assertSame([], $this->consumed);
    }

    public function testTheRefererStandsInForAMissingOrigin(): void
    {
        self::assertSame(CollectionOutcome::Counted, $this->collect(['path' => '/', 'verdict' => 'human'], origin: null, referer: 'https://example.com/page'));
    }

    public function testASuspendedSiteIsRefusedBeforeTheLimiter(): void
    {
        self::assertSame(CollectionOutcome::Refused, $this->collect(['path' => '/', 'verdict' => 'human'], origin: 'https://paused.example'));
        self::assertSame([], $this->consumed);
    }

    public function testAnAddressThatIsNotOneIsRefusedBeforeTheLimiter(): void
    {
        self::assertSame(CollectionOutcome::Refused, $this->collect(['path' => '/', 'verdict' => 'human'], ip: 'not-an-address'));
        self::assertSame([], $this->consumed);
    }

    public function testOverBudgetCountsNothing(): void
    {
        $this->accept = false;

        self::assertSame(CollectionOutcome::OverBudget, $this->collect(['path' => '/', 'verdict' => 'human']));
        self::assertSame([], $this->store->counters);
    }

    public function testTheLimiterIsKeyedOnTheDigestOfTheSiteAndAddress(): void
    {
        $this->collect(['path' => '/', 'verdict' => 'human']);
        $this->collect(['path' => '/', 'verdict' => 'human'], ip: '192.0.2.200');

        self::assertCount(2, $this->consumed);
        self::assertStringStartsWith('s/', $this->consumed[0]);
        self::assertNotSame($this->consumed[0], $this->consumed[1]);
        self::assertStringNotContainsString('192.0.2', $this->consumed[0]);
    }

    public function testABodyAtTheLimitIsRead(): void
    {
        $body = json_encode(['path' => '/', 'verdict' => 'human'], \JSON_THROW_ON_ERROR);
        $body .= str_repeat(' ', Collector::MAX_BODY_BYTES - \strlen($body));

        self::assertSame(CollectionOutcome::Counted, $this->collector()->collect($this->request($body)));
    }

    public function testABodyOverTheLimitIsRefusedBeforeAnything(): void
    {
        self::assertSame(CollectionOutcome::TooLarge, $this->collector()->collect($this->request(str_repeat(' ', Collector::MAX_BODY_BYTES + 1))));
        self::assertSame([], $this->consumed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function junk(): iterable
    {
        yield 'not JSON' => ['path=/'];
        yield 'a JSON string' => ['"human"'];
        yield 'a list' => ['["human"]'];
        yield 'too deep' => ['{"verdict":"human","dimensions":{"a":{"b":{"c":1}}}}'];
    }

    #[DataProvider('junk')]
    public function testJunkCountsNothing(string $body): void
    {
        self::assertSame(CollectionOutcome::NotCounted, $this->collector()->collect($this->request($body)));
        self::assertSame([], $this->store->counters);
    }

    public function testAnObjectOfTheAllowedDepthIsRead(): void
    {
        self::assertSame(CollectionOutcome::Counted, $this->collector()->collect($this->request('{"verdict":"human","path":"/","dimensions":{"a":{"b":"c"}}}')));
    }

    /**
     * @return iterable<string, array{CollectionOutcome, int}>
     */
    public static function statuses(): iterable
    {
        yield 'counted' => [CollectionOutcome::Counted, 204];
        yield 'not counted' => [CollectionOutcome::NotCounted, 204];
        yield 'refused' => [CollectionOutcome::Refused, 403];
        yield 'over budget' => [CollectionOutcome::OverBudget, 429];
        yield 'too large' => [CollectionOutcome::TooLarge, 413];
    }

    #[DataProvider('statuses')]
    public function testEachOutcomeHasItsStatus(CollectionOutcome $outcome, int $status): void
    {
        self::assertSame($status, $outcome->httpStatus());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function collect(
        array $payload,
        ?string $origin = 'https://example.com',
        ?string $referer = null,
        string $ip = '192.0.2.1',
        string $userAgent = self::FIREFOX,
        ?string $dnt = null,
        ?string $gpc = null,
    ): CollectionOutcome {
        return $this->collector()->collect(new CollectionRequest($origin, $referer, $ip, $userAgent, $dnt, $gpc, json_encode($payload, \JSON_THROW_ON_ERROR)));
    }

    private function request(string $body): CollectionRequest
    {
        return new CollectionRequest('https://example.com', null, '192.0.2.1', self::FIREFOX, null, null, $body);
    }

    private function collector(): Collector
    {
        $sites = [
            CollectionSite::of(
                SiteId::fromString('s'),
                [Origin::fromString('https://example.com')],
                DeclaredRoutes::of([DeclaredRoute::of('home', '/'), DeclaredRoute::of('talk', '/talks/{slug}'), DeclaredRoute::of('thanks', '/thanks')]),
                SiteStatus::Active,
                MeasurePlan::of(forms: ClosedList::of(['contact']), goals: [Goal::onRoute('ticket', 'thanks')]),
            ),
            CollectionSite::of(SiteId::fromString('paused'), [Origin::fromString('https://paused.example')], DeclaredRoutes::none(), SiteStatus::Suspended),
        ];
        $test = $this;

        return new Collector(
            new readonly class($sites) implements CollectionProjection {
                /**
                 * @param list<CollectionSite> $sites
                 */
                public function __construct(
                    private array $sites,
                ) {
                }

                #[Override]
                public function siteDeclaring(Origin $origin): ?CollectionSite
                {
                    foreach ($this->sites as $site) {
                        if ($site->declares($origin)) {
                            return $site;
                        }
                    }

                    return null;
                }

                #[Override]
                public function site(SiteId $site): ?CollectionSite
                {
                    return null;
                }
            },
            new readonly class($test) implements CollectionRateLimiter {
                public function __construct(
                    private CollectorTest $test,
                ) {
                }

                #[Override]
                public function consume(SiteId $site, ClientDigest $digest): bool
                {
                    return $this->test->consumed($site->value.'/'.$digest->toString());
                }
            },
            new ClientDigester(new FixedSalt(Salt::of('test', str_repeat('k', 32)))),
            new ReferrerClassifier(HostList::of(['bing.com']), HostList::empty(), HostList::of(['spam.test'])),
            new AdmissionRule(),
            new UserAgentCrawlerDetector(),
            new readonly class($test) implements CountryResolver {
                public function __construct(
                    private CollectorTest $test,
                ) {
                }

                #[Override]
                public function countryOf(#[SensitiveParameter] string $ip): CountryCode
                {
                    $this->test->countryAsked($ip);

                    return CountryCode::fromString('CA');
                }
            },
            $this->store,
            new MeasureCounters(new NoOutboundLinks()),
            new MockClock('2026-11-11 23:30:00 UTC'),
        );
    }

    /**
     * @internal the limiter of the test
     */
    public function consumed(string $key): bool
    {
        $this->consumed[] = $key;

        return $this->accept;
    }

    /**
     * @internal the country resolver of the test
     */
    public function countryAsked(string $ip): void
    {
        $this->countryAsked[] = $ip;
    }
}
