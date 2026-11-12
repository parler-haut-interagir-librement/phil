<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
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
use Phil\BrokenFlowAlerts\BrokenFlowRule;
use Phil\BrokenFlowAlerts\BrokenFlowWatch;
use Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender;
use Phil\IpAnonymizer\ForgottenSalt;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The services of `phil_analytics`: the domain services from the configuration, then each adapter — the bundle's own,
 * or an alias to the application's service named in its place —, the collection endpoint, and the daily jobs.
 *
 * @phpstan-type Config array{
 *     small_number_threshold: int,
 *     single_server: bool,
 *     hosts: array{search: list<string>, social: list<string>, spam: list<string>},
 *     sites: array<string, mixed>,
 *     salt: array{store: string, cache_pool: string, lock_factory: ?string},
 *     counters: array{store: string, rollup: ?string, connection: ?string},
 *     rate_limiter: array{limiter: string, limit: int, interval: string, cache_pool: string, lock_factory: ?string},
 *     collection: array{enabled: bool, projection: string, crawler_detector: string, country_resolver: string, outbound_links: string},
 *     retention: array{enabled: bool, months: int},
 *     reports: array{enabled: bool, subscriptions: ?string, sender: ?string},
 *     broken_flows: array{enabled: bool, flows: array<string, mixed>, baseline_days: int, window_days: int, significance: float, weekly: bool, sender: ?string},
 *     traffic_alerts: array{enabled: bool, sites: list<string>, notifier: ?string, spike_percent: int, dip_percent: int, minimum_baseline: int, baseline_days: int},
 *     schedule: array{enabled: bool, time: string}
 * }
 */
final class AnalyticsServices
{
    public const string THRESHOLD = 'phil_analytics.small_number_threshold';

    public const string SALT_PROVIDER = 'phil_analytics.salt_provider';

    public const string SALT_STORE = 'phil_analytics.salt_store';

    public const string LIMITER_FACTORY = 'phil_analytics.collection_limiter_factory';

    /**
     * @param Config $config
     */
    public static function load(array $config, ServicesConfigurator $services): void
    {
        $clock = new Reference('clock');

        self::domain($config, $services);
        self::salt($config['salt'], $services, $clock);
        self::limiter($config['rate_limiter'], $services);
        $services->set(MeasureCounters::class)->args([new Reference(OutboundLinkDirectory::class)]);
        self::collectionAdapters($config, $services);

        $counters = self::counters($config['counters'], $services);

        if (CounterWiring::None === $counters) {
            // No store yet: nothing that counts or reads the counters is wired.
            return;
        }

        if ($config['collection']['enabled']) {
            self::collection($services, $clock);
        }

        $services->set(ServerEventCounter::class)
            ->args([new Reference(CollectionProjection::class), new Reference(CounterStore::class), $clock])
            ->public();
        $services->set(CounterReader::class)
            ->args([new Reference(CounterStore::class), new Reference(SmallNumberSuppression::class)])
            ->public();

        $retention = $config['retention']['enabled'] && CounterWiring::WithRollup === $counters;

        if ($retention) {
            $services->set(CounterRetentionRun::class)
                ->args([new Reference(CounterRollup::class), new Reference(CounterRetention::class), new Reference(self::THRESHOLD)])
                ->public();
            $services->set(ApplyCounterRetentionHandler::class)
                ->args([new Reference(CounterRetentionRun::class), $clock])
                ->tag('messenger.message_handler', ['handles' => ApplyCounterRetention::class]);
        }

        if ($config['reports']['enabled']) {
            $services->set(DueReports::class)
                ->args([new Reference((string) $config['reports']['subscriptions']), new Reference((string) $config['reports']['sender'])])
                ->public();
            $services->set(SendDueReportsHandler::class)->args([new Reference(DueReports::class), $clock])->tag('messenger.message_handler', ['handles' => SendDueReports::class]);
        }

        if ($config['broken_flows']['enabled']) {
            self::brokenFlows($config['broken_flows'], $services, $clock);
        }

        if ($config['traffic_alerts']['enabled']) {
            self::trafficAlerts($config['traffic_alerts'], $services, $clock);
        }

        if ($config['schedule']['enabled'] && interface_exists(ScheduleProviderInterface::class)) {
            $services->set(AnalyticsSchedule::class)
                ->args([$config['schedule']['time'], $retention, $config['reports']['enabled'], $config['broken_flows']['enabled'], $config['traffic_alerts']['enabled']])
                ->tag('scheduler.schedule_provider', ['name' => AnalyticsSchedule::NAME]);
        }
    }

