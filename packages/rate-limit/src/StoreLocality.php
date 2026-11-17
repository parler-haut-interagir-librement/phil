<?php

declare(strict_types=1);

namespace Phil\RateLimit;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Whether a cache pool or a storage of `symfony/rate-limiter` keeps its data on the server that runs it, read from the
 * service definitions before the container is compiled.
 *
 * A pool is local when it ends on the filesystem, APCu, array or system adapter of `symfony/cache`, by its parent or
 * its class, the first being that of `cache.app` by default. A storage is local when it is the `InMemoryStorage` of
 * `symfony/rate-limiter`, and a `CacheStorage` is as local as the pool it is given. A rate limiter factory is as local
 * as its storage: a `RateLimiterFactory` defined by hand, or a limiter of the framework's configuration, `limiter.<name>`,
 * a child of the abstract `limiter` given its storage. Any other service is taken as
 * shared. Aliases are followed; a chain of more than 32 services, or one that loops, is taken as shared and left to
 * the container.
 *
 * The classes are compared by name: neither component needs to be installed.
 */
final readonly class StoreLocality
{
    /** The abstract adapters of the framework whose pools stay on the server. */
    public const array LOCAL_ADAPTERS = ['cache.adapter.apcu', 'cache.adapter.array', 'cache.adapter.filesystem', 'cache.adapter.system'];

    /** The classes of the pools and storages that stay on the server. */
    public const array LOCAL_CLASSES = [ApcuAdapter::class, ArrayAdapter::class, FilesystemAdapter::class, PhpFilesAdapter::class, InMemoryStorage::class];

    /** The abstract service of the framework whose children are the limiters of its configuration. */
    public const string LIMITER = 'limiter';

    /** The most services followed from the one asked about. */
    public const int MAX_DEPTH = 32;

    /**
     * @param string $id the service of the pool or the storage, or an alias of it
     */
    public function isLocal(ContainerBuilder $container, string $id): bool
    {
        $seen = [];

        for ($depth = 0; $depth < self::MAX_DEPTH; ++$depth) {
            if (!$container->has($id) || isset($seen[$id])) {
                return false;
            }

            $seen[$id] = true;
            $definition = $container->findDefinition($id);

            if ($definition instanceof ChildDefinition) {
                $parent = $definition->getParent();

                if (\in_array($parent, self::LOCAL_ADAPTERS, true)) {
                    return true;
                }

                if (self::LIMITER !== $parent) {
                    $id = $parent;

                    continue;
                }

                // A limiter not given its storage is left to the container, as an undefined service is.
                $storage = $definition->getArguments()['index_1'] ?? null;
                $id = $storage instanceof Reference ? (string) $storage : '';

                continue;
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!\is_string($class)) {
                return false;
            }

            $class = ltrim($class, '\\');

            if (\in_array($class, self::LOCAL_CLASSES, true)) {
                return true;
            }

            $pool = match ($class) {
                CacheStorage::class => $this->poolOf($definition->getArguments()),
                RateLimiterFactory::class => $this->storageOf($definition->getArguments()),
                default => null,
            };

            if (null === $pool) {
                return false;
            }

            $id = $pool;
        }

        return false;
    }

    /**
     * The pool a `CacheStorage` is given, by position or by name.
     *
     * @param array<array-key, mixed> $arguments
     */
    private function poolOf(array $arguments): ?string
    {
        $pool = $arguments[0] ?? $arguments['$pool'] ?? null;

        return $pool instanceof Reference ? (string) $pool : null;
    }

    /**
     * The storage a `RateLimiterFactory` is given, by position or by name.
     *
     * @param array<array-key, mixed> $arguments
     */
    private function storageOf(array $arguments): ?string
    {
        $storage = $arguments[1] ?? $arguments['$storage'] ?? null;

        return $storage instanceof Reference ? (string) $storage : null;
    }
}
