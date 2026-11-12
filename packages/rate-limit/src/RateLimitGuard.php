<?php

declare(strict_types=1);

namespace Phil\RateLimit;

use Override;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails the container build on a rate limit that would not limit: one enabled without `symfony/rate-limiter`, and, in
 * the `prod` environment, one counting in a cache pool or a storage each server keeps for itself, where a client could
 * spend the limit once per server.
 *
 * A bundle notes each store with `watch()` while it loads its configuration, and adds this pass in `build()`. Every
 * bundle writes to the same hidden parameter, which the first instance of the pass reads, checks and removes, so that
 * several bundles adding it check each store once. What is local is told by `StoreLocality`. An application on one
 * server says so with the `single_server` option of the bundle, and its stores are not noted.
 */
final readonly class RateLimitGuard implements CompilerPassInterface
{
    /** The hidden parameter of the stores to check, by the configuration that names each; removed by the pass. */
    public const string STORES_PARAMETER = '.phil_rate_limit.guarded_stores';

    public function __construct(
        private StoreLocality $locality = new StoreLocality(),
    ) {
    }

    /**
     * @param bool   $installed whether `symfony/rate-limiter` is there, as `interface_exists(RateLimiterFactoryInterface::class)` tells
     * @param string $path      the configuration of the rate limit, for the message
     *
     * @throws InvalidConfigurationException when the component is missing
     */
    public static function requireComponent(bool $installed, string $path): void
    {
        if (!$installed) {
            throw new InvalidConfigurationException(\sprintf('%s needs symfony/rate-limiter: install it, or set "enabled" to false.', $path));
        }
    }

    /**
     * Notes a cache pool or a storage to check when the container is compiled.
     *
     * @param string      $store        the service of the pool or the storage
     * @param string      $path         the configuration that names it, for the message
     * @param bool        $singleServer whether the application runs on one server, where a local store counts everything
     * @param string|null $switch       the option beside it that turns the rate limit off, offered in the message
     */
    public static function watch(ContainerBuilder $container, string $store, string $path, bool $singleServer = false, ?string $switch = null): void
    {
        if ($singleServer) {
            return;
        }

        $container->setParameter(self::STORES_PARAMETER, [...self::watched($container), $path => ['store' => $store, 'switch' => $switch]]);
    }

    /**
     * The stores noted so far, by the configuration that names each.
     *
     * @return array<string, array{store: string, switch: string|null}>
     */
    public static function watched(ContainerBuilder $container): array
    {
        /** @var array<string, array{store: string, switch: string|null}> $stores */
        $stores = $container->hasParameter(self::STORES_PARAMETER) ? $container->getParameter(self::STORES_PARAMETER) : [];

        return $stores;
    }

    /**
     * @throws InvalidConfigurationException when a store is local in production
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::STORES_PARAMETER)) {
            return;
        }

        $stores = self::watched($container);
        $container->getParameterBag()->remove(self::STORES_PARAMETER);

        if ('prod' !== $container->getParameter('kernel.environment')) {
            return;
        }

        foreach ($stores as $path => ['store' => $store, 'switch' => $switch]) {
            if ($this->locality->isLocal($container, $store)) {
                throw new InvalidConfigurationException($this->refusal($path, $store, $switch));
            }
        }
    }

    private function refusal(string $path, string $store, ?string $switch): string
    {
        $ways = null === $switch
            ? 'or set "single_server" to true on a single server'
            : \sprintf('set "single_server" to true on a single server, or set "%s" to false', $switch);

        return \sprintf('%s: the store "%s" is local to each server, so a client could spend the limit once per server. Name a cache pool on a shared store — Redis, Memcached, a database —, %s.', $path, $store, $ways);
    }
}
