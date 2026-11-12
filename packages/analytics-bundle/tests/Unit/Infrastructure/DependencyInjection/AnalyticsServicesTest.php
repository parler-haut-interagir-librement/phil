<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Phil\AnalyticsBundle\Application\Alert\TrafficAlertRun;
use Phil\AnalyticsBundle\Application\Collection\Collector;
use Phil\AnalyticsBundle\Application\Contract\CollectionProjection;
use Phil\AnalyticsBundle\Application\Contract\CollectionRateLimiter;
use Phil\AnalyticsBundle\Application\Contract\CounterRollup;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Application\Contract\CountryResolver;
use Phil\AnalyticsBundle\Application\Contract\CrawlerDetector;
use Phil\AnalyticsBundle\Application\Contract\OutboundLinkDirectory;
use Phil\AnalyticsBundle\Application\Measure\MeasureCounters;
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;
use Phil\AnalyticsBundle\Application\Message\ApplyCounterRetention;
use Phil\AnalyticsBundle\Application\Message\CheckBrokenFlows;
use Phil\AnalyticsBundle\Application\Message\CheckTrafficAlerts;
use Phil\AnalyticsBundle\Application\Message\SendDueReports;
use Phil\AnalyticsBundle\Application\Reading\CounterReader;
use Phil\AnalyticsBundle\Application\Report\DueReports;
use Phil\AnalyticsBundle\Application\Retention\CounterRetentionRun;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertRule;
use Phil\AnalyticsBundle\Domain\Collection\AdmissionRule;
use Phil\AnalyticsBundle\Domain\Collection\ClientDigester;
use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\Retention\CounterRetention;
use Phil\AnalyticsBundle\Domain\Source\ReferrerClassifier;
use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Infrastructure\BrokenFlow\GoalCompletionHistory;
use Phil\AnalyticsBundle\Infrastructure\Collection\ConfiguredCollectionProjection;
use Phil\AnalyticsBundle\Infrastructure\Collection\NoOutboundLinks;
use Phil\AnalyticsBundle\Infrastructure\Collection\UnknownCountryResolver;
use Phil\AnalyticsBundle\Infrastructure\Collection\UserAgentCrawlerDetector;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsConfiguration;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsServices;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\CounterWiring;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\CounterSchemaListener;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterRollup;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterStore;
use Phil\AnalyticsBundle\Infrastructure\Http\CollectController;
use Phil\AnalyticsBundle\Infrastructure\Messenger\ApplyCounterRetentionHandler;
use Phil\AnalyticsBundle\Infrastructure\Messenger\CheckBrokenFlowsHandler;
use Phil\AnalyticsBundle\Infrastructure\Messenger\CheckTrafficAlertsHandler;
use Phil\AnalyticsBundle\Infrastructure\Messenger\SendDueReportsHandler;
use Phil\AnalyticsBundle\Infrastructure\RateLimiter\SymfonyCollectionRateLimiter;
use Phil\AnalyticsBundle\Infrastructure\Salt\CacheSaltStore;
use Phil\AnalyticsBundle\Infrastructure\Scheduler\AnalyticsSchedule;
use Phil\AnalyticsBundle\PhilAnalyticsBundle;
use Phil\BrokenFlowAlerts\BrokenFlowRule;
use Phil\BrokenFlowAlerts\BrokenFlowWatch;
use Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender;
use Phil\IpAnonymizer\ForgottenSalt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * What each block of `phil_analytics` wires: the bundle's adapters by default, the application's services in their
 * place when named, and the jobs that depend on them.
 */
#[CoversClass(AnalyticsServices::class)]
#[CoversClass(AnalyticsConfiguration::class)]
#[CoversClass(CounterWiring::class)]
final class AnalyticsServicesTest extends TestCase
{
    public function testTheDefaultsWireEveryAdapterOfTheBundle(): void
    {
        $container = $this->load([]);

        foreach ([
            CounterStore::class => DbalCounterStore::class,
            CounterRollup::class => DbalCounterRollup::class,
            CollectionRateLimiter::class => SymfonyCollectionRateLimiter::class,
            CollectionProjection::class => ConfiguredCollectionProjection::class,
            CrawlerDetector::class => UserAgentCrawlerDetector::class,
            CountryResolver::class => UnknownCountryResolver::class,
            OutboundLinkDirectory::class => NoOutboundLinks::class,
            AnalyticsServices::SALT_STORE => CacheSaltStore::class,
        ] as $contract => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($contract), $contract);
        }