    /**
     * @param Config $config
     */
    private static function domain(array $config, ServicesConfigurator $services): void
    {
        $services->set(self::THRESHOLD, SmallNumberThreshold::class)
            ->factory([SmallNumberThreshold::class, 'of'])
            ->args([$config['small_number_threshold']]);
        $services->set(SmallNumberSuppression::class)->args([new Reference(self::THRESHOLD)])->public();
        $services->set(AdmissionRule::class)->public();
        $services->set(CounterRetention::class)->args([$config['retention']['months']]);

        $lists = [];

        foreach ($config['hosts'] as $kind => $hosts) {
            $lists['$'.$kind] = new Reference('phil_analytics.hosts.'.$kind);
            $services->set('phil_analytics.hosts.'.$kind, HostList::class)->factory([HostList::class, 'of'])->args([$hosts]);
        }

        $services->set(ReferrerClassifier::class)->args($lists)->public();
    }

    /**
     * @param array{store: string, cache_pool: string, lock_factory: ?string} $salt
     */
    private static function salt(array $salt, ServicesConfigurator $services, Reference $clock): void
    {
        if (AnalyticsConfiguration::SALT_CACHE === $salt['store']) {
            $services->set(CacheSaltStore::class)->args([
                new Reference($salt['cache_pool']),
                null === $salt['lock_factory'] ? null : new Reference($salt['lock_factory'], ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ]);
            $services->alias(self::SALT_STORE, CacheSaltStore::class);
        } else {
            $services->alias(self::SALT_STORE, $salt['store']);
        }

        $services->set(self::SALT_PROVIDER, ForgottenSalt::class)->args([new Reference(self::SALT_STORE), $clock]);
        $services->set(ClientDigester::class)->args([new Reference(self::SALT_PROVIDER)]);
    }

    /**
     * @param array{store: string, rollup: ?string, connection: ?string} $counters
     */
    private static function counters(array $counters, ServicesConfigurator $services): CounterWiring
    {
        if (AnalyticsConfiguration::STORE_DBAL !== $counters['store']) {
            $services->alias(CounterStore::class, $counters['store'])->public();

            if (null === $counters['rollup']) {
                return CounterWiring::StoreOnly;
            }

            $services->alias(CounterRollup::class, $counters['rollup'])->public();

            return CounterWiring::WithRollup;
        }

        if (null === $counters['connection']) {
            return CounterWiring::None;
        }

        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $counters['connection']));
        $services->set(DbalCounterStore::class)->args([$connection]);
        $services->set(DbalCounterRollup::class)->args([$connection]);
        $services->alias(CounterStore::class, DbalCounterStore::class)->public();
        $services->alias(CounterRollup::class, DbalCounterRollup::class)->public();

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $services->set(CounterSchemaListener::class)
                ->args([$connection])
                ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $counters['connection']]);
        }

        return CounterWiring::WithRollup;
    }

    /**
     * @param array{limiter: string, limit: int, interval: string, cache_pool: string, lock_factory: ?string} $limiter
     */
    private static function limiter(array $limiter, ServicesConfigurator $services): void
    {
        if (AnalyticsConfiguration::LIMITER_SYMFONY !== $limiter['limiter']) {
            $services->alias(CollectionRateLimiter::class, $limiter['limiter'])->public();

            return;
        }

        $services->set('phil_analytics.collection_limiter_storage', CacheStorage::class)->args([new Reference($limiter['cache_pool'])]);
        $services->set(self::LIMITER_FACTORY, RateLimiterFactory::class)->args([
            ['id' => 'phil_analytics_collection', 'policy' => 'sliding_window', 'limit' => $limiter['limit'], 'interval' => $limiter['interval']],
            new Reference('phil_analytics.collection_limiter_storage'),
            null === $limiter['lock_factory'] ? null : new Reference($limiter['lock_factory']),
        ]);
        $services->set(SymfonyCollectionRateLimiter::class)->args([new Reference(self::LIMITER_FACTORY)]);
        $services->alias(CollectionRateLimiter::class, SymfonyCollectionRateLimiter::class)->public();
    }

    /**
     * The adapters collection reads besides the counters: the bundle's own, or aliases to the application's.
     *
     * @param Config $config
     */
    private static function collectionAdapters(array $config, ServicesConfigurator $services): void
    {
        $collection = $config['collection'];

        foreach ([
            CollectionProjection::class => [$collection['projection'], AnalyticsConfiguration::PROJECTION_CONFIG, ConfiguredCollectionProjection::class, [$config['sites']]],
            CrawlerDetector::class => [$collection['crawler_detector'], AnalyticsConfiguration::CRAWLERS_USER_AGENT, UserAgentCrawlerDetector::class, []],
            CountryResolver::class => [$collection['country_resolver'], AnalyticsConfiguration::COUNTRIES_UNKNOWN, UnknownCountryResolver::class, []],
            OutboundLinkDirectory::class => [$collection['outbound_links'], AnalyticsConfiguration::LINKS_NONE, NoOutboundLinks::class, []],
        ] as $contract => [$chosen, $keyword, $adapter, $arguments]) {
            if ($chosen === $keyword) {
                $services->set($adapter)->args($arguments);
                $chosen = $adapter;
            }

            $services->alias($contract, $chosen)->public();
        }
    }

    private static function collection(ServicesConfigurator $services, Reference $clock): void
    {
        $services->set(Collector::class)
            ->args([
                new Reference(CollectionProjection::class),
                new Reference(CollectionRateLimiter::class),
                new Reference(ClientDigester::class),
                new Reference(ReferrerClassifier::class),
                new Reference(AdmissionRule::class),
                new Reference(CrawlerDetector::class),
                new Reference(CountryResolver::class),
                new Reference(CounterStore::class),
                new Reference(MeasureCounters::class),
                $clock,
            ])
            ->public();
        $services->set(CollectController::class)
            ->args([new Reference(Collector::class)])
            ->tag('controller.service_arguments')
            ->public();
    }

    /**
     * @param array{enabled: bool, flows: array<string, mixed>, baseline_days: int, window_days: int, significance: float, weekly: bool, sender: ?string} $flows
     */
    private static function brokenFlows(array $flows, ServicesConfigurator $services, Reference $clock): void
    {
        if (!class_exists(BrokenFlowWatch::class)) {
            throw new InvalidConfigurationException('phil_analytics.broken_flows needs phil/broken-flow-alerts: composer require phil/broken-flow-alerts.');
        }

        $sender = $flows['sender'];

        if (null === $sender) {
            $services->set(NotifierAlertSender::class)->args([new Reference('notifier')]);
            $sender = NotifierAlertSender::class;
        }

        $services->set(GoalCompletionHistory::class)->args([new Reference(CounterStore::class), $flows['flows']])->public();
        $services->set(BrokenFlowRule::class)->args([$flows['baseline_days'], $flows['window_days'], $flows['significance'], $flows['weekly']]);
        $services->set(BrokenFlowWatch::class)
            ->args([new Reference(GoalCompletionHistory::class), new Reference($sender), $clock, new Reference(BrokenFlowRule::class)])
            ->public();
        $services->set(CheckBrokenFlowsHandler::class)
            ->args([new Reference(BrokenFlowWatch::class), new Reference(GoalCompletionHistory::class)])
            ->tag('messenger.message_handler', ['handles' => CheckBrokenFlows::class]);
    }

    /**
     * @param array{enabled: bool, sites: list<string>, notifier: ?string, spike_percent: int, dip_percent: int, minimum_baseline: int, baseline_days: int} $alerts
     */
    private static function trafficAlerts(array $alerts, ServicesConfigurator $services, Reference $clock): void
    {
        $services->set(TrafficAlertRule::class)->args([$alerts['spike_percent'], $alerts['dip_percent'], $alerts['minimum_baseline']]);
        $services->set(TrafficAlertRun::class)
            ->args([
                new Reference(CounterStore::class),
                new Reference(TrafficAlertRule::class),
                new Reference((string) $alerts['notifier']),
                $alerts['sites'],
                $alerts['baseline_days'],
            ])
            ->public();
        $services->set(CheckTrafficAlertsHandler::class)
            ->args([new Reference(TrafficAlertRun::class), $clock])
            ->tag('messenger.message_handler', ['handles' => CheckTrafficAlerts::class]);
    }
}
