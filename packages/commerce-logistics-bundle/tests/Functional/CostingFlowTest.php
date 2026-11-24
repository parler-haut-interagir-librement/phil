<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\SaleCost;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\ValuedStock;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use Phil\CommerceLogisticsBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Costing configured first in, first out, through the container: purchase orders received at their cost with a landed
 * cost, consigned units kept out of the value, a sale, a theft and a customer return, read back through
 * `InventoryValuation` from fresh units of work.
 */
#[CoversClass(PhilCommerceLogisticsBundle::class)]
final class CostingFlowTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, true, true);
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->service(LocationRepository::class, LocationRepository::class)->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $entityManager->flush();
    }

    public function testTheStockIsValuedAtCostAndASaleComesBackAtItsCost(): void
    {
        $at = new DateTimeImmutable('2026-11-21T10:00:00+00:00');
        $cad = Currency::of('CAD');
        $purchasing = $this->service(Purchasing::class, Purchasing::class);
        $purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(3, Money::of(1_000, $cad)), 'ZINE' => PurchaseLine::of(4, Money::of(300, $cad), 'consignor-17')], $at);
        $purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(2, Money::of(1_300, $cad))], $at);
        $purchasing->receive('PO-1', ['DICE' => 3, 'ZINE' => 4], $at, Money::of(150, $cad));
        $purchasing->receive('PO-2', ['DICE' => 2], $at->modify('+1 day'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $stock = $this->service(Stock::class, Stock::class);
        $order = Order::open('2026-0001', $cad);
        $price = Money::of(2_500, $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 4, $price);
        $order->place($at);

        $stock->reserve($order, $at);
        $this->entityManager()->flush();
        $stock->ship('2026-0001', $at->modify('+2 days'));
        $stock->adjustFor('ZINE', 'warehouse', -1, VarianceReason::Theft, $at->modify('+2 days'), 'COUNT-1');
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 1, $at->modify('+3 days'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $valuation = $this->service(InventoryValuation::class, InventoryValuation::class);
        // 31.50 for the first three, 13.00 for the fourth: the one back comes in at a quarter of the 44.50 its sale cost.
        self::assertSame([['DICE', 'warehouse', 2, 2_413]], array_map(static fn (ValuedStock $valued): array => [$valued->sku, $valued->location, $valued->units, $valued->value->minorUnits], $valuation->valuedStock($at->modify('+3 days'))));
        self::assertSame(1_207, $valuation->unitCost('DICE')?->minorUnits);
        self::assertNull($valuation->unitCost('ZINE'));
        self::assertSame([['2026-0001', 'DICE', 3, 3_337]], array_map(static fn (SaleCost $cost): array => [$cost->reference, $cost->sku, $cost->units, $cost->cost->minorUnits], $valuation->costOfSales($at, $at->modify('+4 days'))));
        self::assertSame(
            [['consignor-17', -1, 0, VarianceReason::Theft]],
            array_map(static fn (CostEntry $entry): array => [$entry->owner, $entry->units, $entry->value()->minorUnits, $entry->reason], $valuation->entries($at, $at->modify('+4 days'), [CostEntryKind::Variance])),
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
