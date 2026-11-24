<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommerceReportsBundle\Application\Contract\ReturnedLines;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Infrastructure\Logistics\LogisticsStockFigures;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryReturnedLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemorySoldLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockedSkus;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockFigures;
use Phil\CommerceReportsBundle\Infrastructure\Pos\PosSoldLines;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Each port the application left alone, aliased to the adapter of the module that reads it, or to its empty memory
 * adapter when the module is not there: the point of sale for the lines sold, the logistics module for the stock
 * figures. The returned lines and the stocked SKUs have no module that lists them, and stay empty until the
 * application aliases them.
 */
final class PortsPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PosSoldLines::class)) {
            return;
        }

        $pos = $this->hasAll($container, [CashSessionRepository::class, RegisterSaleRepository::class, RegisterRepository::class, OrderRepository::class]);
        $logistics = $this->hasAll($container, [StockRepository::class]);

        if ($pos) {
            $container->getDefinition(PosSoldLines::class)->setArguments([
                new Reference(CashSessionRepository::class),
                new Reference(RegisterSaleRepository::class),
                new Reference(RegisterRepository::class),
                new Reference(OrderRepository::class),
                new Reference(StockedSkus::class),
            ]);
        } else {
            $container->removeDefinition(PosSoldLines::class);
        }

        if ($logistics) {
            $container->getDefinition(LogisticsStockFigures::class)->setArguments([new Reference(StockRepository::class)]);
        } else {
            $container->removeDefinition(LogisticsStockFigures::class);
        }

        $this->alias($container, SoldLines::class, $pos ? PosSoldLines::class : InMemorySoldLines::class);
        $this->alias($container, StockFigures::class, $logistics ? LogisticsStockFigures::class : InMemoryStockFigures::class);
        $this->alias($container, ReturnedLines::class, InMemoryReturnedLines::class);
        $this->alias($container, StockedSkus::class, InMemoryStockedSkus::class);
    }

    /**
     * @param list<class-string> $ids
     */
    private function hasAll(ContainerBuilder $container, array $ids): bool
    {
        return array_all($ids, static fn (string $id): bool => $container->has($id));
    }

    private function alias(ContainerBuilder $container, string $port, string $adapter): void
    {
        if (!$container->has($port)) {
            $container->setAlias($port, $adapter);
        }
    }
}
