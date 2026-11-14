<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Infrastructure\DependencyInjection;

use Doctrine\Migrations\Migrator;
use Doctrine\Migrations\Version\MigrationFactory;
use Phil\Migration\Announcements;
use Phil\Migration\Announcer;
use Phil\Migration\Announcing;
use Phil\Migration\AnnouncingMigrationFactory;
use Phil\Migration\AnnouncingMigrator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wraps the migration factory and the migrator of DoctrineMigrationsBundle: the migrations get the announcements of
 * the run, and the run is announced once it is over.
 *
 * A factory or a migrator the application gave in `doctrine_migrations.services` is decorated; otherwise the one
 * Doctrine Migrations makes is wrapped, as it is built while the dependency factory resolves it.
 *
 * The decoration comes last, outside any other: the factory of the service migrations of DoctrineMigrationsBundle
 * decorates the same service, and returns the migrations of the container without calling the factory it wraps.
 *
 * The migrations of the container (`enable_service_migrations`, tagged `doctrine_migrations.migration`) never go
 * through a factory when no directory lists them: each `Announcing` one gets the announcements as it is built.
 */
final readonly class AnnouncingMigrationsPass implements CompilerPassInterface
{
    public const string DEPENDENCY_FACTORY = 'doctrine.migrations.dependency_factory';

    public const string FACTORY_ID = 'phil_migration.migration_factory';

    public const string MIGRATOR_ID = 'phil_migration.migrator';

    public const string MIGRATION_TAG = 'doctrine_migrations.migration';

    /**
     * The priority of the decorations: below the default one, so that they wrap the decorators of the same service.
     */
    private const int PRIORITY = -1;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::DEPENDENCY_FACTORY)) {
            return;
        }

        $this->wrap($container, MigrationFactory::class, self::FACTORY_ID, AnnouncingMigrationFactory::class, 'getMigrationFactory', [new Reference(Announcements::class)]);

        if ($container->hasDefinition(Announcer::class)) {
            $this->wrap($container, Migrator::class, self::MIGRATOR_ID, AnnouncingMigrator::class, 'getMigrator', [new Reference(Announcements::class), new Reference(Announcer::class)]);
        }

        foreach (array_keys($container->findTaggedServiceIds(self::MIGRATION_TAG)) as $id) {
            $this->announce($container, $container->getDefinition($id));
        }
    }

    /**
     * A migration of the container gets the announcements as it is built, when it announces.
     */
    private function announce(ContainerBuilder $container, Definition $migration): void
    {
        $class = $container->getParameterBag()->resolveValue($migration->getClass());

        if (!\is_string($class) || true !== $container->getReflectionClass($class)?->implementsInterface(Announcing::class) || $migration->hasMethodCall('announceWith')) {
            return;
        }

        $migration->addMethodCall('announceWith', [new Reference(Announcements::class)]);
    }

    /**
     * @param class-string    $service   the service of Doctrine Migrations wrapped
     * @param class-string    $class     the class that wraps it
     * @param list<Reference> $arguments the arguments that follow the wrapped service
     */
    private function wrap(ContainerBuilder $container, string $service, string $id, string $class, string $getter, array $arguments): void
    {
        $dependencies = $container->getDefinition(self::DEPENDENCY_FACTORY);
        $given = $this->given($dependencies, $service);

        if (null !== $given) {
            $container->register($id, $class)
                ->setDecoratedService($given, null, self::PRIORITY)
                ->setArguments([new Reference($id.'.inner'), ...$arguments]);

            return;
        }

        $container->register($id, $class)
            ->setArguments([new Definition($service)->setFactory([new Reference(self::DEPENDENCY_FACTORY), $getter]), ...$arguments]);
        $dependencies->addMethodCall('setDefinition', [$service, new ServiceClosureArgument(new Reference($id))]);
    }

    /**
     * The service the application gave Doctrine Migrations in place of one of its own, the last one when several were.
     */
    private function given(Definition $dependencies, string $service): ?string
    {
        $given = null;

        foreach (array_filter($dependencies->getMethodCalls(), $this->isDefinition(...)) as [, $arguments]) {
            $closure = $arguments[1] ?? null;
            $value = $closure instanceof ServiceClosureArgument ? $closure->getValues()[0] : null;

            if ($service === ($arguments[0] ?? null) && $value instanceof Reference) {
                $given = (string) $value;
            }
        }

        return $given;
    }

    /**
     * Whether a method call of the dependency factory gives it a service: `setDefinition($id, $closure)`.
     *
     * @phpstan-assert-if-true array{string, array<mixed>} $call
     */
    private function isDefinition(mixed $call): bool
    {
        return \is_array($call) && 'setDefinition' === $call[0] && \is_array($call[1]);
    }
}
