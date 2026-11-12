<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle;

use Override;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsConfiguration;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsServices;
use Phil\RateLimit\RateLimitGuard;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Aggregate, identifier-free analytics. The domain decides what the design decides (UTC days, declared routes,
 * referrer and device classes, the client digest, the admission of a page view, the small-number rule, what is
 * measured, the retention, shared views, reports and alerts); the bundle wires it from `phil_analytics`, with a default
 * adapter for each contract that the application can replace by naming its own service:
 *
 * ```yaml
 * phil_analytics:
 *     sites:
 *         spring-con:
 *             origins: ['https://example.com']
 *             routes: { home: '/', talk: '/talks/{slug}' }
 *     counters: { store: dbal, connection: default }   # or the id of a CounterStore service
 *     rate_limiter: { limit: 600, interval: '10 minutes' }
 * ```
 *
 * @phpstan-import-type Config from AnalyticsServices
 */
final class PhilAnalyticsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_analytics';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        AnalyticsConfiguration::define($definition);
    }

    /**
     * The salt and the limiter count per server when their pool is local to it: in the prod environment, the build
     * refuses such a pool, unless `single_server` says the application runs on one server.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RateLimitGuard());
    }

    /**
     * Without DoctrineBundle, the "dbal" store has no connection: the application names its own store.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('phil_analytics', ['counters' => ['connection' => null]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        AnalyticsServices::load($config, $container->services());

        if (AnalyticsConfiguration::SALT_CACHE === $config['salt']['store']) {
            RateLimitGuard::watch($builder, $config['salt']['cache_pool'], 'phil_analytics.salt.cache_pool', $config['single_server']);
        }

        if (AnalyticsConfiguration::LIMITER_SYMFONY === $config['rate_limiter']['limiter']) {
            RateLimitGuard::watch($builder, $config['rate_limiter']['cache_pool'], 'phil_analytics.rate_limiter.cache_pool', $config['single_server']);
        }
    }
}
