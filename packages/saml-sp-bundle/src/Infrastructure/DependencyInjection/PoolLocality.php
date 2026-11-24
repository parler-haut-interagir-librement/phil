<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\DependencyInjection;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Whether a cache pool keeps its items on the server that runs it, read from the service definitions before the
 * container is compiled.
 *
 * A pool is local when it ends on the APCu, array, filesystem or system adapter of the framework, by its parent, or on
 * one of the classes of those adapters, the filesystem one being that of `cache.app` by default. Aliases and parents
 * are followed; any other pool is taken as shared, and so is a chain of more than 32 services or one that loops. The
 * classes are compared by name, which loads nothing: `symfony/cache` need not be installed.
 */
final readonly class PoolLocality
{
    /** The abstract adapters of the framework whose pools stay on the server. */
    public const array LOCAL_ADAPTERS = ['cache.adapter.apcu', 'cache.adapter.array', 'cache.adapter.filesystem', 'cache.adapter.system'];

    /** The classes of the pools that stay on the server. */
    public const array LOCAL_CLASSES = [
        ApcuAdapter::class,
        ArrayAdapter::class,
        FilesystemAdapter::class,
        PhpFilesAdapter::class,
        PhpArrayAdapter::class,
    ];

    /** The most services followed from the pool asked about. */
    public const int MAX_DEPTH = 32;

    /**
     * @param string $id the service of the pool, or an alias of it
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
                $id = $definition->getParent();

                if (\in_array($id, self::LOCAL_ADAPTERS, true)) {
                    return true;
                }

                continue;
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            return \is_string($class) && \in_array(ltrim($class, '\\'), self::LOCAL_CLASSES, true);
        }

        return false;
    }
}
