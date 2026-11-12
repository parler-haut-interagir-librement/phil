<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\DependencyInjection;

use Phil\AnalyticsBundle\Application\Alert\TrafficAlertRun;
use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Measure\ClickTracking;
use Phil\AnalyticsBundle\Domain\Retention\CounterRetention;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

/**
 * The tree of `phil_analytics`. Each adapter is named by a keyword for the bundle's own, or by the id of a service of
 * the application that implements the same contract.
 */
final class AnalyticsConfiguration
{
    public const string STORE_DBAL = 'dbal';

    public const string SALT_CACHE = 'cache';

    public const string LIMITER_SYMFONY = 'symfony';

    public const string PROJECTION_CONFIG = 'config';

    public const string CRAWLERS_USER_AGENT = 'user_agent';

    public const string COUNTRIES_UNKNOWN = 'unknown';

    public const string LINKS_NONE = 'none';

    /**
     * The search engines a referrer is classified under by default; `{}` stands for any public suffix.
     */
    public const array SEARCH_HOSTS = ['google.{}', 'bing.com', 'duckduckgo.com', 'search.yahoo.com', 'yandex.{}', 'baidu.com', 'ecosia.org', 'qwant.com', 'search.brave.com', 'startpage.com'];

    /**
     * The social networks a referrer is classified under by default.
     */
    public const array SOCIAL_HOSTS = ['facebook.com', 'instagram.com', 'linkedin.com', 'reddit.com', 'x.com', 'twitter.com', 't.co', 'youtube.com', 'pinterest.com', 'threads.net', 'bsky.app', 'mastodon.social'];