        foreach ([Collector::class, CollectController::class, ServerEventCounter::class, CounterReader::class, CounterRetentionRun::class, ApplyCounterRetentionHandler::class, CounterSchemaListener::class] as $service) {
            self::assertTrue($container->hasDefinition($service), $service);
        }

        foreach ([DueReports::class, BrokenFlowWatch::class, TrafficAlertRun::class] as $service) {
            self::assertFalse($container->hasDefinition($service), $service);
        }

        self::assertTrue($container->getDefinition(CollectController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(ApplyCounterRetentionHandler::class)->hasTag('messenger.message_handler'));
        self::assertSame(['05:00', true, false, false, false], $container->getDefinition(AnalyticsSchedule::class)->getArguments());
        self::assertSame([['name' => 'phil_analytics']], $container->getDefinition(AnalyticsSchedule::class)->getTag('scheduler.schedule_provider'));
    }

    public function testTheDbalStoreUsesTheNamedConnection(): void
    {
        $container = $this->load(['counters' => ['connection' => 'analytics']]);

        self::assertEquals([new Reference('doctrine.dbal.analytics_connection')], $container->getDefinition(DbalCounterStore::class)->getArguments());
        self::assertEquals([new Reference('doctrine.dbal.analytics_connection')], $container->getDefinition(DbalCounterRollup::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'analytics']], $container->getDefinition(CounterSchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testTheSaltLivesInTheNamedPoolUnderTheNamedLockFactory(): void
    {
        $arguments = $this->load(['salt' => ['cache_pool' => 'cache.redis']])->getDefinition(CacheSaltStore::class)->getArguments();

        self::assertEquals(new Reference('cache.redis'), $arguments[0]);
        self::assertEquals(new Reference('lock.factory', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), $arguments[1]);
        self::assertNull($this->load(['salt' => ['lock_factory' => null]])->getDefinition(CacheSaltStore::class)->getArgument(1));
    }

    public function testTheLimiterIsASlidingWindowOfTheConfiguredBudget(): void
    {
        $container = $this->load(['rate_limiter' => ['limit' => 50, 'interval' => '1 minute', 'cache_pool' => 'cache.redis', 'lock_factory' => 'lock.redis']]);
        $factory = $container->getDefinition(AnalyticsServices::LIMITER_FACTORY);

        self::assertSame(['id' => 'phil_analytics_collection', 'policy' => 'sliding_window', 'limit' => 50, 'interval' => '1 minute'], $factory->getArgument(0));
        self::assertEquals(new Reference('lock.redis'), $factory->getArgument(2));
        self::assertEquals([new Reference('cache.redis')], $container->getDefinition('phil_analytics.collection_limiter_storage')->getArguments());
        self::assertNull($this->load([])->getDefinition(AnalyticsServices::LIMITER_FACTORY)->getArgument(2));
    }

    public function testEachAdapterCanBeTheApplicationsService(): void
    {
        $container = $this->load([
            'salt' => ['store' => 'app.salts'],
            'counters' => ['store' => 'app.counters', 'rollup' => 'app.rollup'],
            'rate_limiter' => ['limiter' => 'app.limiter'],
            'collection' => ['projection' => 'app.sites', 'crawler_detector' => 'app.crawlers', 'country_resolver' => 'app.countries', 'outbound_links' => 'app.links'],
        ]);

        foreach ([
            AnalyticsServices::SALT_STORE => 'app.salts',
            CounterStore::class => 'app.counters',
            CounterRollup::class => 'app.rollup',
            CollectionRateLimiter::class => 'app.limiter',
            CollectionProjection::class => 'app.sites',
            CrawlerDetector::class => 'app.crawlers',
            CountryResolver::class => 'app.countries',
            OutboundLinkDirectory::class => 'app.links',
        ] as $contract => $service) {
            self::assertSame($service, (string) $container->getAlias($contract), $contract);
        }

        foreach ([CacheSaltStore::class, DbalCounterStore::class, SymfonyCollectionRateLimiter::class, ConfiguredCollectionProjection::class, UserAgentCrawlerDetector::class] as $adapter) {
            self::assertFalse($container->hasDefinition($adapter), $adapter);
        }

        self::assertTrue($container->hasDefinition(CounterRetentionRun::class));
    }

    public function testAStoreWithoutARollupHasNoRetention(): void
    {
        $container = $this->load(['counters' => ['store' => 'app.counters']]);

        self::assertFalse($container->hasAlias(CounterRollup::class));
        self::assertFalse($container->hasDefinition(CounterRetentionRun::class));
        self::assertTrue($container->hasDefinition(Collector::class));
        self::assertSame(['05:00', false, false, false, false], $container->getDefinition(AnalyticsSchedule::class)->getArguments());
    }

    public function testWithoutAStoreNothingThatCountsIsWired(): void
    {
        $container = $this->load(['counters' => ['connection' => null], 'reports' => ['enabled' => true, 'subscriptions' => 'a', 'sender' => 'b']]);

        foreach ([Collector::class, CollectController::class, ServerEventCounter::class, CounterReader::class, CounterRetentionRun::class, DueReports::class, AnalyticsSchedule::class] as $service) {
            self::assertFalse($container->hasDefinition($service), $service);
        }

        self::assertFalse($container->hasAlias(CounterStore::class));
    }

    public function testTheEndpointCanBeLeftOut(): void
    {
        $container = $this->load(['collection' => ['enabled' => false]]);

        self::assertFalse($container->hasDefinition(Collector::class));
        self::assertFalse($container->hasDefinition(CollectController::class));
        self::assertTrue($container->hasAlias(CounterStore::class));
        self::assertTrue($container->hasDefinition(ServerEventCounter::class));
    }

    public function testTheRetentionCanBeLeftOut(): void
    {
        $container = $this->load(['retention' => ['enabled' => false]]);

        self::assertFalse($container->hasDefinition(CounterRetentionRun::class));
        self::assertSame(['05:00', false, false, false, false], $container->getDefinition(AnalyticsSchedule::class)->getArguments());
    }

    public function testTheRetentionKeepsTheConfiguredMonths(): void
    {
        self::assertSame([24], $this->load(['retention' => ['months' => 24]])->getDefinition(CounterRetention::class)->getArguments());
    }

    public function testReportsGoThroughTheApplicationsServices(): void
    {
        $container = $this->load(['reports' => ['subscriptions' => 'app.subscriptions', 'sender' => 'app.sender'], 'schedule' => ['time' => '23:59']]);

        self::assertEquals([new Reference('app.subscriptions'), new Reference('app.sender')], $container->getDefinition(DueReports::class)->getArguments());
        self::assertTrue($container->getDefinition(SendDueReportsHandler::class)->hasTag('messenger.message_handler'));
        self::assertSame(['23:59', true, true, false, false], $container->getDefinition(AnalyticsSchedule::class)->getArguments());
    }

    public function testBrokenFlowsGoThroughTheNotifierUnlessASenderIsNamed(): void
    {
        $flows = ['checkout' => ['site' => 'spring-con', 'goal' => 'ticket', 'label' => 'Tickets']];
        $container = $this->load(['broken_flows' => ['flows' => $flows, 'baseline_days' => 14, 'window_days' => 3, 'significance' => 0.01, 'weekly' => false]]);

        self::assertEquals([new Reference('notifier')], $container->getDefinition(NotifierAlertSender::class)->getArguments());
        self::assertEquals(new Reference(NotifierAlertSender::class), $container->getDefinition(BrokenFlowWatch::class)->getArgument(1));
        self::assertSame([14, 3, 0.01, false], $container->getDefinition(BrokenFlowRule::class)->getArguments());
        self::assertSame($flows, $container->getDefinition(GoalCompletionHistory::class)->getArgument(1));
        self::assertTrue($container->getDefinition(CheckBrokenFlowsHandler::class)->hasTag('messenger.message_handler'));
        self::assertSame(['05:00', true, false, true, false], $container->getDefinition(AnalyticsSchedule::class)->getArguments());

        $named = $this->load(['broken_flows' => ['flows' => $flows, 'sender' => 'app.alerts']]);

        self::assertFalse($named->hasDefinition(NotifierAlertSender::class));
        self::assertEquals(new Reference('app.alerts'), $named->getDefinition(BrokenFlowWatch::class)->getArgument(1));
    }

    public function testTrafficAlertsGoThroughTheApplicationsNotifier(): void
    {
        $container = $this->load(['traffic_alerts' => ['sites' => ['spring-con'], 'notifier' => 'app.traffic', 'spike_percent' => 250, 'dip_percent' => 40, 'minimum_baseline' => 50, 'baseline_days' => 14]]);

        self::assertSame([250, 40, 50], $container->getDefinition(TrafficAlertRule::class)->getArguments());
        self::assertEquals(
            [new Reference(CounterStore::class), new Reference(TrafficAlertRule::class), new Reference('app.traffic'), ['spring-con'], 14],
            $container->getDefinition(TrafficAlertRun::class)->getArguments(),
        );
        self::assertTrue($container->getDefinition(TrafficAlertRun::class)->isPublic());
        self::assertEquals([new Reference(TrafficAlertRun::class), new Reference('clock')], $container->getDefinition(CheckTrafficAlertsHandler::class)->getArguments());
        self::assertSame([['handles' => CheckTrafficAlerts::class]], $container->getDefinition(CheckTrafficAlertsHandler::class)->getTag('messenger.message_handler'));
        self::assertSame(['05:00', true, false, false, true], $container->getDefinition(AnalyticsSchedule::class)->getArguments());

        $defaults = $this->load(['traffic_alerts' => ['sites' => ['s'], 'notifier' => 'app.traffic']]);

        self::assertSame([300, 30, 20], $defaults->getDefinition(TrafficAlertRule::class)->getArguments());
        self::assertSame(28, $defaults->getDefinition(TrafficAlertRun::class)->getArgument(4));
    }

    public function testThePlanOfASiteReachesTheProjection(): void
    {
        $container = $this->load(['sites' => ['s' => [
            'origins' => ['https://example.com'],
            'clicks' => 'off',
            'downloads' => ['pdf'],
            'dimensions' => ['plan' => ['free', 'pro'], 'checkout-button' => ['a', 'b']],
            'server_events' => ['signup'],
        ]]]);
        $site = $this->siteArgument($container);

        self::assertSame('off', $site['clicks']);
        self::assertSame(['pdf'], $site['downloads']);
        self::assertSame(['plan' => ['free', 'pro'], 'checkout-button' => ['a', 'b']], $site['dimensions']);
        self::assertSame(['signup'], $site['server_events']);

        $defaults = $this->siteArgument($this->load(['sites' => ['s' => ['origins' => ['https://example.com']]]]));

        self::assertSame('aggregate', $defaults['clicks']);
        self::assertNull($defaults['downloads']);
        self::assertSame([], $defaults['dimensions']);
        self::assertSame([], $defaults['server_events']);
    }

    /**
     * @return array<mixed>
     */
    private function siteArgument(ContainerBuilder $container): array
    {
        $sites = $container->getDefinition(ConfiguredCollectionProjection::class)->getArgument(0);
        self::assertIsArray($sites);
        self::assertIsArray($sites['s'] ?? null);

        return $sites['s'];
    }

    public function testTheScheduleCanBeLeftOut(): void
    {
        self::assertFalse($this->load(['schedule' => ['enabled' => false]])->hasDefinition(AnalyticsSchedule::class));
    }

    public function testTheConfiguredSitesReachTheProjection(): void
    {
        $sites = ['spring-con' => ['origins' => ['https://example.com'], 'routes' => ['home' => '/']]];

        $configured = $this->load(['sites' => $sites])->getDefinition(ConfiguredCollectionProjection::class)->getArgument(0);

        self::assertIsArray($configured);
        self::assertSame(['spring-con'], array_keys($configured));
        self::assertIsArray($configured['spring-con']);
        self::assertSame(['https://example.com'], $configured['spring-con']['origins']);
        self::assertSame('active', $configured['spring-con']['status']);
    }

    /**
     * Every service of a full configuration as it is wired: its class, factory, arguments — a reference as "@id" —,
     * tags and visibility.
     */
    public function testEveryServiceIsWiredWithItsArguments(): void
    {
        $container = $this->load(['reports' => ['subscriptions' => 'app.subscriptions', 'sender' => 'app.sender'], 'broken_flows' => ['flows' => []]]);
        $wired = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            if ('service_container' !== $id) {
                $wired[$id] = $this->describe($definition);
            }
        }

        self::assertSame([
            AnalyticsServices::THRESHOLD => [SmallNumberThreshold::class, [SmallNumberThreshold::class, 'of'], [5], [], false],
            SmallNumberSuppression::class => [null, null, ['@'.AnalyticsServices::THRESHOLD], [], true],
            AdmissionRule::class => [null, null, [], [], true],
            CounterRetention::class => [null, null, [13], [], false],
            'phil_analytics.hosts.search' => [HostList::class, [HostList::class, 'of'], [AnalyticsConfiguration::SEARCH_HOSTS], [], false],
            'phil_analytics.hosts.social' => [HostList::class, [HostList::class, 'of'], [AnalyticsConfiguration::SOCIAL_HOSTS], [], false],
            'phil_analytics.hosts.spam' => [HostList::class, [HostList::class, 'of'], [[]], [], false],
            ReferrerClassifier::class => [null, null, ['$search' => '@phil_analytics.hosts.search', '$social' => '@phil_analytics.hosts.social', '$spam' => '@phil_analytics.hosts.spam'], [], true],
            CacheSaltStore::class => [null, null, ['@cache.app', '@lock.factory'], [], false],
            AnalyticsServices::SALT_PROVIDER => [ForgottenSalt::class, null, ['@'.AnalyticsServices::SALT_STORE, '@clock'], [], false],
            ClientDigester::class => [null, null, ['@'.AnalyticsServices::SALT_PROVIDER], [], false],
            'phil_analytics.collection_limiter_storage' => [CacheStorage::class, null, ['@cache.app'], [], false],
            AnalyticsServices::LIMITER_FACTORY => [RateLimiterFactory::class, null, [['id' => 'phil_analytics_collection', 'policy' => 'sliding_window', 'limit' => 600, 'interval' => '10 minutes'], '@phil_analytics.collection_limiter_storage', null], [], false],
            SymfonyCollectionRateLimiter::class => [null, null, ['@'.AnalyticsServices::LIMITER_FACTORY], [], false],
            MeasureCounters::class => [null, null, ['@'.OutboundLinkDirectory::class], [], false],
            ConfiguredCollectionProjection::class => [null, null, [[]], [], false],
            UserAgentCrawlerDetector::class => [null, null, [], [], false],
            UnknownCountryResolver::class => [null, null, [], [], false],
            NoOutboundLinks::class => [null, null, [], [], false],
            DbalCounterStore::class => [null, null, ['@doctrine.dbal.default_connection'], [], false],
            DbalCounterRollup::class => [null, null, ['@doctrine.dbal.default_connection'], [], false],
            CounterSchemaListener::class => [null, null, ['@doctrine.dbal.default_connection'], ['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], false],
            Collector::class => [null, null, ['@'.CollectionProjection::class, '@'.CollectionRateLimiter::class, '@'.ClientDigester::class, '@'.ReferrerClassifier::class, '@'.AdmissionRule::class, '@'.CrawlerDetector::class, '@'.CountryResolver::class, '@'.CounterStore::class, '@'.MeasureCounters::class, '@clock'], [], true],
            CollectController::class => [null, null, ['@'.Collector::class], ['controller.service_arguments' => [[]]], true],
            ServerEventCounter::class => [null, null, ['@'.CollectionProjection::class, '@'.CounterStore::class, '@clock'], [], true],
            CounterReader::class => [null, null, ['@'.CounterStore::class, '@'.SmallNumberSuppression::class], [], true],
            CounterRetentionRun::class => [null, null, ['@'.CounterRollup::class, '@'.CounterRetention::class, '@'.AnalyticsServices::THRESHOLD], [], true],
            ApplyCounterRetentionHandler::class => [null, null, ['@'.CounterRetentionRun::class, '@clock'], ['messenger.message_handler' => [['handles' => ApplyCounterRetention::class]]], false],
            DueReports::class => [null, null, ['@app.subscriptions', '@app.sender'], [], true],
            SendDueReportsHandler::class => [null, null, ['@'.DueReports::class, '@clock'], ['messenger.message_handler' => [['handles' => SendDueReports::class]]], false],
            NotifierAlertSender::class => [null, null, ['@notifier'], [], false],
            GoalCompletionHistory::class => [null, null, ['@'.CounterStore::class, []], [], true],
            BrokenFlowRule::class => [null, null, [28, 7, 0.001, true], [], false],
            BrokenFlowWatch::class => [null, null, ['@'.GoalCompletionHistory::class, '@'.NotifierAlertSender::class, '@clock', '@'.BrokenFlowRule::class], [], true],
            CheckBrokenFlowsHandler::class => [null, null, ['@'.BrokenFlowWatch::class, '@'.GoalCompletionHistory::class], ['messenger.message_handler' => [['handles' => CheckBrokenFlows::class]]], false],
            AnalyticsSchedule::class => [null, null, ['05:00', true, true, true, false], ['scheduler.schedule_provider' => [['name' => 'phil_analytics']]], false],
        ], $wired);
    }

    public function testTheLowestSettingsAreAccepted(): void
    {
        $container = $this->load([
            'small_number_threshold' => 2,
            'rate_limiter' => ['limit' => 1],
            'retention' => ['months' => 1],
            'broken_flows' => ['baseline_days' => 7, 'window_days' => 1],
        ]);

        self::assertSame([2], $container->getDefinition(AnalyticsServices::THRESHOLD)->getArguments());
        $limiter = $container->getDefinition(AnalyticsServices::LIMITER_FACTORY)->getArgument(0);

        self::assertIsArray($limiter);
        self::assertSame(1, $limiter['limit']);
        self::assertSame([1], $container->getDefinition(CounterRetention::class)->getArguments());
        self::assertSame([7, 1], \array_slice($container->getDefinition(BrokenFlowRule::class)->getArguments(), 0, 2));
    }

    public function testTheLongestBrokenFlowSettingsAreAccepted(): void
    {
        $container = $this->load(['broken_flows' => ['baseline_days' => 366, 'window_days' => 31]]);

        self::assertSame([366, 31], \array_slice($container->getDefinition(BrokenFlowRule::class)->getArguments(), 0, 2));
    }

    /**
     * A key with a dash stays as written: a route, a goal or a flow is named by the application, not by the config.
     */
    public function testNamesWithADashAreKept(): void
    {
        $sites = ['s' => ['origins' => ['https://example.com'], 'routes' => ['talk-page' => '/talks/{slug}'], 'status' => 'suspended', 'goals' => ['sign-up' => ['route' => 'talk-page']]]];
        $container = $this->load(['sites' => $sites, 'broken_flows' => ['flows' => ['check-out' => ['site' => 's', 'goal' => 'sign-up']]]]);

        $sites = $container->getDefinition(ConfiguredCollectionProjection::class)->getArgument(0);

        self::assertIsArray($sites);
        $site = $sites['s'];
        self::assertIsArray($site);
        self::assertSame(['talk-page' => '/talks/{slug}'], $site['routes']);
        self::assertSame('suspended', $site['status']);
        self::assertSame(['sign-up' => ['route' => 'talk-page', 'event' => null, 'value' => null]], $site['goals']);
        self::assertSame(['check-out' => ['site' => 's', 'goal' => 'sign-up', 'label' => null]], $container->getDefinition(GoalCompletionHistory::class)->getArgument(1));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'reports without a sender' => [['reports' => ['enabled' => true, 'subscriptions' => 'a']], 'Reports need the ids of the ReportSubscriptions and ReportSender services'];
        yield 'reports without subscriptions' => [['reports' => ['enabled' => true, 'sender' => 'b']], 'Reports need the ids of the ReportSubscriptions and ReportSender services'];
        yield 'a time out of the day' => [['schedule' => ['time' => '24:00']], 'The time of the jobs is HH:MM, in UTC'];
        yield 'a time without minutes' => [['schedule' => ['time' => '5']], 'The time of the jobs is HH:MM, in UTC'];
        yield 'a goal with a route and an event' => [self::goal(['route' => 'home', 'event' => 'click', 'value' => 'x']), 'A goal names a declared route, or a measured event and its value'];
        yield 'a goal with a route and a value' => [self::goal(['route' => 'home', 'value' => 'x']), 'A goal names a declared route, or a measured event and its value'];
        yield 'a goal with an event and no value' => [self::goal(['event' => 'click']), 'A goal names a declared route, or a measured event and its value'];
        yield 'a goal with a value and no event' => [self::goal(['value' => 'x']), 'A goal names a declared route, or a measured event and its value'];
        yield 'an empty goal' => [self::goal([]), 'A goal names a declared route, or a measured event and its value'];
        yield 'a significance of zero' => [['broken_flows' => ['significance' => 0.0]], 'The significance of a broken flow is above 0 and under 0.5'];
        yield 'a significance of one half' => [['broken_flows' => ['significance' => 0.5]], 'The significance of a broken flow is above 0 and under 0.5'];
        yield 'a site without origins' => [['sites' => ['s' => ['origins' => []]]], 'should have at least 1 element'];
        yield 'a time after other text' => [['schedule' => ['time' => 'x05:00']], 'The time of the jobs is HH:MM, in UTC'];
        yield 'a time before other text' => [['schedule' => ['time' => '05:00x']], 'The time of the jobs is HH:MM, in UTC'];
        yield 'a time before a line feed' => [['schedule' => ['time' => "05:00\n"]], 'The time of the jobs is HH:MM, in UTC'];
        yield 'a threshold of one' => [['small_number_threshold' => 1], 'Should be greater than or equal to 2'];
        yield 'a limit of zero' => [['rate_limiter' => ['limit' => 0]], 'Should be greater than or equal to 1'];
        yield 'a retention of zero months' => [['retention' => ['months' => 0]], 'Should be greater than or equal to 1'];
        yield 'a baseline under a week' => [['broken_flows' => ['baseline_days' => 6]], 'Should be greater than or equal to 7'];
        yield 'a baseline over a year' => [['broken_flows' => ['baseline_days' => 367]], 'Should be less than or equal to 366'];
        yield 'an empty window' => [['broken_flows' => ['window_days' => 0]], 'Should be greater than or equal to 1'];
        yield 'a window over a month' => [['broken_flows' => ['window_days' => 32]], 'Should be less than or equal to 31'];
        yield 'traffic alerts without a notifier' => [['traffic_alerts' => ['sites' => ['s']]], 'Traffic alerts need the id of a TrafficAlertNotifier service and at least one watched site'];
        yield 'traffic alerts without a site' => [['traffic_alerts' => ['notifier' => 'app.traffic']], 'Traffic alerts need the id of a TrafficAlertNotifier service and at least one watched site'];
        yield 'a spike at the usual day' => [['traffic_alerts' => ['spike_percent' => 100]], 'Should be greater than or equal to 101'];
        yield 'a dip of nothing' => [['traffic_alerts' => ['dip_percent' => 0]], 'Should be greater than or equal to 1'];
        yield 'a dip at the usual day' => [['traffic_alerts' => ['dip_percent' => 100]], 'Should be less than or equal to 99'];
        yield 'a minimum usual day of zero' => [['traffic_alerts' => ['minimum_baseline' => 0]], 'Should be greater than or equal to 1'];
        yield 'a traffic baseline under a week' => [['traffic_alerts' => ['baseline_days' => 6]], 'Should be greater than or equal to 7'];
        yield 'a traffic baseline over a year' => [['traffic_alerts' => ['baseline_days' => 367]], 'Should be less than or equal to 366'];
        yield 'an unknown click level' => [['sites' => ['s' => ['origins' => ['https://example.com'], 'clicks' => 'per_person']]], 'The value "per_person" is not allowed'];
        yield 'a dimension without values' => [['sites' => ['s' => ['origins' => ['https://example.com'], 'dimensions' => ['plan' => []]]]], 'should have at least 1 element'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refused')]
    public function testARefusedConfigurationSaysWhy(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testBothKindsOfGoalAreAccepted(): void
    {
        $container = $this->load(self::goal(['route' => 'home']));
        $event = $this->load(self::goal(['event' => 'form_submit', 'value' => 'contact']));

        self::assertTrue($container->hasDefinition(ConfiguredCollectionProjection::class));
        self::assertTrue($event->hasDefinition(ConfiguredCollectionProjection::class));
    }

    /**
     * @param array<string, string> $goal
     *
     * @return array<string, mixed>
     */
    private static function goal(array $goal): array
    {
        return ['sites' => ['s' => ['origins' => ['https://example.com'], 'goals' => ['g' => $goal]]]];
    }

    /**
     * @return array{?string, mixed, mixed, array<mixed>, bool}
     */
    private function describe(Definition $definition): array
    {
        return [$definition->getClass(), self::plain($definition->getFactory()), self::plain($definition->getArguments()), $definition->getTags(), $definition->isPublic()];
    }

    private static function plain(mixed $value): mixed
    {
        if ($value instanceof Reference) {
            return '@'.$value;
        }

        return \is_array($value) ? array_map(self::plain(...), $value) : $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilAnalyticsBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
