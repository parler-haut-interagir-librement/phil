<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle;

use Override;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeterBundle\Infrastructure\DependencyInjection\UsageMeterServices;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/usage-meter` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_usage_meter:
 *     store: dbal
 *     meters:
 *         exports: { limit: 3, calendar: month, timezone: America/Toronto }
 *         requests: { limit: 600, rolling: 3600, buckets: 60 }
 *     rate_limiters:
 *         api: { meter: requests, key: ip }
 * ```
 *
 * The bundle wires the meters, the store — two tables of a DBAL connection that join the schema the ORM generates, the
 * memory of one process, or the application's —, the limit providers in a chain, an anchor provider, `UsageMeter`,
 * the `phil:usage-meter:purge` command, and the limiters of `phil/rate-limit-bundle` that count the requests of the
 * controllers, routes and forms it limits on a meter.
 */
final class PhilUsageMeterBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_usage_meter';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();

        $children
            ->scalarNode('store')
                ->info('Where the counters live: "dbal", two tables of a DBAL connection; "memory", one process; or the id of a service implementing UsageStore.')
                ->defaultValue(UsageMeterServices::STORE_DBAL)
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('dbal')
                ->info('The DBAL store: its connection and its two tables, added to the schema the ORM generates.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('connection')->defaultValue('default')->cannotBeEmpty()->end()
                    ->scalarNode('counter_table')->defaultValue(DbalUsageStore::COUNTER_TABLE)->cannotBeEmpty()->end()
                    ->scalarNode('key_table')->defaultValue(DbalUsageStore::KEY_TABLE)->cannotBeEmpty()->end()
                ->end()
            ->end()
            ->scalarNode('clock')
                ->info('Service id of the PSR-20 clock of the windows.')
                ->defaultValue('clock')
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('limit_providers')
                ->info("Service ids of the limit providers, asked in order before the meter's own limit; the first that names a limit wins.")
                ->scalarPrototype()->cannotBeEmpty()->end()
            ->end()
            ->arrayNode('subscription_features')
                ->info("Limits read from the features of the subscriber's offers with phil/commerce-subscription-bundle, written <prefix><meter>:<limit>; asked after the limit providers.")
                ->canBeEnabled()
                ->children()
                    ->scalarNode('prefix')->defaultValue('usage:')->cannotBeEmpty()->end()
                ->end()
            ->end()
            ->scalarNode('anchor_provider')
                ->info("Service id of the anchor provider: each subject's own anchor for the meters counted by billing cycle.")
                ->defaultNull()
            ->end();

        $this->meters($children);
        $this->rateLimiters($children);

        $children
            ->scalarNode('idempotency_secret')
                ->info('The key of the hashes the idempotency keys of the rate limiters are kept as.')
                ->defaultValue('%kernel.secret%')
                ->cannotBeEmpty()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @phpstan-var array{store: string, dbal: array{connection: string, counter_table: string, key_table: string}, clock: string, limit_providers: list<string>, subscription_features: array{enabled: bool, prefix: string}, anchor_provider: string|null, meters: array<array-key, array{limit: mixed, calendar: string|null, timezone: string, rolling: int|null, buckets: int, billing: string|null, months: int}>, rate_limiters: array<array-key, array{meter: string, key: string, amount: int, idempotency_key: bool}>, idempotency_secret: string} $config */
        UsageMeterServices::register($builder, $config);
    }

    private function meters(NodeBuilder $children): void
    {
        $children
            ->arrayNode('meters')
                ->info('The meters, by name: a limit and exactly one period — calendar, rolling or billing.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('limit')
                            ->info('The limit of a subject no limit provider names: "unlimited" or a whole number from zero.')
                            ->isRequired()
                        ->end()
                        ->enumNode('calendar')
                            ->info('Counted by calendar day, ISO week, month or year, from local midnight in the time zone.')
                            ->values(['day', 'week', 'month', 'year'])
                            ->defaultNull()
                        ->end()
                        ->scalarNode('timezone')->info('The time zone of a calendar period.')->defaultValue('UTC')->cannotBeEmpty()->end()
                        ->integerNode('rolling')->info('Counted over the last so many seconds.')->defaultNull()->end()
                        ->integerNode('buckets')->info('The buckets a rolling window is cut into, 1 to 1440, dividing it exactly.')->defaultValue(1)->end()
                        ->scalarNode('billing')
                            ->info("Counted by billing periods from this anchor, a date and time with its offset; each subject's own anchor wins.")
                            ->defaultNull()
                            // An unquoted YAML date is read as a timestamp.
                            ->beforeNormalization()
                                ->ifTrue(static fn (mixed $anchor): bool => \is_int($anchor))
                                ->then(static fn (int $timestamp): string => '@'.$timestamp)
                            ->end()
                        ->end()
                        ->integerNode('months')->info('The length of a billing period in months, 1 to 120.')->defaultValue(1)->end()
                    ->end()
                ->end()
            ->end();
    }

    private function rateLimiters(NodeBuilder $children): void
    {
        $children
            ->arrayNode('rate_limiters')
                ->info('The limiters of phil/rate-limit-bundle that count on a meter, by name: the meter, who a request counts for and what it counts.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('meter')->info('A meter of the meters section.')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('key')
                            ->info('Who a request counts for when a rule names no key: a key strategy of phil/rate-limit-bundle — ip, user, user_or_ip, ip_route — or a KeyResolver service id.')
                            ->defaultValue('ip')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('amount')->info('What one request counts when a rule names no amount.')->defaultValue(1)->min(1)->end()
                        ->booleanNode('idempotency_key')
                            ->info('Whether a POST, PUT, PATCH or DELETE request is counted once per Idempotency-Key header, so that a retry is not counted again.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
