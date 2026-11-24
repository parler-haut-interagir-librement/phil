<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Unit\LowStock;

use DateTimeImmutable;
use Generator;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;
use Phil\InventoryAlerts\LowStock\LowStockAlert;
use Phil\InventoryAlerts\LowStock\LowStockDigest;
use Phil\InventoryAlerts\LowStock\LowStockMonitor;
use Phil\InventoryAlerts\LowStock\LowStockSituation;
use Phil\InventoryAlerts\Stock\StockLevel;
use Phil\InventoryAlerts\Stock\StockMovement;
use Phil\InventoryAlerts\Tests\Support\MemoryLowStockStates;
use Phil\InventoryAlerts\Tests\Support\MemoryStock;
use Phil\InventoryAlerts\Tests\Support\RecordingStaffNotifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(LowStockMonitor::class)]
#[CoversClass(LowStockAlert::class)]
#[CoversClass(LowStockDigest::class)]
#[CoversClass(LowStockSituation::class)]
#[CoversClass(StockLevel::class)]
#[CoversClass(StockMovement::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class LowStockMonitorTest extends TestCase
{
    private MemoryStock $stock;

    private MemoryLowStockStates $states;

    private RecordingStaffNotifier $notifier;

    private int $sequence = 0;

    protected function setUp(): void
    {
        $this->stock = new MemoryStock()->watch('MUG', 'store', 5)->watch('CAP', 'store', 2)->watch('MUG', 'depot', 10);
        $this->states = new MemoryLowStockStates();
        $this->notifier = new RecordingStaffNotifier();
    }

    /**
     * @return iterable<string, array{int, int, ?LowStockSituation}>
     */
    public static function situations(): iterable
    {
        yield 'above' => [6, 5, null];
        yield 'at the threshold' => [5, 5, LowStockSituation::Low];
        yield 'under it' => [1, 5, LowStockSituation::Low];
        yield 'none left' => [0, 5, LowStockSituation::Out];
        yield 'more reserved than held' => [-2, 5, LowStockSituation::Out];
        yield 'a threshold of zero' => [1, 0, null];
    }

    #[DataProvider('situations')]
    public function testTheSituationComparesTheAvailableUnitsWithTheThreshold(int $available, int $threshold, ?LowStockSituation $situation): void
    {
        self::assertSame($situation, LowStockSituation::of($available, $threshold));
    }

    public function testAMovementThatBringsAStockToItsThresholdAlertsTheStaffOfTheLocation(): void
    {
        $this->stock->set(new StockLevel('MUG', 'store', 9, 4));
        $movement = $this->movement('MUG', 'store', -3);

        $digests = $this->monitor()->record([$movement]);

        self::assertCount(1, $digests);
        self::assertSame($digests, $this->notifier->digests);
        self::assertSame('store', $digests[0]->location);
        self::assertEquals([new LowStockAlert('MUG', 'store', LowStockSituation::Low, 5, 5, $movement)], $digests[0]->alerts);
        self::assertSame([$movement->id], $digests[0]->movementIds());
        self::assertSame(LowStockSituation::Low, $this->states->situation('MUG', 'store'));
    }

    public function testASkuAndALocationThatSpellTheSameWordsTogetherStayApart(): void
    {
        $this->stock->watch('MUGs', 'tore', 5)->set(new StockLevel('MUGs', 'tore', 1))->set(new StockLevel('MUG', 'store', 1));
        $store = $this->movement('MUG', 'store', -1);
        $tore = $this->movement('MUGs', 'tore', -1);

        $digests = $this->monitor()->record([$store, $tore]);

        self::assertSame(['store', 'tore'], array_map(static fn (LowStockDigest $digest): string => $digest->location, $digests));
        self::assertSame([$store->id], $digests[0]->movementIds());
        self::assertSame([$tore->id], $digests[1]->movementIds());
    }

    public function testTheSameSituationIsNotToldTwice(): void
    {
        $this->stock->set(new StockLevel('MUG', 'store', 4));
        $this->monitor()->record([$this->movement('MUG', 'store', -1)]);
        $this->stock->set(new StockLevel('MUG', 'store', 3));

        self::assertSame([], $this->monitor()->record([$this->movement('MUG', 'store', -1)]));
        self::assertCount(1, $this->notifier->digests);
    }

    public function testRunningOutAfterBeingLowIsToldAgain(): void
    {
        $this->stock->set(new StockLevel('MUG', 'store', 4));
        $this->monitor()->record([$this->movement('MUG', 'store', -1)]);
        $this->stock->set(new StockLevel('MUG', 'store', 0));

        $digests = $this->monitor()->record([$this->movement('MUG', 'store', -4)]);

        self::assertSame(LowStockSituation::Out, $digests[0]->alerts[0]->situation);
        self::assertSame(LowStockSituation::Out, $this->states->situation('MUG', 'store'));
    }

    public function testAStockThatRoseAboveItsThresholdIsToldAgainWhenItFalls(): void
    {
        $this->stock->set(new StockLevel('MUG', 'store', 4));
        $this->monitor()->record([$this->movement('MUG', 'store', -1)]);
        $this->stock->set(new StockLevel('MUG', 'store', 40));

        self::assertSame([], $this->monitor()->record([$this->movement('MUG', 'store', 36)]));
        self::assertNull($this->states->situation('MUG', 'store'));
        self::assertSame(1, $this->states->forgets);

        $this->stock->set(new StockLevel('MUG', 'store', 5));

        self::assertCount(1, $this->monitor()->record([$this->movement('MUG', 'store', -35)]));
        self::assertCount(2, $this->notifier->digests);
    }

    public function testAStockAboveItsThresholdForgetsNothingItWasNeverTold(): void
    {
        $this->stock->set(new StockLevel('MUG', 'store', 40));

        self::assertSame([], $this->monitor()->record([$this->movement('MUG', 'store', 1)]));
        self::assertSame(0, $this->states->forgets);
    }

    public function testAlertsAreGroupedByLocationInTheOrderOfTheMovements(): void
    {
        $this->stock
            ->set(new StockLevel('MUG', 'store', 1))
            ->set(new StockLevel('MUG', 'depot', 0))
            ->set(new StockLevel('CAP', 'store', 2));
        $mug = $this->movement('MUG', 'store', -1);
        $depot = $this->movement('MUG', 'depot', -1);
        $cap = $this->movement('CAP', 'store', -1);

        $digests = $this->monitor()->record([$mug, $depot, $cap]);

        self::assertSame(['store', 'depot'], array_map(static fn (LowStockDigest $digest): string => $digest->location, $digests));
        self::assertSame([$mug->id, $cap->id], $digests[0]->movementIds());
        self::assertSame([$depot->id], $digests[1]->movementIds());
        self::assertSame(LowStockSituation::Out, $digests[1]->alerts[0]->situation);
        self::assertSame(10, $digests[1]->alerts[0]->threshold);
        self::assertSame(0, $digests[1]->alerts[0]->available);
    }

    public function testTheLastMovementOfASkuAtALocationNamesTheAlert(): void
    {
        $this->stock->set(new StockLevel('MUG', 'store', 2))->set(new StockLevel('CAP', 'store', 1));
        $first = $this->movement('MUG', 'store', -1);
        $cap = $this->movement('CAP', 'store', -1);
        $last = $this->movement('MUG', 'store', -1);

        $digests = $this->monitor()->record([$first, $cap, $last]);

        self::assertSame([$last->id, $cap->id], $digests[0]->movementIds());
        self::assertSame(2, $this->stock->reads);
    }

    public function testASkuNobodyWatchesIsNotRead(): void
    {
        $this->stock->set(new StockLevel('BAG', 'store', 0));

        self::assertSame([], $this->monitor()->record([$this->movement('BAG', 'store', -1)]));
        self::assertSame(0, $this->stock->reads);
    }

    public function testALocationThatNeverHeldTheSkuIsOut(): void
    {
        $digests = $this->monitor()->record([$this->movement('MUG', 'depot', 0)]);

        self::assertSame(LowStockSituation::Out, $digests[0]->alerts[0]->situation);
    }

    public function testASituationIsRememberedOnlyOnceItsDigestIsSent(): void
    {
        $this->stock->set(new StockLevel('MUG', 'store', 1))->set(new StockLevel('MUG', 'depot', 1));
        $notifier = new RecordingStaffNotifier(failOnLocation: 'depot');
        $monitor = new LowStockMonitor($this->stock, $this->stock, $this->states, $notifier);

        try {
            $monitor->record([$this->movement('MUG', 'store', -1), $this->movement('MUG', 'depot', -1)]);
            self::fail('The notifier failed.');
        } catch (RuntimeException) {
        }

        self::assertSame(LowStockSituation::Low, $this->states->situation('MUG', 'store'));
        self::assertNull($this->states->situation('MUG', 'depot'));
    }

    public function testANegativeThresholdIsRefused(): void
    {
        $this->stock->watch('MUG', 'store', -1);

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The low-stock threshold must be a whole number from 0 to 1,000,000,000,000.');

        $this->monitor()->record([$this->movement('MUG', 'store', -1)]);
    }

    public function testAt10000MovementsTheMonitorStops(): void
    {
        $movements = function (int $count): Generator {
            for ($i = 0; $i < $count; ++$i) {
                yield $this->movement('BAG', 'store', -1);
            }
        };

        self::assertSame([], $this->monitor()->record($movements(10_000)));

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The list of movements must have at most 10,000 movements.');

        $this->monitor()->record($movements(10_001));
    }

    public function testAStockLevelGivesItsAvailableUnitsAndItsPosition(): void
    {
        $level = new StockLevel('MUG', 'store', 10, 4, 6);

        self::assertSame(6, $level->available());
        self::assertSame(12, $level->position());
        self::assertSame(-2, new StockLevel('MUG', 'store', 2, 4)->available());
        self::assertEquals(new StockLevel('MUG', 'store', 0, 0, 0), StockLevel::empty('MUG', 'store'));
    }

    /**
     * @return iterable<string, array{callable(): object, string}>
     */
    public static function refusedStock(): iterable
    {
        yield 'negative on hand' => [static fn (): StockLevel => new StockLevel('MUG', 'store', -1), 'The quantity on hand must be a whole number from 0 to 1,000,000,000,000.'];
        yield 'negative reserved' => [static fn (): StockLevel => new StockLevel('MUG', 'store', 1, -1), 'The reserved quantity must be a whole number from 0 to 1,000,000,000,000.'];
        yield 'negative on order' => [static fn (): StockLevel => new StockLevel('MUG', 'store', 1, 0, -1), 'The quantity on order must be a whole number from 0 to 1,000,000,000,000.'];
        yield 'above the bound' => [static fn (): StockLevel => new StockLevel('MUG', 'store', 1_000_000_000_001), 'The quantity on hand must be a whole number from 0 to 1,000,000,000,000.'];
        yield 'a control character' => [static fn (): StockLevel => new StockLevel("MUG\n", 'store', 1), 'The SKU must have 1 to 128 bytes and no control character.'];
        yield 'a delete character' => [static fn (): StockLevel => new StockLevel('MUG', "store\x7F", 1), 'The location must have 1 to 128 bytes and no control character.'];
        yield 'a code too long' => [static fn (): StockLevel => new StockLevel(str_repeat('M', 129), 'store', 1), 'The SKU must have 1 to 128 bytes and no control character.'];
        yield 'a movement without identifier' => [static fn (): StockMovement => new StockMovement('', 'MUG', 'store', 1, new DateTimeImmutable()), 'The movement identifier must have 1 to 128 bytes and no control character.'];
        yield 'a movement without SKU' => [static fn (): StockMovement => new StockMovement('m', '', 'store', 1, new DateTimeImmutable()), 'The SKU must have 1 to 128 bytes and no control character.'];
        yield 'a movement without location' => [static fn (): StockMovement => new StockMovement('m', 'MUG', '', 1, new DateTimeImmutable()), 'The location must have 1 to 128 bytes and no control character.'];
    }

    /**
     * @param callable(): object $make
     */
    #[DataProvider('refusedStock')]
    public function testStockOutOfRangeIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testACodeOf128BytesIsAccepted(): void
    {
        self::assertSame(str_repeat('M', 128), new StockLevel(str_repeat('M', 128), 'store', 1_000_000_000_000)->sku);
    }

    private function monitor(): LowStockMonitor
    {
        return new LowStockMonitor($this->stock, $this->stock, $this->states, $this->notifier);
    }

    private function movement(string $sku, string $location, int $change): StockMovement
    {
        return new StockMovement('m'.++$this->sequence, $sku, $location, $change, new DateTimeImmutable('2026-11-21 09:00'));
    }
}
