<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\RateLimit\StoreLocality;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Refuses, in the prod environment, a cache pool of circuit states that stays on each server — the filesystem, APCu,
 * array or system adapter of `symfony/cache`, `cache.app` by default —, as `StoreLocality` of `phil/rate-limit` tells:
 * each server would count its own failures and open its own circuits, and a provider down for all would keep being
 * called by the servers that have not seen enough failures yet.
 *
 * The bundle notes the pool in a hidden parameter while it loads its configuration, unless `single_server` says the
 * application runs on one server or `state_store` names a store of its own; the pass reads and removes it.
 */
final readonly class SharedStateGuard implements CompilerPassInterface
{
    /** The hidden parameter of the pool to check, removed by the pass. */
    public const string POOL_PARAMETER = '.phil_circuit_breaker.guarded_pool';

    public function __construct(
        private StoreLocality $locality = new StoreLocality(),
    ) {
    }

    /**
     * @throws InvalidConfigurationException when the pool is local to each server in production
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::POOL_PARAMETER)) {
            return;
        }

        $pool = $container->getParameter(self::POOL_PARAMETER);
        $container->getParameterBag()->remove(self::POOL_PARAMETER);

        if (!\is_string($pool) || 'prod' !== $container->getParameter('kernel.environment') || !$this->locality->isLocal($container, $pool)) {
            return;
        }

        throw new InvalidConfigurationException(\sprintf('phil_circuit_breaker.cache_pool: the pool "%s" is local to each server, so each server would open its circuits on its own failures. Name a cache pool on a shared store — Redis, Memcached, a database —, a "state_store" of the application, or set "single_server" to true on a single server.', $pool));
    }
}
