<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Cache;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Scopes `cache.app`, and every cache pool built on it, to the active tenant.
 *
 * A pool is built on `cache.app` when it is `cache.app` itself, or a pool whose definition inherits from it, as every
 * pool of `framework.cache.pools` without an adapter of its own does. Each such pool is decorated by
 * {@see TenantScopedPool}, or by {@see TenantScopedTagAwarePool} when it is natively tag-aware; each tag-aware adapter
 * over such a pool, such as `cache.app.taggable`, is decorated by {@see TenantScopedTagAwarePool}, which prefixes the
 * tags.
 *
 * The pools every tenant shares stay as they are: Symfony's own pools built on `cache.app`
 * ({@see self::SHARED_POOLS}) and the pools of `phil_tenancy.cache.global_pools`. A pool is named as the application
 * names it, even when Symfony registers its items under `.<pool>.inner`.
 */
final readonly class TenantCachePass implements CompilerPassInterface
{
    public const string GLOBAL_POOLS_PARAMETER = '.phil_tenancy.cache.global_pools';

    public const string APP_POOL = 'cache.app';

    public const string DECORATOR_SUFFIX = '.phil_tenancy';

    /**
     * Symfony's pools built on `cache.app` whose items every tenant shares: the signal that stops the workers, the
     * rate limits, the proofs and sessions a security server must share, the expired login links, the schedules, the
     * HTTP responses, the translation providers and the mailer webhooks.
     */
    public const array SHARED_POOLS = [
        'cache.messenger.restart_workers_signal',
        'cache.rate_limiter',
        'cache.security',
        'cache.security.discovery',
        'security.authenticator.cache.expired_links',
        'cache.scheduler',
        'cache.http_client.pool',
        'cache.translation.providers',
        'cache.mailer.webhook',
    ];

    /**
     * @param string $activationTag the tag of the tenant activations, which each decorator gets
     */
    public function __construct(
        private string $activationTag,
    ) {
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::GLOBAL_POOLS_PARAMETER) || !$container->hasDefinition(self::APP_POOL)) {
            return;
        }

        /** @var list<string> $configured */
        $configured = $container->getParameter(self::GLOBAL_POOLS_PARAMETER);
        $pools = array_keys($container->findTaggedServiceIds('cache.pool'));
        $names = array_fill_keys(array_map($this->poolName(...), $pools), true);

        foreach ($configured as $pool) {
            if (!isset($names[$pool])) {
                throw new InvalidArgumentException(\sprintf('The pool "%s" of phil_tenancy.cache.global_pools is not a cache pool.', $pool));
            }
        }

        $scoped = $this->scopePools($container, $pools, array_fill_keys([...self::SHARED_POOLS, ...$configured], true));
        $this->scopeTagAwareAdapters($container, $scoped);
    }

    /**
     * @param list<string>        $pools
     * @param array<string, true> $global
     *
     * @return array<string, true> the pools scoped
     */
    private function scopePools(ContainerBuilder $container, array $pools, array $global): array
    {
        $scoped = [];

        foreach ($pools as $id) {
            $definition = $container->getDefinition($id);

            if ($definition->isAbstract() || isset($global[$this->poolName($id)]) || !$this->isBuiltOnApp($container, $id, $definition)) {
                continue;
            }

            $class = $this->classOf($container, $definition);
            $tagAware = $definition->hasTag('cache.taggable') || (null !== $class && is_a($class, TagAwareAdapterInterface::class, true));
            $this->requireScopable($id, $class, $tagAware ? [NamespacedPoolInterface::class, TagAwareCacheInterface::class] : [NamespacedPoolInterface::class, CacheInterface::class]);

            $scoped[$id] = true;
            $decorator = $container->register($id.self::DECORATOR_SUFFIX, $tagAware ? TenantScopedTagAwarePool::class : TenantScopedPool::class)
                ->setDecoratedService($id)
                ->setArguments([new Reference('.inner'), new Reference(TenantContext::class)])
                ->addTag($this->activationTag);

            if ($tagAware) {
                $decorator->addArgument(true);
            }
        }

        return $scoped;
    }

    /**
     * The tag-aware adapters whose items live in a pool just scoped, such as `cache.app.taggable`.
     *
     * @param array<string, true> $scoped
     */
    private function scopeTagAwareAdapters(ContainerBuilder $container, array $scoped): void
    {
        foreach (array_keys($container->findTaggedServiceIds('cache.taggable')) as $id) {
            $definition = $container->getDefinition($id);
            $items = $definition->getArguments()[0] ?? null;

            if (isset($scoped[$id]) || !$items instanceof Reference || !isset($scoped[(string) $items]) || !is_a($this->classOf($container, $definition) ?? '', TagAwareAdapter::class, true)) {
                continue;
            }

            $container->register($id.self::DECORATOR_SUFFIX, TenantScopedTagAwarePool::class)
                ->setDecoratedService($id)
                ->setArguments([new Reference('.inner'), new Reference(TenantContext::class), false])
                ->addTag($this->activationTag);
        }
    }

    /**
     * @param class-string|null  $class      null when the class is known only once the container runs
     * @param list<class-string> $interfaces
     */
    private function requireScopable(string $id, ?string $class, array $interfaces): void
    {
        foreach ($interfaces as $interface) {
            if (null !== $class && !is_a($class, $interface, true)) {
                throw new InvalidArgumentException(\sprintf('The cache pool "%s" (%s) cannot be scoped to the active tenant: it does not implement %s. List it in phil_tenancy.cache.global_pools to share it between tenants.', $this->poolName($id), $class, $interface));
            }
        }
    }

    /**
     * The name the application gives the pool: `.<pool>.inner`, where Symfony keeps the items of a tag-aware pool of
     * `framework.cache.pools`, is `<pool>`.
     */
    private function poolName(string $id): string
    {
        if (str_starts_with($id, '.') && str_ends_with($id, '.inner')) {
            return substr($id, 1, -\strlen('.inner'));
        }

        return $id;
    }

    private function isBuiltOnApp(ContainerBuilder $container, string $id, Definition $definition): bool
    {
        if (self::APP_POOL === $id) {
            return true;
        }

        $seen = [];

        while ($definition instanceof ChildDefinition) {
            $parent = $definition->getParent();

            if (self::APP_POOL === $parent) {
                return true;
            }

            if (isset($seen[$parent]) || !$container->hasDefinition($parent)) {
                return false;
            }

            $seen[$parent] = true;
            $definition = $container->getDefinition($parent);
        }

        return false;
    }

    /**
     * @return class-string|null
     */
    private function classOf(ContainerBuilder $container, Definition $definition): ?string
    {
        $seen = [];

        while (null === $definition->getClass() && $definition instanceof ChildDefinition) {
            $parent = $definition->getParent();

            if (isset($seen[$parent]) || !$container->hasDefinition($parent)) {
                return null;
            }

            $seen[$parent] = true;
            $definition = $container->getDefinition($parent);
        }

        $class = $container->getParameterBag()->resolveValue($definition->getClass());

        return \is_string($class) && class_exists($class) ? $class : null;
    }
}
