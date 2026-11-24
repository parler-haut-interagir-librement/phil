<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Contract\StockOwnership;
use Phil\CommerceLogisticsBundle\Application\Costing;
use Phil\CommerceLogisticsBundle\Application\InventoryValue;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\ShopUnitsFirst;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostingMethod;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostShare;
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;
use Phil\CommerceLogisticsBundle\Domain\Exception\CostRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\Model\CostLayer;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\ValuedStock;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The value of the stock as it moves, under both methods: receipts at their unit cost, landed costs shared to the minor
 * unit, shipments, variances by reason, transfers, customer returns at the cost they left at, and units left on
 * consignment kept out of the shop's value everywhere.
 */
#[CoversClass(Costing::class)]
#[CoversClass(ShopUnitsFirst::class)]
#[CoversClass(Stock::class)]
#[CoversClass(Purchasing::class)]
#[CoversClass(CostRefused::class)]
final class CostingTest extends TestCase
{
    private InMemoryLogistics $repository;

    private DateTimeImmutable $at;

    private Currency $cad;

    #[Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('store', 'Store', 0));

        $this->at = new DateTimeImmutable('2026-11-21T10:00:00Z');
        $this->cad = Currency::of('CAD');
    }

    public function testAWeightedAverageValuesUnitsAtTheirPoolAndAReturnAtItsSalesCost(): void
    {
        [$stock, $purchasing] = $this->services(CostingMethod::WeightedAverage);
        $purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(3, $this->cad(1_000))], $this->at);
        $purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(3, $this->cad(1_250))], $this->at);
        $purchasing->receive('PO-1', ['DICE' => 3], $this->at);
        $purchasing->receive('PO-2', ['DICE' => 2], $this->at->modify('+1 day'));

        self::assertSame([[null, 5, 5_500]], $this->layers('DICE', 'warehouse'));
        self::assertSame(1_100, $this->value()->unitCost('DICE')?->minorUnits);

        $stock->reserve($this->order('2026-0001', ['DICE' => 2]), $this->at);
        $stock->ship('2026-0001', $this->at->modify('+2 days'));
        self::assertSame([[null, 3, 3_300]], $this->layers('DICE', 'warehouse'));

        $stock->receive('DICE', 'warehouse', 1, $this->at->modify('+3 days'), 'PO-3', $this->cad(1_300));
        self::assertSame([[null, 4, 4_600]], $this->layers('DICE', 'warehouse'));

        $stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 1, $this->at->modify('+4 days'));
        // At the 11.00 it left at, not at the 11.50 of the day.
        self::assertSame([[null, 5, 5_700]], $this->layers('DICE', 'warehouse'));
        self::assertSame([
            [CostEntryKind::Received, null, 3, 0, 3_000, 'PO-1'],
            [CostEntryKind::Received, null, 2, 0, 2_500, 'PO-2'],
            [CostEntryKind::Issued, null, -2, 0, -2_200, '2026-0001'],
            [CostEntryKind::Received, null, 1, 0, 1_300, 'PO-3'],
            [CostEntryKind::Returned, null, 1, 0, 1_100, '2026-0001'],
        ], $this->entries());
    }

    public function testFirstInFirstOutTakesTheOldestLayersAndAReturnComesBackAtItsSalesCost(): void
    {
        [$stock, $purchasing] = $this->services(CostingMethod::FirstInFirstOut);
        $purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(3, $this->cad(1_000))], $this->at);
        $purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(2, $this->cad(1_250))], $this->at);
        $purchasing->receive('PO-2', ['DICE' => 2], $this->at->modify('+1 day'));
        $purchasing->receive('PO-1', ['DICE' => 3], $this->at);

        self::assertSame([[null, 3, 3_000], [null, 2, 2_500]], $this->layers('DICE', 'warehouse'));
        self::assertSame(1_100, $this->value()->unitCost('DICE')?->minorUnits);

        $stock->reserve($this->order('2026-0001', ['DICE' => 4]), $this->at);
        $stock->ship('2026-0001', $this->at->modify('+2 days'));
        self::assertSame([[null, 1, 1_250]], $this->layers('DICE', 'warehouse'));
        self::assertSame(1_250, $this->value()->unitCost('DICE')?->minorUnits);

        $stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 1, $this->at->modify('+3 days'));
        $stock->returnFromCustomer('2026-0001', 'DICE', 'store', 3, $this->at->modify('+3 days'));
        // 42.50 left in all: a quarter of it, the larger remainder first, then the rest, to the minor unit.
        self::assertSame([[null, 1, 1_250], [null, 1, 1_063]], $this->layers('DICE', 'warehouse'));
        self::assertSame([[null, 3, 3_187]], $this->layers('DICE', 'store'));
        self::assertSame([[CostEntryKind::Issued, null, -4, 0, -4_250, '2026-0001'], [CostEntryKind::Returned, null, 1, 0, 1_063, '2026-0001'], [CostEntryKind::Returned, null, 3, 0, 3_187, '2026-0001']], \array_slice($this->entries(), 2));
    }

    public function testALandedCostIsSharedOutOverTheLinesThatBearIt(): void
    {
        [$stock, $purchasing] = $this->services(CostingMethod::FirstInFirstOut);
        $purchasing->order('PO-1', 'Books & Co', 'warehouse', [
            'BOOK' => PurchaseLine::of(3, $this->cad(1_000)),
            'ZINE' => PurchaseLine::of(1, $this->cad(500)),
            'CARD' => 4,
            'LENT' => PurchaseLine::of(2, $this->cad(700), 'consignor-17'),
        ], $this->at);
        $purchasing->receive('PO-1', ['BOOK' => 3, 'ZINE' => 1, 'CARD' => 4, 'LENT' => 2], $this->at, $this->cad(100));

        // 1.00 over 30.00 and 5.00: 85.71 and 14.28 cents, the cent left to the larger remainder.
        self::assertSame([[null, 3, 3_086]], $this->layers('BOOK', 'warehouse'));
        self::assertSame([[null, 1, 514]], $this->layers('ZINE', 'warehouse'));
        self::assertSame([], $this->layers('CARD', 'warehouse'));
        self::assertSame([['consignor-17', 2, 0]], $this->layers('LENT', 'warehouse'));
        self::assertSame([[CostEntryKind::Landed, null, 0, 0, 86, 'PO-1'], [CostEntryKind::Landed, null, 0, 0, 14, 'PO-1']], \array_slice($this->entries(), 4));

        $stock->reserve($this->order('2026-0001', ['BOOK' => 3]), $this->at);
        $stock->shipPart('2026-0001', 'BOOK', 1, $this->at);
        $stock->shipPart('2026-0001', 'BOOK', 1, $this->at);
        $stock->shipPart('2026-0001', 'BOOK', 1, $this->at);

        // No minor unit lost: the last unit takes what is left.
        self::assertSame([-1_029, -1_029, -1_028], array_map(static fn (CostEntry $entry): int => $entry->value()->minorUnits, \array_slice($this->repository->entries, 6)));
        self::assertSame([], $this->layers('BOOK', 'warehouse'));
    }

    public function testALandedCostIsSharedByUnitsWhenEveryLineCostsNothing(): void
    {
        [, $purchasing] = $this->services(CostingMethod::WeightedAverage);
        $purchasing->order('PO-1', 'Books & Co', 'warehouse', ['FREE' => PurchaseLine::of(3, $this->cad(0)), 'GIFT' => PurchaseLine::of(1, $this->cad(0))], $this->at);
        $purchasing->receive('PO-1', ['FREE' => 3, 'GIFT' => 1], $this->at, $this->cad(101));

        self::assertSame([[null, 3, 76]], $this->layers('FREE', 'warehouse'));
        self::assertSame([[null, 1, 25]], $this->layers('GIFT', 'warehouse'));
    }

    public function testALandedCostNeedsCostingABearerAndTheStocksCurrency(): void
    {
        $stock = new Stock($this->repository, $this->repository);
        $plain = new Purchasing($this->repository, $this->repository, $stock);
        $plain->order('PO-0', 'Books & Co', 'warehouse', ['BOOK' => PurchaseLine::of(3, $this->cad(1_000))], $this->at);
        $this->refused('Costing is not enabled: set phil_commerce_logistics.costing to record a landed cost.', fn (): PurchaseOrder => $plain->receive('PO-0', ['BOOK' => 1], $this->at, $this->cad(100)));

        [, $purchasing] = $this->services(CostingMethod::WeightedAverage);
        $purchasing->order('PO-1', 'Books & Co', 'warehouse', ['LENT' => PurchaseLine::of(2, $this->cad(700), 'consignor-17'), 'CARD' => 2], $this->at);
        $this->refused("No line of the receipt against PO-1 bears a landed cost: it needs units of the shop's with a unit cost.", fn () => $purchasing->receive('PO-1', ['LENT' => 1, 'CARD' => 1], $this->at, $this->cad(100)));

        $purchasing->order('PO-2', 'Books & Co', 'warehouse', ['BOOK' => PurchaseLine::of(3, $this->cad(1_000))], $this->at);
        $this->refused('A cost of PO-2 is zero or more; -1.00 CAD is not.', fn () => $purchasing->receive('PO-2', ['BOOK' => 1], $this->at, $this->cad(-100)));
        $this->refused('The stock of BOOK is valued in CAD; a cost in USD cannot join it.', fn () => $purchasing->receive('PO-2', ['BOOK' => 1], $this->at, Money::of(100, Currency::of('USD'))));

        $purchasing->order('PO-3', 'Books & Co', 'warehouse', ['MAP' => PurchaseLine::of(1, Money::of(100, Currency::of('USD')))], $this->at);
        $this->refused('The stock of MAP is valued in CAD; a cost in USD cannot join it.', fn () => $purchasing->receive('PO-3', ['MAP' => 1], $this->at));
    }

    public function testALandedCostReachesOnlyTheLayerOfItsReceipt(): void
    {
        $costing = $this->costing(CostingMethod::FirstInFirstOut);
        $costing->receive('BOOK', 'warehouse', 1, $this->cad(1_000), null, 'PO-1', $this->at);
        $costing->receive('BOOK', 'warehouse', 1, $this->cad(1_000), null, 'PO-2', $this->at->modify('+1 day'));
        $costing->land('BOOK', 'warehouse', $this->cad(50), 'PO-1', $this->at);

        self::assertSame([[null, 1, 1_050], [null, 1, 1_000]], $this->layers('BOOK', 'warehouse'));
        $this->refused('No cost layer of BOOK at warehouse holds the units received under PO-9.', fn () => $costing->land('BOOK', 'warehouse', $this->cad(50), 'PO-9', $this->at));
        $this->refused('No cost layer of DICE at warehouse holds the units received under PO-1.', fn () => $this->costing(CostingMethod::WeightedAverage)->land('DICE', 'warehouse', $this->cad(50), 'PO-1', $this->at));
        $this->refused('A cost of BOOK is zero or more; -0.50 CAD is not.', fn () => $costing->land('BOOK', 'warehouse', $this->cad(-50), 'PO-1', $this->at));
    }

    public function testConsignedUnitsNeverJoinTheShopsValue(): void
    {
        [$stock, $purchasing] = $this->services(CostingMethod::WeightedAverage);
        $purchasing->order('PO-1', 'Zoë Café', 'warehouse', ['ZINE' => PurchaseLine::of(5, $this->cad(300), '17')], $this->at);
        $purchasing->order('PO-2', 'Books & Co', 'warehouse', ['ZINE' => PurchaseLine::of(2, $this->cad(400))], $this->at);
        $purchasing->receive('PO-1', ['ZINE' => 5], $this->at);
        $purchasing->receive('PO-2', ['ZINE' => 2], $this->at->modify('+1 day'));

        self::assertSame([['17', 5, 0], [null, 2, 800]], $this->layers('ZINE', 'warehouse'));
        self::assertSame([['ZINE', 'warehouse', 2, 800]], $this->valued($this->at->modify('+1 day')));

        $stock->reserve($this->order('2026-0001', ['ZINE' => 3]), $this->at);
        $stock->ship('2026-0001', $this->at->modify('+2 days'));

        // The shop's two first, then one of the consignor's, which costs the shop nothing.
        self::assertSame([['17', 4, 0]], $this->layers('ZINE', 'warehouse'));
        self::assertSame([[CostEntryKind::Issued, null, -2, 0, -800, '2026-0001'], [CostEntryKind::Issued, '17', -1, 0, 0, '2026-0001']], \array_slice($this->entries(), 2));
        self::assertSame([], $this->valued($this->at->modify('+2 days')));
        self::assertSame(400, $this->value()->unitCost('ZINE')?->minorUnits);

        $stock->returnFromCustomer('2026-0001', 'ZINE', 'warehouse', 3, $this->at->modify('+3 days'));
        self::assertSame([['17', 4, 0], [null, 2, 800], ['17', 1, 0]], $this->layers('ZINE', 'warehouse'));
        self::assertSame([[CostEntryKind::Returned, '17', 1, 0, 0, '2026-0001'], [CostEntryKind::Returned, null, 2, 0, 800, '2026-0001']], \array_slice($this->entries(), 4));
    }

    public function testAnApplicationSaysWhoseUnitsLeave(): void
    {
        $ownership = new class implements StockOwnership {
            /** @var list<array{int, int, list<array{string, int}>, string|null}> */
            public array $asked = [];

            /** @var list<array{string, int}> */
            public array $answer = [['consignor-9', 1]];

            #[Override]
            public function leaving(string $sku, string $location, int $units, int $shopUnits, array $consigned, ?string $reference): array
            {
                $this->asked[] = [$units, $shopUnits, $consigned, $reference];

                return $this->answer;
            }
        };
        $costing = new Costing($this->repository, CostingMethod::FirstInFirstOut, $this->cad, $ownership);
        $stock = new Stock($this->repository, $this->repository, costing: $costing);
        $stock->receive('ZINE', 'warehouse', 2, $this->at, 'PO-1', owner: 'consignor-9');
        $stock->receive('ZINE', 'warehouse', 1, $this->at->modify('+1 hour'), 'PO-2', owner: 'consignor-3');
        $stock->receive('ZINE', 'warehouse', 1, $this->at->modify('+2 hours'), 'PO-3', owner: 'consignor-9');
        $stock->receive('ZINE', 'warehouse', 2, $this->at->modify('+3 hours'), 'PO-4', $this->cad(400));
        $stock->receive('ZINE', 'warehouse', 1, $this->at->modify('+4 hours'), 'PO-5');

        $stock->adjustFor('ZINE', 'warehouse', -2, VarianceReason::Damaged, $this->at, 'DMG-1');

        self::assertSame([[2, 3, [['consignor-9', 3], ['consignor-3', 1]], 'DMG-1']], $ownership->asked);
        self::assertSame([[CostEntryKind::Variance, null, -1, 0, -400, 'DMG-1'], [CostEntryKind::Variance, 'consignor-9', -1, 0, 0, 'DMG-1']], \array_slice($this->entries(), 5));
        self::assertSame([VarianceReason::Damaged, VarianceReason::Damaged], array_map(static fn (CostEntry $entry): ?VarianceReason => $entry->reason, \array_slice($this->repository->entries, 5)));

        foreach ([[['consignor-9', 4]], [['consignor-3', 2]], [['consignor-3', -1]], [['nobody', 1]], [], [['consignor-9', 1], ['consignor-9', 3]]] as $answer) {
            $ownership->answer = $answer;
            $this->refused('The owners given for 4 units of ZINE leaving warehouse do not add up: each owner gives no more than they hold, and the shop no more than it holds.', fn () => $costing->vary('ZINE', 'warehouse', -4, 5, VarianceReason::Lost, 'DMG-2', $this->at));
        }
    }

    public function testTheShopsUnitsLeaveFirstThenTheOwnersInTheOrderTheyCame(): void
    {
        $owners = new ShopUnitsFirst();

        self::assertSame([], $owners->leaving('ZINE', 'store', 2, 2, [['a', 3]], null));
        self::assertSame([['a', 1]], $owners->leaving('ZINE', 'store', 3, 2, [['a', 3], ['b', 1]], null));
        self::assertSame([['a', 3], ['b', 1]], $owners->leaving('ZINE', 'store', 6, 2, [['a', 3], ['b', 1], ['c', 5]], null));
        self::assertSame([['b', 2]], $owners->leaving('ZINE', 'store', 2, 0, [['a', 0], ['b', 4]], null));
    }

    public function testAVarianceIsJournaledWithItsReasonAndItsValueAtCost(): void
    {
        [$stock] = $this->services(CostingMethod::WeightedAverage);
        $stock->receive('DICE', 'warehouse', 3, $this->at, 'PO-1', $this->cad(1_000));
        $stock->receive('DICE', 'warehouse', 1, $this->at, 'PO-2', $this->cad(1_001));

        $stock->adjustFor('DICE', 'warehouse', -1, VarianceReason::Theft, $this->at, 'COUNT-1');
        $stock->count('DICE', 'warehouse', 5, $this->at, 'COUNT-2', VarianceReason::CountError);
        $stock->count('DICE', 'warehouse', 5, $this->at, 'COUNT-3', VarianceReason::CountError);
        $stock->adjust('DICE', 'warehouse', -1, $this->at, 'COUNT-4');

        self::assertSame([
            [CostEntryKind::Variance, null, -1, 0, -1_000, 'COUNT-1'],
            [CostEntryKind::Variance, null, 2, 0, 2_001, 'COUNT-2'],
            [CostEntryKind::Variance, null, -1, 0, -1_000, 'COUNT-4'],
        ], \array_slice($this->entries(), 2));
        self::assertSame([VarianceReason::Theft, VarianceReason::CountError, null], array_map(static fn (CostEntry $entry): ?VarianceReason => $entry->reason, \array_slice($this->repository->entries, 2)));
        self::assertSame(
            [null, null, VarianceReason::Theft, VarianceReason::CountError, VarianceReason::CountError, null],
            array_map(static fn (StockMovement $movement): ?VarianceReason => $movement->reason, $this->repository->movements),
        );
        self::assertSame([[null, 4, 4_002]], $this->layers('DICE', 'warehouse'));
    }

    public function testUnitsFoundUnderFirstInFirstOutTakeTheNewestLayersCost(): void
    {
        [$stock] = $this->services(CostingMethod::FirstInFirstOut);
        $stock->receive('DICE', 'warehouse', 3, $this->at, 'PO-1', $this->cad(1_000));
        $stock->receive('DICE', 'warehouse', 3, $this->at->modify('+1 day'), 'PO-2', $this->cad(1_001));
        $stock->adjustFor('DICE', 'warehouse', 2, VarianceReason::CountError, $this->at->modify('+2 days'), 'COUNT-1');

        self::assertSame([[null, 3, 3_000], [null, 3, 3_003], [null, 2, 2_002]], $this->layers('DICE', 'warehouse'));
    }

    public function testUnitsWithoutAKnownCostAreUncosted(): void
    {
        [$stock] = $this->services(CostingMethod::WeightedAverage);
        $stock->receive('DICE', 'warehouse', 2, $this->at, 'PO-1');
        $stock->adjustFor('DICE', 'warehouse', 1, VarianceReason::CountError, $this->at, 'COUNT-1');
        $stock->receive('DICE', 'warehouse', 1, $this->at, 'PO-2', $this->cad(900));

        self::assertNull($this->value()->unitCost('CARD'));
        self::assertSame(900, $this->value()->unitCost('DICE')?->minorUnits);

        $stock->reserve($this->order('2026-0001', ['DICE' => 3]), $this->at);
        $stock->ship('2026-0001', $this->at);
        $stock->returnFromCustomer('2026-0001', 'DICE', 'store', 2, $this->at);
        $stock->returnFromCustomer('2026-0001', 'DICE', 'store', 1, $this->at);

        self::assertSame([
            [CostEntryKind::Received, null, 2, 2, 0, 'PO-1'],
            [CostEntryKind::Variance, null, 1, 1, 0, 'COUNT-1'],
            [CostEntryKind::Received, null, 1, 0, 900, 'PO-2'],
            [CostEntryKind::Issued, null, -3, -2, -900, '2026-0001'],
            [CostEntryKind::Returned, null, 2, 1, 900, '2026-0001'],
            [CostEntryKind::Returned, null, 1, 1, 0, '2026-0001'],
        ], $this->entries());
        self::assertSame([[null, 1, 900]], $this->layers('DICE', 'store'));
        self::assertSame(900, $this->value()->unitCost('DICE')?->minorUnits);
    }

    public function testAReturnOfASaleMadeBeforeCostingBeganComesBackUncosted(): void
    {
        $plain = new Stock($this->repository, $this->repository);
        $plain->receive('DICE', 'warehouse', 2, $this->at);
        $plain->reserve($this->order('2026-0001', ['DICE' => 2]), $this->at);
        $plain->ship('2026-0001', $this->at);

        [$stock] = $this->services(CostingMethod::FirstInFirstOut);
        $stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 2, $this->at);

        self::assertSame([[CostEntryKind::Returned, null, 2, 2, 0, '2026-0001']], $this->entries());
        self::assertSame([], $this->layers('DICE', 'warehouse'));
    }

    public function testATransferTakesTheUnitsCostAndOwnerAlong(): void
    {
        [$stock] = $this->services(CostingMethod::FirstInFirstOut);
        $stock->receive('DICE', 'warehouse', 3, $this->at, 'PO-1', $this->cad(1_000));
        $stock->receive('DICE', 'warehouse', 2, $this->at->modify('+1 day'), 'PO-2', $this->cad(1_250));
        $stock->receive('DICE', 'warehouse', 1, $this->at->modify('+2 days'), 'PO-3', owner: 'consignor-17');
        $stock->receive('DICE', 'warehouse', 1, $this->at->modify('+3 days'), 'PO-4');
        $stock->receive('DICE', 'store', 1, $this->at->modify('+4 days'), 'PO-5', $this->cad(1_500));

        $stock->transfer('DICE', 'warehouse', 'store', 7, $this->at->modify('+5 days'), 'TR-1');

        self::assertSame([], $this->layers('DICE', 'warehouse'));
        self::assertSame(
            [[null, 3, 3_000, 'PO-1', '2026-11-21'], [null, 2, 2_500, 'PO-2', '2026-11-22'], ['consignor-17', 1, 0, 'PO-3', '2026-11-23'], [null, 1, 1_500, 'PO-5', '2026-11-25']],
            array_map(static fn (CostLayer $layer): array => [$layer->owner, $layer->units(), $layer->value()->minorUnits, $layer->reference, $layer->receivedAt->format('Y-m-d')], $this->repository->layersOf('DICE', 'store')),
        );
        self::assertSame([
            [CostEntryKind::TransferredOut, null, -6, -1, -5_500, 'TR-1'],
            [CostEntryKind::TransferredOut, 'consignor-17', -1, 0, 0, 'TR-1'],
            [CostEntryKind::TransferredIn, null, 6, 1, 5_500, 'TR-1'],
            [CostEntryKind::TransferredIn, 'consignor-17', 1, 0, 0, 'TR-1'],
        ], \array_slice($this->entries(), 5));
        self::assertSame(['warehouse', 'store', 'store', 'warehouse'], array_map(static fn (CostEntry $entry): string => $entry->location, [...\array_slice($this->repository->entries, 5, 1), ...\array_slice($this->repository->entries, 7, 2), $this->repository->entries[6]]));
    }

    public function testATransferUnderAWeightedAverageJoinsTheTargetsPool(): void
    {
        [$stock] = $this->services(CostingMethod::WeightedAverage);
        $stock->receive('DICE', 'warehouse', 4, $this->at, 'PO-1', $this->cad(1_000));
        $stock->receive('DICE', 'store', 1, $this->at, 'PO-2', $this->cad(1_500));
        $stock->transfer('DICE', 'warehouse', 'store', 1, $this->at, 'TR-1');
        $stock->transfer('DICE', 'store', 'warehouse', 1, $this->at, 'TR-2');

        self::assertSame([[null, 4, 4_250]], $this->layers('DICE', 'warehouse'));
        self::assertSame([[null, 1, 1_250]], $this->layers('DICE', 'store'));
    }

    public function testAnUncostedTransferMovesNoValue(): void
    {
        [$stock] = $this->services(CostingMethod::WeightedAverage);
        $stock->receive('DICE', 'warehouse', 2, $this->at, 'PO-1');
        $stock->transfer('DICE', 'warehouse', 'store', 2, $this->at, 'TR-1');

        self::assertSame([[CostEntryKind::TransferredOut, null, -2, -2, 0, 'TR-1'], [CostEntryKind::TransferredIn, null, 2, 2, 0, 'TR-1']], \array_slice($this->entries(), 1));
        self::assertSame([], $this->layers('DICE', 'store'));
    }

    public function testAWeightedAverageMergesTheLayersFirstInFirstOutLeft(): void
    {
        $fifo = $this->costing(CostingMethod::FirstInFirstOut);
        $fifo->receive('DICE', 'warehouse', 1, $this->cad(1_000), null, 'PO-1', $this->at);
        $fifo->receive('DICE', 'warehouse', 2, $this->cad(1_300), null, 'PO-2', $this->at->modify('+1 day'));
        $fifo->receive('DICE', 'warehouse', 1, null, 'consignor-17', 'PO-3', $this->at->modify('+2 days'));

        $average = $this->costing(CostingMethod::WeightedAverage);
        self::assertSame([CostingMethod::WeightedAverage, 'CAD'], [$average->method(), $average->currency()->code]);
        $average->issue('DICE', 'warehouse', 1, 4, '2026-0001', $this->at);

        self::assertSame([[null, 2, 2_400], ['consignor-17', 1, 0]], $this->layers('DICE', 'warehouse'));
        self::assertSame(-1_200, $this->repository->entries[3]->value()->minorUnits);

        $fifo->receive('DICE', 'warehouse', 1, $this->cad(1_000), null, 'PO-4', $this->at->modify('+3 days'));
        $average->land('DICE', 'warehouse', $this->cad(3), 'PO-4', $this->at);
        self::assertSame([[null, 3, 3_403], ['consignor-17', 1, 0]], $this->layers('DICE', 'warehouse'));

        $fifo->receive('DICE', 'warehouse', 1, $this->cad(1_000), null, 'PO-5', $this->at->modify('+4 days'));
        $average->receive('DICE', 'warehouse', 1, $this->cad(1_000), null, 'PO-6', $this->at->modify('+5 days'));
        self::assertSame([[null, 5, 5_403], ['consignor-17', 1, 0]], $this->layers('DICE', 'warehouse'));
    }

    public function testAVarianceOfNothingMovesNothing(): void
    {
        $costing = $this->costing(CostingMethod::WeightedAverage);
        $costing->vary('DICE', 'warehouse', 0, 0, null, null, $this->at);

        self::assertSame([], $this->repository->entries);
    }

    public function testALandedCostIsCheckedBeforeItsLayerIsLooked(): void
    {
        $costing = $this->costing(CostingMethod::FirstInFirstOut);

        $this->refused('A cost of DICE is zero or more; -0.50 CAD is not.', fn () => $costing->land('DICE', 'warehouse', $this->cad(-50), 'PO-1', $this->at));
        $this->refused('The stock of DICE is valued in CAD; a cost in USD cannot join it.', fn () => $costing->land('DICE', 'warehouse', Money::of(50, Currency::of('USD')), 'PO-1', $this->at));
    }

    public function testAReturnGoesBackToEachOwnerForAllThatTheirIssuesAndReturnsTell(): void
    {
        $costing = $this->costing(CostingMethod::FirstInFirstOut);
        $costing->receive('ZINE', 'warehouse', 2, null, '17', 'R-1', $this->at);
        $costing->receive('ZINE', 'warehouse', 1, null, '17', 'R-2', $this->at->modify('+1 hour'));
        $costing->receive('ZINE', 'warehouse', 1, null, '18', 'R-3', $this->at->modify('+2 hours'));
        $costing->issue('ZINE', 'warehouse', 2, 4, '2026-0001', $this->at);
        $costing->issue('ZINE', 'warehouse', 2, 2, '2026-0001', $this->at);

        $costing->returnFromSale('2026-0001', 'ZINE', 'store', 4, $this->at->modify('+1 day'));

        self::assertSame([['17', 3, 0], ['18', 1, 0]], $this->layers('ZINE', 'store'));
        self::assertSame([
            [CostEntryKind::Issued, '17', -2, 0, 0, '2026-0001'],
            [CostEntryKind::Issued, '17', -1, 0, 0, '2026-0001'],
            [CostEntryKind::Issued, '18', -1, 0, 0, '2026-0001'],
            [CostEntryKind::Returned, '17', 3, 0, 0, '2026-0001'],
            [CostEntryKind::Returned, '18', 1, 0, 0, '2026-0001'],
        ], \array_slice($this->entries(), 3));

        // Everything that left came back: one more unit is the shop's, uncosted, and no owner gets a share of nothing.
        $costing->returnFromSale('2026-0001', 'ZINE', 'store', 1, $this->at->modify('+2 days'));

        self::assertSame([['17', 3, 0], ['18', 1, 0]], $this->layers('ZINE', 'store'));
        self::assertSame([[CostEntryKind::Returned, null, 1, 1, 0, '2026-0001']], \array_slice($this->entries(), 8));
    }

    public function testTheShopsUnitsComeBackFirstAtTheRunningBalanceOfTheirCostThenTheOwners(): void
    {
        $costing = $this->costing(CostingMethod::FirstInFirstOut);
        $costing->receive('DICE', 'warehouse', 3, $this->cad(111), null, 'R-1', $this->at);
        $costing->receive('DICE', 'warehouse', 2, null, '17', 'R-2', $this->at->modify('+1 hour'));
        $costing->land('DICE', 'warehouse', $this->cad(1), 'R-1', $this->at);
        $costing->issue('DICE', 'warehouse', 5, 5, '2026-0001', $this->at);

        foreach ([1, 1, 1, 1, 2] as $day => $units) {
            $costing->returnFromSale('2026-0001', 'DICE', 'store', $units, $this->at->modify(\sprintf('+%d days', $day + 1)));
        }

        // 3.34 over three units, to the cent: the remainders go to the larger fractions, then to the first.
        self::assertSame([
            [CostEntryKind::Issued, null, -3, 0, -334, '2026-0001'],
            [CostEntryKind::Issued, '17', -2, 0, 0, '2026-0001'],
            [CostEntryKind::Returned, null, 1, 0, 111, '2026-0001'],
            [CostEntryKind::Returned, null, 1, 0, 112, '2026-0001'],
            [CostEntryKind::Returned, null, 1, 0, 111, '2026-0001'],
            [CostEntryKind::Returned, '17', 1, 0, 0, '2026-0001'],
            [CostEntryKind::Returned, '17', 1, 0, 0, '2026-0001'],
            [CostEntryKind::Returned, null, 1, 1, 0, '2026-0001'],
        ], \array_slice($this->entries(), 3));
        self::assertSame([[null, 1, 111], [null, 1, 112], [null, 1, 111], ['17', 1, 0], ['17', 1, 0]], $this->layers('DICE', 'store'));
    }

    public function testATransferOfAnOwnersUnitsFromSeveralLayersIsOneEntryAndNothingMovesWithoutUnits(): void
    {
        $costing = $this->costing(CostingMethod::FirstInFirstOut);
        $costing->receive('ZINE', 'warehouse', 2, null, '17', 'R-1', $this->at);
        $costing->receive('ZINE', 'warehouse', 3, null, '17', 'R-2', $this->at->modify('+1 hour'));
        $costing->transfer('ZINE', 'warehouse', 'store', 4, 5, 'TR-1', $this->at);

        self::assertSame([['17', 2, 0], ['17', 2, 0]], $this->layers('ZINE', 'store'));
        self::assertSame([['17', 1, 0]], $this->layers('ZINE', 'warehouse'));
        self::assertSame([
            [CostEntryKind::TransferredOut, '17', -4, 0, 0, 'TR-1'],
            [CostEntryKind::TransferredIn, '17', 4, 0, 0, 'TR-1'],
        ], \array_slice($this->entries(), 2));

        $costing->transfer('ZINE', 'warehouse', 'store', 0, 1, 'TR-0', $this->at);
        self::assertCount(4, $this->repository->entries);
    }

    public function testTheOwnersAnApplicationNamesAreHeldToWhatEachHoldsAndTheShopToItsOwn(): void
    {
        $ownership = new class implements StockOwnership {
            /** @var list<array{int, int, list<array{string, int}>}> */
            public array $asked = [];

            /** @var list<array{string, int}> */
            public array $answer = [];

            #[Override]
            public function leaving(string $sku, string $location, int $units, int $shopUnits, array $consigned, ?string $reference): array
            {
                $this->asked[] = [$units, $shopUnits, $consigned];

                return $this->answer;
            }
        };
        $costing = new Costing($this->repository, CostingMethod::FirstInFirstOut, $this->cad, $ownership);
        $costing->receive('ZINE', 'warehouse', 3, null, '9', 'R-1', $this->at);
        $costing->receive('ZINE', 'warehouse', 1, null, '3', 'R-2', $this->at);

        // Nothing is asked of the owners for a difference of nothing, whatever they would answer.
        $ownership->answer = [['nobody', 1]];
        $costing->vary('ZINE', 'warehouse', 0, 4, null, 'COUNT-0', $this->at);
        self::assertSame([], $ownership->asked);

        // A level that holds fewer units than its owners hold has no shop units, never a negative number of them.
        $ownership->answer = [];
        $this->refused('The owners given for 1 units of ZINE leaving warehouse do not add up: each owner gives no more than they hold, and the shop no more than it holds.', fn () => $costing->vary('ZINE', 'warehouse', -1, 2, null, 'COUNT-1', $this->at));
        self::assertSame([[1, 0, [['9', 3], ['3', 1]]]], $ownership->asked);

        // Two shares of one owner add up against what the owner holds.
        $ownership->answer = [['9', 2], ['9', 2]];
        $this->refused('The owners given for 4 units of ZINE leaving warehouse do not add up: each owner gives no more than they hold, and the shop no more than it holds.', fn () => $costing->vary('ZINE', 'warehouse', -4, 4, null, 'COUNT-2', $this->at));

        // An owner who holds nothing gives nothing, whatever the shop could cover.
        $ownership->answer = [['nobody', 1]];
        $this->refused('The owners given for 1 units of ZINE leaving warehouse do not add up: each owner gives no more than they hold, and the shop no more than it holds.', fn () => $costing->vary('ZINE', 'warehouse', -1, 10, null, 'COUNT-4', $this->at));

        // A share of nothing, even of an owner who holds nothing, is no refusal and no entry; the owners can cover it all.
        $ownership->answer = [['9', 1], ['3', 1], ['9', 0], ['nobody', 0]];
        $costing->vary('ZINE', 'warehouse', -2, 4, VarianceReason::Lost, 'COUNT-3', $this->at);

        self::assertSame([
            [CostEntryKind::Variance, '9', -1, 0, 0, 'COUNT-3'],
            [CostEntryKind::Variance, '3', -1, 0, 0, 'COUNT-3'],
        ], \array_slice($this->entries(), 2));
        self::assertSame([['9', 2, 0]], $this->layers('ZINE', 'warehouse'));
    }

    public function testAReceiptsTermsAreBoundAsOnAPurchaseOrderLine(): void
    {
        [$stock] = $this->services(CostingMethod::WeightedAverage);

        $this->expectException(InvalidValue::class);
        $stock->receive('DICE', 'warehouse', 1, $this->at, 'PO-1', owner: ' ');
    }

    public function testACostIsZeroOrMore(): void
    {
        $costing = $this->costing(CostingMethod::WeightedAverage);

        $this->refused('A cost of DICE is zero or more; -0.01 CAD is not.', fn () => $costing->receive('DICE', 'warehouse', 1, $this->cad(-1), null, null, $this->at));
    }

    public function testTheCostOfAUnitIsRoundedHalfUp(): void
    {
        self::assertSame([3, 4, 3, 0, 5], [
            CostShare::of($this->cad(10), 1, 3)->minorUnits,
            CostShare::of($this->cad(7), 1, 2)->minorUnits,
            CostShare::of($this->cad(5), 1, 2)->minorUnits + CostShare::of($this->cad(1), 1, 2)->minorUnits - 1,
            CostShare::of($this->cad(1), 1, 3)->minorUnits,
            CostShare::of($this->cad(9), 1, 2)->minorUnits,
        ]);
    }

    /**
     * @return array{Stock, Purchasing}
     */
    private function services(CostingMethod $method): array
    {
        $costing = $this->costing($method);
        $stock = new Stock($this->repository, $this->repository, costing: $costing);

        return [$stock, new Purchasing($this->repository, $this->repository, $stock, $costing)];
    }

    private function costing(CostingMethod $method): Costing
    {
        return new Costing($this->repository, $method, $this->cad);
    }

    private function value(): InventoryValue
    {
        return new InventoryValue($this->repository);
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, $this->cad);
    }

    /**
     * @return list<array{string|null, int, int}> the owner, units and value of each layer, the oldest first
     */
    private function layers(string $sku, string $location): array
    {
        return array_map(static fn (CostLayer $layer): array => [$layer->owner, $layer->units(), $layer->value()->minorUnits], $this->repository->layersOf($sku, $location));
    }

    /**
     * @return list<array{CostEntryKind, string|null, int, int, int, string|null}>
     */
    private function entries(): array
    {
        return array_map(static fn (CostEntry $entry): array => [$entry->kind, $entry->owner, $entry->units, $entry->uncosted, $entry->value()->minorUnits, $entry->reference], $this->repository->entries);
    }

    /**
     * @return list<array{string, string, int, int}>
     */
    private function valued(DateTimeImmutable $at): array
    {
        return array_map(static fn (ValuedStock $stock): array => [$stock->sku, $stock->location, $stock->units, $stock->value->minorUnits], $this->value()->valuedStock($at));
    }

    /**
     * @param array<string, int> $products
     */
    private function order(string $number, array $products): Order
    {
        $price = Money::of(2_000, $this->cad);
        $order = Order::open($number, $this->cad);

        foreach ($products as $id => $quantity) {
            $order->add(Sellable::of(SellableReference::of('product', $id), $id, $price, 'goods'), $quantity, $price);
        }

        $order->place($this->at);

        return $order;
    }

    private function refused(string $message, callable $call): void
    {
        try {
            $call();
            self::fail(\sprintf('Not refused: %s', $message));
        } catch (CostRefused $costRefused) {
            self::assertSame($message, $costRefused->getMessage());
        }
    }
}
