<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Unit\Replenishment;

use DateTimeImmutable;
use Generator;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Exception\ProposalAlreadyDecided;
use Phil\InventoryAlerts\Internal\Check;
use Phil\InventoryAlerts\Replenishment\ProposalLine;
use Phil\InventoryAlerts\Replenishment\ProposalState;
use Phil\InventoryAlerts\Replenishment\PurchaseProposal;
use Phil\InventoryAlerts\Replenishment\ReplenishmentPlanner;
use Phil\InventoryAlerts\Replenishment\ReplenishmentRule;
use Phil\InventoryAlerts\Stock\StockLevel;
use Phil\InventoryAlerts\Tests\Support\MemoryStock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReplenishmentRule::class)]
#[CoversClass(ReplenishmentPlanner::class)]
#[CoversClass(PurchaseProposal::class)]
#[CoversClass(ProposalLine::class)]
#[CoversClass(ProposalState::class)]
#[CoversClass(ProposalAlreadyDecided::class)]
#[CoversClass(StockLevel::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class ReplenishmentPlannerTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function minMaxCases(): iterable
    {
        yield 'at the minimum' => [10, 1, 40];
        yield 'above the minimum' => [11, 1, 0];
        yield 'under zero' => [-5, 1, 55];
        yield 'rounded to the pack' => [10, 12, 48];
        yield 'a pack that fits' => [10, 8, 40];
    }

    #[DataProvider('minMaxCases')]
    public function testAMinMaxRuleOrdersUpToTheMaximum(int $position, int $pack, int $quantity): void
    {
        self::assertSame($quantity, ReplenishmentRule::minMax('MUG', 'store', 'acme', 10, 50, $pack)->quantityFor($position));
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function fixedQuantityCases(): iterable
    {
        yield 'at the reorder point' => [20, 1, 30];
        yield 'above it' => [21, 1, 0];
        // 30 would bring −10 to 20, still at the reorder point: twice.
        yield 'one quantity short' => [-10, 1, 60];
        yield 'just above one quantity short' => [-9, 1, 30];
        yield 'far under' => [-15, 1, 60];
        yield 'rounded to the pack' => [20, 7, 35];
    }

    #[DataProvider('fixedQuantityCases')]
    public function testAFixedQuantityRuleOrdersEnoughQuantitiesToRiseAboveTheReorderPoint(int $position, int $pack, int $quantity): void
    {
        $rule = ReplenishmentRule::fixedQuantity('MUG', 'store', 'acme', 20, 30, $pack);

        self::assertSame($quantity, $rule->quantityFor($position));
        self::assertNull($rule->maximum);
        self::assertSame(30, $rule->orderQuantity);
    }

    public function testARuleKeepsWhatItWasGiven(): void
    {
        $rule = ReplenishmentRule::minMax('MUG', 'store', 'acme', 10, 50, 6);

        self::assertSame(['MUG', 'store', 'acme', 10, 50, null, 6], [$rule->sku, $rule->location, $rule->supplier, $rule->reorderPoint, $rule->maximum, $rule->orderQuantity, $rule->packSize]);
    }

    /**
     * @return iterable<string, array{callable(): ReplenishmentRule, string}>
     */
    public static function refusedRules(): iterable
    {
        yield 'maximum at the minimum' => [static fn (): ReplenishmentRule => ReplenishmentRule::minMax('MUG', 'store', 'acme', 10, 10), 'The maximum must be above the minimum.'];
        yield 'negative minimum' => [static fn (): ReplenishmentRule => ReplenishmentRule::minMax('MUG', 'store', 'acme', -1, 10), 'The reorder point must be a whole number from 0 to 1,000,000,000,000.'];
        yield 'no pack' => [static fn (): ReplenishmentRule => ReplenishmentRule::minMax('MUG', 'store', 'acme', 1, 10, 0), 'The pack size must be a whole number from 1 to 1,000,000,000,000.'];
        yield 'maximum above the bound' => [static fn (): ReplenishmentRule => ReplenishmentRule::minMax('MUG', 'store', 'acme', 1, 1_000_000_000_001), 'The maximum must be a whole number from 1 to 1,000,000,000,000.'];
        yield 'no quantity' => [static fn (): ReplenishmentRule => ReplenishmentRule::fixedQuantity('MUG', 'store', 'acme', 1, 0), 'The order quantity must be a whole number from 1 to 1,000,000,000,000.'];
        yield 'empty supplier' => [static fn (): ReplenishmentRule => ReplenishmentRule::fixedQuantity('MUG', 'store', '', 1, 5), 'The supplier must have 1 to 128 bytes and no control character.'];
        yield 'empty location' => [static fn (): ReplenishmentRule => ReplenishmentRule::fixedQuantity('MUG', '', 'acme', 1, 5), 'The location must have 1 to 128 bytes and no control character.'];
        yield 'empty SKU' => [static fn (): ReplenishmentRule => ReplenishmentRule::fixedQuantity('', 'store', 'acme', 1, 5), 'The SKU must have 1 to 128 bytes and no control character.'];
    }

    /**
     * @param callable(): ReplenishmentRule $make
     */
    #[DataProvider('refusedRules')]
    public function testARuleOutOfRangeIsRefused(callable $make, string $message): void
    {
        try {
            $make();
            self::fail('The rule was accepted.');
        } catch (InvalidInput $invalidInput) {
            // The whole message: the bound is written without decimals.
            self::assertSame($message, $invalidInput->getMessage());
        }
    }

    public function testThePlannerProposesOneOrderPerSupplierWithLinesByLocationThenSku(): void
    {
        $stock = new MemoryStock()
            // Position 12 − 4 + 0 = 8, at or under 10: up to 50.
            ->set(new StockLevel('MUG', 'store', 12, 4))
            // Position 3 + 10 = 13, above 10: nothing.
            ->set(new StockLevel('MUG', 'depot', 3, 0, 10))
            // Position 5: at 20 or under, by 24.
            ->set(new StockLevel('CAP', 'depot', 5));
        $at = new DateTimeImmutable('2026-11-21 09:00');

        $proposals = new ReplenishmentPlanner($stock)->propose([
            ReplenishmentRule::minMax('MUG', 'store', 'acme', 10, 50),
            ReplenishmentRule::minMax('MUG', 'depot', 'acme', 10, 50),
            ReplenishmentRule::fixedQuantity('CAP', 'depot', 'hats', 20, 24),
            // Never held at the store: position 0.
            ReplenishmentRule::fixedQuantity('BAG', 'store', 'acme', 0, 6),
            ReplenishmentRule::minMax('CUP', 'depot', 'acme', 5, 8),
        ], $at);

        self::assertCount(2, $proposals);
        [$acme, $hats] = $proposals;
        self::assertSame('acme', $acme->supplier);
        self::assertSame(ProposalState::Proposed, $acme->state);
        self::assertSame($at, $acme->proposedAt);
        self::assertNull($acme->decidedBy);
        self::assertNull($acme->decidedAt);
        self::assertEquals([
            new ProposalLine('CUP', 'depot', 8, 0, 5),
            new ProposalLine('BAG', 'store', 6, 0, 0),
            new ProposalLine('MUG', 'store', 42, 8, 10),
        ], $acme->lines);
        self::assertSame('hats', $hats->supplier);
        self::assertEquals([new ProposalLine('CAP', 'depot', 24, 5, 20)], $hats->lines);
        self::assertSame(['depot', 'store'], array_keys($acme->byLocation()));
        self::assertCount(2, $acme->byLocation()['store']);
        self::assertSame(56, $acme->totalUnits());
        self::assertSame(5, $stock->reads);
    }

    public function testSkusOfALocationAreSortedByCode(): void
    {
        $proposals = new ReplenishmentPlanner(new MemoryStock())->propose([
            ReplenishmentRule::fixedQuantity('B', 'store', 'acme', 0, 1),
            ReplenishmentRule::fixedQuantity('A', 'store', 'acme', 0, 1),
        ], new DateTimeImmutable());

        self::assertSame(['A', 'B'], array_map(static fn (ProposalLine $line): string => $line->sku, $proposals[0]->lines));
    }

    public function testNothingToOrderProposesNothing(): void
    {
        $stock = new MemoryStock()->set(new StockLevel('MUG', 'store', 100));

        self::assertSame([], new ReplenishmentPlanner($stock)->propose([ReplenishmentRule::minMax('MUG', 'store', 'acme', 10, 50)], new DateTimeImmutable()));
    }

    public function testTwoRulesForOneSkuAndLocationAreRefused(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The list of rules must have one rule per SKU and location; "MUG" at "store" has two.');

        new ReplenishmentPlanner(new MemoryStock())->propose([
            ReplenishmentRule::minMax('MUG', 'store', 'acme', 10, 50),
            ReplenishmentRule::minMax('MUG', 'store', 'other', 10, 50),
        ], new DateTimeImmutable());
    }

    public function testTheSameSkuAtTwoLocationsIsNotADuplicate(): void
    {
        $proposals = new ReplenishmentPlanner(new MemoryStock())->propose([
            ReplenishmentRule::fixedQuantity('MUG', 'a', 'acme', 0, 1),
            ReplenishmentRule::fixedQuantity('MUG', 'b', 'acme', 0, 1),
        ], new DateTimeImmutable());

        self::assertCount(2, $proposals[0]->lines);
    }

    public function testASkuAndALocationThatSpellTheSameWordsTogetherAreNotADuplicate(): void
    {
        $proposals = new ReplenishmentPlanner(new MemoryStock())->propose([
            ReplenishmentRule::fixedQuantity('MUG', 'store', 'acme', 0, 1),
            ReplenishmentRule::fixedQuantity('MUGs', 'tore', 'acme', 0, 1),
        ], new DateTimeImmutable());

        self::assertCount(2, $proposals[0]->lines);
    }

    public function testAFixedQuantityRuleOrdersSingleUnitsUnlessAPackIsGiven(): void
    {
        $rule = ReplenishmentRule::fixedQuantity('MUG', 'store', 'acme', 0, 5);

        self::assertSame(1, $rule->packSize);
        self::assertSame(5, $rule->quantityFor(0));
    }

    public function testAProposalNamesItsSupplier(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The supplier must have 1 to 128 bytes and no control character.');

        new PurchaseProposal('', [new ProposalLine('MUG', 'store', 40, 10, 10)], new DateTimeImmutable());
    }

    public function testAt100000RulesThePlannerStops(): void
    {
        $rules = static function (int $count): Generator {
            for ($i = 0; $i < $count; ++$i) {
                yield ReplenishmentRule::minMax('S'.$i, 'store', 'acme', 0, 1);
            }
        };
        $stock = new MemoryStock();

        // Position 0 is at the minimum of each rule: 100,000 lines.
        self::assertSame(100_000, new ReplenishmentPlanner($stock)->propose($rules(100_000), new DateTimeImmutable())[0]->totalUnits());

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The list of rules must have at most 100,000 rules.');

        new ReplenishmentPlanner($stock)->propose($rules(100_001), new DateTimeImmutable());
    }

    public function testAProposalIsApprovedByAPerson(): void
    {
        $proposal = $this->proposal();
        $at = new DateTimeImmutable('2026-11-21 10:00');
        $approved = $proposal->approve('marie', $at);

        self::assertSame(ProposalState::Approved, $approved->state);
        self::assertSame('marie', $approved->decidedBy);
        self::assertSame($at, $approved->decidedAt);
        self::assertSame($proposal->lines, $approved->lines);
        self::assertSame($proposal->proposedAt, $approved->proposedAt);
        self::assertSame(ProposalState::Proposed, $proposal->state);
    }

    public function testAProposalIsRejectedByAPerson(): void
    {
        $rejected = $this->proposal()->reject('marie', new DateTimeImmutable());

        self::assertSame(ProposalState::Rejected, $rejected->state);
        self::assertSame('marie', $rejected->decidedBy);
    }

    public function testADecidedProposalCannotBeDecidedAgain(): void
    {
        $this->expectException(ProposalAlreadyDecided::class);
        $this->expectExceptionMessageIsOrContains('The purchase proposal for supplier "acme" is already decided.');

        $this->proposal()->reject('marie', new DateTimeImmutable())->approve('marie', new DateTimeImmutable());
    }

    public function testADecidedProposalCannotChangeItsLines(): void
    {
        $this->expectException(ProposalAlreadyDecided::class);

        $this->proposal()->approve('marie', new DateTimeImmutable())->withQuantity('MUG', 'store', 3);
    }

    public function testTheDeciderIsNamed(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The person who decides must have 1 to 128 bytes and no control character.');

        $this->proposal()->approve('', new DateTimeImmutable());
    }

    public function testAPersonChangesTheQuantityOfALineBeforeApproving(): void
    {
        $proposal = $this->proposal()->withQuantity('CAP', 'store', 7);

        self::assertEquals([
            new ProposalLine('MUG', 'store', 40, 10, 10),
            new ProposalLine('CAP', 'store', 7, 2, 5),
            new ProposalLine('MUG', 'depot', 5, 0, 1),
        ], $proposal->lines);
        self::assertSame(ProposalState::Proposed, $proposal->state);
        self::assertSame('acme', $proposal->supplier);
        self::assertSame($this->proposal()->proposedAt->format('c'), $proposal->proposedAt->format('c'));
    }

    public function testAQuantityOfZeroDropsTheLine(): void
    {
        $proposal = $this->proposal()->withQuantity('MUG', 'store', 0);

        self::assertSame(['CAP', 'MUG'], array_map(static fn (ProposalLine $line): string => $line->sku, $proposal->lines));
        self::assertSame(['store', 'depot'], array_map(static fn (ProposalLine $line): string => $line->location, $proposal->lines));
    }

    public function testALineMustBeOneOfTheProposal(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The line must be one of the proposal.');

        // The SKU of one line and the location of another.
        $this->proposal()->withQuantity('CAP', 'depot', 3);
    }

    public function testANegativeQuantityIsRefused(): void
    {
        $this->expectException(InvalidInput::class);

        $this->proposal()->withQuantity('MUG', 'store', -1);
    }

    public function testTheLastLineIsNotDropped(): void
    {
        $proposal = new PurchaseProposal('acme', [new ProposalLine('MUG', 'store', 40, 10, 10)], new DateTimeImmutable());

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The proposal must keep at least one line; reject it instead.');

        $proposal->withQuantity('MUG', 'store', 0);
    }

    private function proposal(): PurchaseProposal
    {
        return new PurchaseProposal('acme', [
            new ProposalLine('MUG', 'store', 40, 10, 10),
            new ProposalLine('CAP', 'store', 3, 2, 5),
            new ProposalLine('MUG', 'depot', 5, 0, 1),
        ], new DateTimeImmutable('2026-11-21 09:00'));
    }
}