    public static function define(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();

        $children
            ->integerNode('small_number_threshold')
                ->info('The smallest counter shown on its own when counters are read, at least 2.')
                ->min(2)
                ->defaultValue(5)
            ->end()
            ->booleanNode('single_server')
                ->info('Accepts a cache pool local to the server for the salt and the limiter in the prod environment, for an application on one server.')
                ->defaultFalse()
            ->end()
            ->arrayNode('hosts')
                ->info('The host lists the referrer is classified with; "{}" stands for any public suffix.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('search')->scalarPrototype()->cannotBeEmpty()->end()->defaultValue(self::SEARCH_HOSTS)->end()
                    ->arrayNode('social')->scalarPrototype()->cannotBeEmpty()->end()->defaultValue(self::SOCIAL_HOSTS)->end()
                    ->arrayNode('spam')->scalarPrototype()->cannotBeEmpty()->end()->end()
                ->end()
            ->end();

        self::sites($children);
        self::adapters($children);
        self::jobs($children);
    }

    private static function sites(NodeBuilder $children): void
    {
        $children
            ->arrayNode('sites')
                ->info('The sites the "config" projection declares, by site reference.')
                ->useAttributeAsKey('reference')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->arrayNode('origins')
                            ->info('The origins a collection request of the site comes from, such as https://example.com.')
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->arrayNode('routes')
                            ->info('The declared routes, by name: a path pattern with {placeholders}; any other path is counted under "(other)".')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->enumNode('status')
                            ->values(array_map(static fn (SiteStatus $status): string => $status->value, SiteStatus::cases()))
                            ->defaultValue(SiteStatus::Active->value)
                        ->end()
                        ->arrayNode('goals')
                            ->info('The goals, by name: a declared route, or a measured event and its value.')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('route')->defaultNull()->end()
                                    ->scalarNode('event')->defaultNull()->end()
                                    ->scalarNode('value')->defaultNull()->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $goal): bool => !self::isGoal($goal))
                                    ->thenInvalid('A goal names a declared route, or a measured event and its value: %s')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('forms')->info('The forms whose submissions are counted.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->arrayNode('click_names')->info('The click names counted.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->enumNode('clicks')
                            ->info('"aggregate", a counter per declared name, tied to nobody; "off", no click, download or outbound link measured.')
                            ->values(array_map(static fn (ClickTracking $level): string => $level->value, ClickTracking::cases()))
                            ->defaultValue(ClickTracking::Aggregate->value)
                        ->end()
                        ->arrayNode('downloads')
                            ->info('The file extensions counted as downloads; null for the default list.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultNull()
                        ->end()
                        ->arrayNode('dimensions')
                            ->info('The declared dimensions, by name, each with its closed list of values: the variant of an experiment, a plan, a language.')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->arrayPrototype()->requiresAtLeastOneElement()->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->end()
                        ->arrayNode('server_events')
                            ->info("The events the application's server counts for the site, through ServerEventCounter.")
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private static function adapters(NodeBuilder $children): void
    {
        $children
            ->arrayNode('salt')
                ->info('The daily salt of the client digest the limiter keys on.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('store')
                        ->info('"cache", a cache pool that forgets each salt at the end of its day; or the id of a service implementing ExpiringSaltStore.')
                        ->defaultValue(self::SALT_CACHE)
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('cache_pool')
                        ->info('The pool of the "cache" store: shared by every server, and never persisted.')
                        ->defaultValue('cache.app')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('lock_factory')
                        ->info('The lock factory that makes the first claim of a day atomic between servers; null for none.')
                        ->defaultValue('lock.factory')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('counters')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('store')
                        ->info('"dbal", two tables of a Doctrine connection; or the id of a service implementing CounterStore.')
                        ->defaultValue(self::STORE_DBAL)
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('rollup')
                        ->info('The id of a service implementing CounterRollup, when the store is not "dbal"; null for no retention.')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('connection')
                        ->info('The Doctrine connection of the "dbal" store; without DoctrineBundle, none, and nothing that counts is wired until a store is named.')
                        ->defaultValue('default')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('rate_limiter')
                ->info('The collection budget, per site and client digest.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('limiter')
                        ->info('"symfony", a Symfony Rate Limiter sliding window; or the id of a service implementing CollectionRateLimiter.')
                        ->defaultValue(self::LIMITER_SYMFONY)
                        ->cannotBeEmpty()
                    ->end()
                    ->integerNode('limit')
                        ->info('Sends per interval: calibrate it on a group behind one address, counting several sends per page.')
                        ->min(1)
                        ->defaultValue(600)
                    ->end()
                    ->scalarNode('interval')->info('The window, such as "10 minutes".')->defaultValue('10 minutes')->cannotBeEmpty()->end()
                    ->scalarNode('cache_pool')->info("The pool of the limiter's state: never persisted.")->defaultValue('cache.app')->cannotBeEmpty()->end()
                    ->scalarNode('lock_factory')->info('A lock factory for exact counts under concurrency; null for none.')->defaultNull()->end()
                ->end()
            ->end()
            ->arrayNode('collection')
                ->info('The collection endpoint, route phil_analytics_collect, and the adapters it reads.')
                ->canBeDisabled()
                ->children()
                    ->scalarNode('projection')
                        ->info('"config", the sites declared under phil_analytics.sites; or the id of a service implementing CollectionProjection.')
                        ->defaultValue(self::PROJECTION_CONFIG)
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('crawler_detector')
                        ->info('"user_agent", the crawlers that say so in their user agent; or the id of a service implementing CrawlerDetector.')
                        ->defaultValue(self::CRAWLERS_USER_AGENT)
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('country_resolver')
                        ->info('"unknown", every country unknown; or the id of a service implementing CountryResolver.')
                        ->defaultValue(self::COUNTRIES_UNKNOWN)
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('outbound_links')
                        ->info('"none", outbound clicks counted by host only; or the id of a service implementing OutboundLinkDirectory.')
                        ->defaultValue(self::LINKS_NONE)
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end();
    }

    private static function jobs(NodeBuilder $children): void
    {
        $children
            ->arrayNode('retention')
                ->info('Daily counters rolled up into monthly totals past their retention, once a day.')
                ->canBeDisabled()
                ->children()
                    ->integerNode('months')->min(1)->defaultValue(CounterRetention::DEFAULT_MONTHS)->end()
                ->end()
            ->end()
            ->arrayNode('reports')
                ->info('Weekly reports on Mondays and monthly reports on the first, sent by the application.')
                ->canBeEnabled()
                ->children()
                    ->scalarNode('subscriptions')->info('The id of a service implementing ReportSubscriptions.')->defaultNull()->end()
                    ->scalarNode('sender')->info('The id of a service implementing ReportSender.')->defaultNull()->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $reports): bool => true === $reports['enabled'] && (null === $reports['subscriptions'] || null === $reports['sender']))
                    ->thenInvalid('Reports need the ids of the ReportSubscriptions and ReportSender services: %s')
                ->end()
            ->end()
            ->arrayNode('broken_flows')
                ->info('An alert when a goal stops being reached, with phil/broken-flow-alerts: passive, from the goal counters.')
                ->canBeEnabled()
                ->children()
                    ->arrayNode('flows')
                        ->info('The goals watched, by flow identifier.')
                        ->useAttributeAsKey('id')
                        ->normalizeKeys(false)
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('site')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('goal')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('label')->defaultNull()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->integerNode('baseline_days')->min(7)->max(366)->defaultValue(28)->end()
                    ->integerNode('window_days')->min(1)->max(31)->defaultValue(7)->end()
                    ->floatNode('significance')
                        ->info('How unlikely the drop must be in a working flow: above 0 and under 0.5.')
                        ->defaultValue(0.001)
                        ->validate()
                            ->ifTrue(static fn (float $significance): bool => $significance <= 0.0 || $significance >= 0.5)
                            ->thenInvalid('The significance of a broken flow is above 0 and under 0.5: %s')
                        ->end()
                    ->end()
                    ->booleanNode('weekly')->defaultTrue()->end()
                    ->scalarNode('sender')
                        ->info('The id of a service implementing AlertSender; null, the alerts go through the notifier service.')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('traffic_alerts')
                ->info("An alert when a watched site's visits of the day before spike or dip against its usual day.")
                ->canBeEnabled()
                ->children()
                    ->arrayNode('sites')
                        ->info('The references of the watched sites.')
                        ->scalarPrototype()->cannotBeEmpty()->end()
                    ->end()
                    ->scalarNode('notifier')->info('The id of a service implementing TrafficAlertNotifier.')->defaultNull()->end()
                    ->integerNode('spike_percent')->info('A day at or above this share of the usual day is a spike.')->min(101)->defaultValue(300)->end()
                    ->integerNode('dip_percent')->info('A day at or below this share of the usual day is a dip.')->min(1)->max(99)->defaultValue(30)->end()
                    ->integerNode('minimum_baseline')->info('A site whose usual day is under this many visits raises no alert.')->min(1)->defaultValue(20)->end()
                    ->integerNode('baseline_days')
                        ->info('The days the usual day is the mean of.')
                        ->min(TrafficAlertRun::MIN_BASELINE_DAYS)
                        ->max(TrafficAlertRun::MAX_BASELINE_DAYS)
                        ->defaultValue(28)
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $alerts): bool => true === $alerts['enabled'] && (null === $alerts['notifier'] || [] === $alerts['sites']))
                    ->thenInvalid('Traffic alerts need the id of a TrafficAlertNotifier service and at least one watched site: %s')
                ->end()
            ->end()
            ->arrayNode('schedule')
                ->info('The schedule phil_analytics: the retention, the reports and the flow check, once a day.')
                ->canBeDisabled()
                ->children()
                    ->scalarNode('time')
                        ->info('The UTC time of day the jobs run at, HH:MM.')
                        ->defaultValue('05:00')
                        ->validate()
                            ->ifTrue(static fn (mixed $time): bool => !\is_string($time) || 1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $time))
                            ->thenInvalid('The time of the jobs is HH:MM, in UTC: %s')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $goal
     */
    private static function isGoal(array $goal): bool
    {
        $route = null !== ($goal['route'] ?? null);
        $event = null !== ($goal['event'] ?? null);
        $value = null !== ($goal['value'] ?? null);

        return $route ? !$event && !$value : $event && $value;
    }
}
