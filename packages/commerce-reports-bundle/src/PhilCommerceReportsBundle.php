<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\ReturnedLines;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Application\Report\DeadStockReport;
use Phil\CommerceReportsBundle\Application\Report\SalesReport;
use Phil\CommerceReportsBundle\Application\Report\SellThroughReport;
use Phil\CommerceReportsBundle\Application\Sales\SalesLedger;
use Phil\CommerceReportsBundle\Domain\Formula\Dormancy;
use Phil\CommerceReportsBundle\Infrastructure\DependencyInjection\PortsPass;
use Phil\CommerceReportsBundle\Infrastructure\Logistics\LogisticsStockFigures;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryReturnedLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemorySoldLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockedSkus;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockFigures;
use Phil\CommerceReportsBundle\Infrastructure\Pos\PosSoldLines;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The sales and stock reports of a shop, registered among the reports of the reporting bundle: gross and net sales,
 * sell-through and dead stock, read from the commerce modules through the bundle's ports.
 *
 * ```yaml
 * phil_commerce_reports:
 *     dead_stock_days: 90
 * ```
 */
final class PhilCommerceReportsBundle extends AbstractBundle
{
    /** The tag of the reporting bundle's reports, which collects them by their key. */
    public const string REPORT_TAG = 'phil_reporting.report';

    #[Override]
    protected string $extensionAlias = 'phil_commerce_reports';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PortsPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('dead_stock_days')
                    ->info('The days without a sale after which a SKU with units available is dead stock; a report\'s "days" parameter overrides it.')
                    ->defaultValue(Dormancy::DEFAULT_DAYS)
                    ->min(1)
                    ->max(Dormancy::MAX_DAYS)
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var int $days */
        $days = $config['dead_stock_days'];

        foreach ([InMemorySoldLines::class, InMemoryReturnedLines::class, InMemoryStockFigures::class, InMemoryStockedSkus::class] as $adapter) {
            $builder->register($adapter, $adapter);
        }

        // Their arguments are set by PortsPass once the modules' repositories are known.
        $builder->register(PosSoldLines::class, PosSoldLines::class);
        $builder->register(LogisticsStockFigures::class, LogisticsStockFigures::class);

        $builder->register(SalesLedger::class, SalesLedger::class)
            ->setArguments([new Reference(SoldLines::class), new Reference(ReturnedLines::class)]);
        $builder->register(SalesReport::class, SalesReport::class)
            ->setArguments([new Reference(SalesLedger::class)])
            ->addTag(self::REPORT_TAG, ['key' => SalesReport::key()]);
        $builder->register(SellThroughReport::class, SellThroughReport::class)
            ->setArguments([new Reference(SoldLines::class), new Reference(StockFigures::class), new Reference(StockedSkus::class)])
            ->addTag(self::REPORT_TAG, ['key' => SellThroughReport::key()]);
        $builder->register(DeadStockReport::class, DeadStockReport::class)
            ->setArguments([new Reference(StockFigures::class), new Reference(StockedSkus::class), new Reference(ClockInterface::class), $days])
            ->addTag(self::REPORT_TAG, ['key' => DeadStockReport::key()]);
    }
}
