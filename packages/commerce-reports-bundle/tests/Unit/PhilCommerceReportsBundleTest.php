<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit;

use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommerceReportsBundle\Application\Contract\ReturnedLines;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Application\Report\DeadStockReport;
use Phil\CommerceReportsBundle\Application\Report\SalesReport;
use Phil\CommerceReportsBundle\Application\Report\SellThroughReport;
use Phil\CommerceReportsBundle\Application\Sales\SalesLedger;
use Phil\CommerceReportsBundle\Infrastructure\DependencyInjection\PortsPass;
use Phil\CommerceReportsBundle\Infrastructure\Logistics\LogisticsStockFigures;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryReturnedLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemorySoldLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockedSkus;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockFigures;
use Phil\CommerceReportsBundle\Infrastructure\Pos\PosSoldLines;
use Phil\CommerceReportsBundle\PhilCommerceReportsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceReportsBundle::class)]
#[CoversClass(PortsPass::class)]
final class PhilCommerceReportsBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_commerce_reports', $this->extension()->getAlias());
    }

    public function testTheReportsAreTaggedWithTheirKeys(): void
    {
        $container = $this->load([]);

        self::assertSame([['key' => 'commerce_sales']], $container->getDefinition(SalesReport::class)->getTag(PhilCommerceReportsBundle::REPORT_TAG));
        self::assertSame([['key' => 'commerce_sell_through']], $container->getDefinition(SellThroughReport::class)->getTag(PhilCommerceReportsBundle::REPORT_TAG));
        self::assertSame([['key' => 'commerce_dead_stock']], $container->getDefinition(DeadStockReport::class)->getTag(PhilCommerceReportsBundle::REPORT_TAG));
        self::assertSame('phil_reporting.report', PhilCommerceReportsBundle::REPORT_TAG);
    }

    public function testTheReportsReadThePorts(): void
    {
        $container = $this->load([]);

        self::assertSame(SalesLedger::class, $container->getDefinition(SalesLedger::class)->getClass());
        self::assertEquals([new Reference(SoldLines::class), new Reference(ReturnedLines::class)], $container->getDefinition(SalesLedger::class)->getArguments());
        self::assertEquals([new Reference(SalesLedger::class)], $container->getDefinition(SalesReport::class)->getArguments());
        self::assertEquals(
            [new Reference(SoldLines::class), new Reference(StockFigures::class), new Reference(StockedSkus::class)],
            $container->getDefinition(SellThroughReport::class)->getArguments(),
        );
        self::assertEquals(
            [new Reference(StockFigures::class), new Reference(StockedSkus::class), new Reference(ClockInterface::class), 90],
            $container->getDefinition(DeadStockReport::class)->getArguments(),
        );
    }

    public function testTheDeadStockDaysDefaultToNinety(): void
    {
        self::assertSame(90, $this->load([])->getDefinition(DeadStockReport::class)->getArgument(3));
        self::assertSame(30, $this->load([['dead_stock_days' => 30]])->getDefinition(DeadStockReport::class)->getArgument(3));
        self::assertSame(1, $this->load([['dead_stock_days' => 1]])->getDefinition(DeadStockReport::class)->getArgument(3));
        self::assertSame(3650, $this->load([['dead_stock_days' => 3650]])->getDefinition(DeadStockReport::class)->getArgument(3));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function wrongDays(): iterable
    {
        yield 'no day' => [0];
        yield 'more than ten years' => [3651];
    }

    #[DataProvider('wrongDays')]
    public function testTheDeadStockDaysAreBounded(int $days): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['dead_stock_days' => $days]]);
    }

    public function testWithoutTheModulesThePortsAreEmpty(): void
    {
        $container = $this->compiled([]);

        self::assertSame(InMemorySoldLines::class, (string) $container->getAlias(SoldLines::class));
        self::assertSame(InMemoryStockFigures::class, (string) $container->getAlias(StockFigures::class));
        self::assertSame(InMemoryReturnedLines::class, (string) $container->getAlias(ReturnedLines::class));
        self::assertSame(InMemoryStockedSkus::class, (string) $container->getAlias(StockedSkus::class));
        self::assertFalse($container->hasDefinition(PosSoldLines::class));
        self::assertFalse($container->hasDefinition(LogisticsStockFigures::class));

        foreach ([InMemorySoldLines::class, InMemoryStockFigures::class, InMemoryReturnedLines::class, InMemoryStockedSkus::class] as $adapter) {
            self::assertSame($adapter, $container->getDefinition($adapter)->getClass());
        }
    }

    public function testWithTheModulesThePortsReadThem(): void
    {
        $container = $this->compiled([CashSessionRepository::class, RegisterSaleRepository::class, RegisterRepository::class, OrderRepository::class, StockRepository::class]);

        self::assertSame(PosSoldLines::class, (string) $container->getAlias(SoldLines::class));
        self::assertSame(LogisticsStockFigures::class, (string) $container->getAlias(StockFigures::class));
        self::assertEquals(
            [new Reference(CashSessionRepository::class), new Reference(RegisterSaleRepository::class), new Reference(RegisterRepository::class), new Reference(OrderRepository::class), new Reference(StockedSkus::class)],
            $container->getDefinition(PosSoldLines::class)->getArguments(),
        );
        self::assertEquals([new Reference(StockRepository::class)], $container->getDefinition(LogisticsStockFigures::class)->getArguments());
    }

    public function testAPointOfSaleWithoutItsCashSessionsIsNotRead(): void
    {
        $container = $this->compiled([RegisterSaleRepository::class, RegisterRepository::class, OrderRepository::class]);

        self::assertSame(InMemorySoldLines::class, (string) $container->getAlias(SoldLines::class));
        self::assertFalse($container->hasDefinition(PosSoldLines::class));
    }

    public function testAPointOfSaleWithoutTheOrdersIsNotRead(): void
    {
        $container = $this->compiled([CashSessionRepository::class, RegisterSaleRepository::class, RegisterRepository::class]);

        self::assertSame(InMemorySoldLines::class, (string) $container->getAlias(SoldLines::class));
    }

    public function testThePortsOfTheApplicationAreKept(): void
    {
        $container = $this->container([StockRepository::class]);
        $container->register('app.sold', InMemorySoldLines::class);
        $container->setAlias(SoldLines::class, 'app.sold');
        $container->register(StockFigures::class, InMemoryStockFigures::class);
        new PortsPass()->process($container);

        self::assertSame('app.sold', (string) $container->getAlias(SoldLines::class));
        self::assertFalse($container->hasAlias(StockFigures::class));
    }

    public function testThePassLeavesAnApplicationWithoutTheBundleAlone(): void
    {
        $container = new ContainerBuilder();
        new PortsPass()->process($container);

        self::assertFalse($container->has(SoldLines::class));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilCommerceReportsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof PortsPass));
    }

    /**
     * @param list<class-string> $modules
     */
    private function compiled(array $modules): ContainerBuilder
    {
        $container = $this->container($modules);
        new PortsPass()->process($container);

        return $container;
    }

    /**
     * @param list<class-string> $modules
     */
    private function container(array $modules): ContainerBuilder
    {
        $container = $this->load([]);

        foreach ($modules as $module) {
            $container->register($module, $module);
        }

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCommerceReportsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
