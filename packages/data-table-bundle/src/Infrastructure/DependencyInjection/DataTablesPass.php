<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\DataTableBundle\Application\Contract\DataTable;
use Phil\DataTableBundle\Application\Exception\InvalidDefinition;
use Phil\DataTableBundle\Application\Tables;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives `Tables` the application's tables, by the name each one says it has.
 */
final readonly class DataTablesPass implements CompilerPassInterface
{
    public const string TAG = 'phil_data_table.table';

    /**
     * @throws InvalidDefinition when two tables have one name
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $tables = [];

        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            /** @var class-string<DataTable> $class */
            $class = $container->getDefinition($id)->getClass() ?? $id;
            $name = $class::name();

            if (isset($tables[$name])) {
                throw InvalidDefinition::because($name, 'two tables have this name.');
            }

            $tables[$name] = new Reference($id);
        }

        $container->getDefinition(Tables::class)->replaceArgument(0, ServiceLocatorTagPass::register($container, $tables));
    }
}
