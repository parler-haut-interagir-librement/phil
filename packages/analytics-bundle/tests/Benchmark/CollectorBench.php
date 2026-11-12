<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Benchmark;

use Generator;
use Override;
use Phil\AnalyticsBundle\Application\Collection\CollectionRequest;
use Phil\AnalyticsBundle\Application\Collection\Collector;
use Phil\AnalyticsBundle\Application\Contract\CollectionRateLimiter;
use Phil\AnalyticsBundle\Application\Measure\MeasureCounters;
use Phil\AnalyticsBundle\Domain\Collection\AdmissionRule;
use Phil\AnalyticsBundle\Domain\Collection\ClientDigester;
use Phil\AnalyticsBundle\Domain\Source\ReferrerClassifier;
use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Infrastructure\Collection\ConfiguredCollectionProjection;
use Phil\AnalyticsBundle\Infrastructure\Collection\NoOutboundLinks;
use Phil\AnalyticsBundle\Infrastructure\Collection\UnknownCountryResolver;
use Phil\AnalyticsBundle\Infrastructure\Collection\UserAgentCrawlerDetector;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InMemoryCounters;
use Phil\IpAnonymizer\FixedSalt;
use Phil\IpAnonymizer\Salt;
use Symfony\Component\Clock\MockClock;

/**
 * Every page view goes through `Collector`: the cost of one request, from its origin to its counters, with the counter
 * store and the limiter in memory so that only the bundle's own work is measured, for the last of many sites.
 */
final class CollectorBench
{
    /**
     * @var array<int, Collector>
     */
    private array $collectors = [];

    public function __construct()
    {
        foreach ([1, 100, 1_000] as $sites) {
            $declared = [];

            for ($i = 0; $i < $sites; ++$i) {
                $declared['site-'.$i] = [
                    'origins' => ['https://site-'.$i.'.example'],
                    'routes' => ['home' => '/', 'talk' => '/talks/{slug}', 'thanks' => '/thanks'],
                    'status' => 'active',
                    'goals' => ['ticket' => ['route' => 'thanks', 'event' => null, 'value' => null]],
                    'forms' => ['contact'],
                    'click_names' => [],
                ];
            }

            $this->collectors[$sites] = new Collector(
                new ConfiguredCollectionProjection($declared),
                new class implements CollectionRateLimiter {
                    #[Override]
                    public function consume(SiteId $site, ClientDigest $digest): bool
                    {
                        return true;
                    }
                },
                new ClientDigester(new FixedSalt(Salt::of('bench', str_repeat('k', 32)))),
                new ReferrerClassifier(HostList::of(['google.{}', 'bing.com']), HostList::of(['mastodon.social']), HostList::of(['spam.test'])),
                new AdmissionRule(),
                new UserAgentCrawlerDetector(),
                new UnknownCountryResolver(),
                new InMemoryCounters(),
                new MeasureCounters(new NoOutboundLinks()),
                new MockClock('2026-11-11 12:00:00 UTC'),
            );
        }
    }

    /**
     * @ParamProviders({"siteCounts"})
     *
     * @param array{sites: int} $params
     */
    public function benchAPageView(array $params): void
    {
        $this->collectors[$params['sites']]->collect(new CollectionRequest(
            'https://site-'.($params['sites'] - 1).'.example',
            null,
            '192.0.2.1',
            'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0',
            null,
            null,
            '{"path":"/talks/opening","referrer":"https://www.google.ca","verdict":"human"}',
        ));
    }

    /**
     * @return Generator<string, array{sites: int}>
     */
    public function siteCounts(): Generator
    {
        foreach ([1, 100, 1_000] as $sites) {
            yield $sites.' sites' => ['sites' => $sites];
        }
    }
}
