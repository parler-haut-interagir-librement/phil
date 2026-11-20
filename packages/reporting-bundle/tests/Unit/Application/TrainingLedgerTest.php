<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Phil\ReportingBundle\Application\Training\TrainingLedger;
use Phil\ReportingBundle\Application\Training\TrainingStep;
use Phil\ReportingBundle\Application\Training\TrainingTotals;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Tests\Unit\Fixture\Trainings;
use Phil\ReportingBundle\Tests\Unit\Fixture\Units;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(TrainingLedger::class)]
#[CoversClass(TrainingTotals::class)]
#[CoversClass(TrainingStep::class)]
final class TrainingLedgerTest extends TestCase
{
    public function testTheTotalsOfEachQuarterAndUnitWithTheUnitsBelow(): void
    {
        $units = new Units();
        $trainings = $this->trainings();

        self::assertSame([
            ['2026-01-01', '2026-04-01', 'hq', 'Company', 1, 2, 250, 15_000, 13_000],
            ['2026-01-01', '2026-04-01', 'ops', 'Operations', 2, 2, 250, 15_000, 13_000],
            ['2026-01-01', '2026-04-01', 't1', 'Field', 3, 1, 150, 10_000, 8_000],
            ['2026-01-01', '2026-04-01', 't2', 'Support', 3, 1, 100, 5_000, 5_000],
            ['2026-01-01', '2026-04-01', 'gone', 'gone', 1, 1, 25, 1_000, 0],
            ['2026-04-01', '2026-07-01', 'hq', 'Company', 1, 1, 200, 0, 0],
            ['2026-04-01', '2026-07-01', 'sales', 'Sales', 2, 1, 200, 0, 0],
            ['2026-10-01', '2027-01-01', 'hq', 'Company', 1, 1, 100, 0, 0],
            ['2026-10-01', '2027-01-01', 'ops', 'Operations', 2, 1, 100, 0, 0],
            ['2026-10-01', '2027-01-01', 't1', 'Field', 3, 1, 100, 0, 0],
            ['2026-10-01', '2027-01-01', null, null, 0, 1, 50, 2_500, 2_500],
        ], $this->flat(new TrainingLedger($trainings, $units)->byUnit('training_hours', Trainings::year(), TrainingStep::Quarter)));

        self::assertSame(['sales' => 1, 't1' => 1, 't2' => 1, 'gone' => 1], $units->asked, 'Each path is asked for once.');
        self::assertEquals([Trainings::year()], $trainings->asked);
    }

    public function testTheTotalsOfEachUnitAlone(): void
    {
        self::assertSame([
            ['2026-01-01', '2027-01-01', 't1', 'Field', 3, 2, 250, 10_000, 8_000],
            ['2026-01-01', '2027-01-01', 't2', 'Support', 3, 1, 100, 5_000, 5_000],
            ['2026-01-01', '2027-01-01', 'sales', 'Sales', 2, 1, 200, 0, 0],
            ['2026-01-01', '2027-01-01', 'gone', 'gone', 1, 1, 25, 1_000, 0],
            ['2026-01-01', '2027-01-01', null, null, 0, 1, 50, 2_500, 2_500],
        ], $this->flat(new TrainingLedger($this->trainings(), new Units())->byUnit('training_hours', Trainings::year(), null, false)));
    }

    public function testTheTotalsWithinAUnit(): void
    {
        self::assertSame([
            ['2026-01-01', '2027-01-01', 'ops', 'Operations', 2, 3, 350, 15_000, 13_000],
            ['2026-01-01', '2027-01-01', 't1', 'Field', 3, 2, 250, 10_000, 8_000],
            ['2026-01-01', '2027-01-01', 't2', 'Support', 3, 1, 100, 5_000, 5_000],
        ], $this->flat(new TrainingLedger($this->trainings(), new Units())->byUnit('training_hours', Trainings::year(), null, true, 'ops')));

        self::assertSame([
            ['2026-01-01', '2027-01-01', 't2', 'Support', 3, 1, 100, 5_000, 5_000],
        ], $this->flat(new TrainingLedger($this->trainings(), new Units())->byUnit('training_hours', Trainings::year(), null, false, 't2')));

        self::assertSame([], new TrainingLedger($this->trainings(), new Units())->byUnit('training_hours', Trainings::year(), null, true, 'nowhere'));
    }

    public function testWithoutADirectoryEachUnitIsNamedByItsIdentifier(): void
    {
        self::assertSame([
            ['2026-01-01', '2027-01-01', 'gone', 'gone', 1, 1, 25, 1_000, 0],
            ['2026-01-01', '2027-01-01', 'sales', 'sales', 1, 1, 200, 0, 0],
            ['2026-01-01', '2027-01-01', 't1', 't1', 1, 2, 250, 10_000, 8_000],
            ['2026-01-01', '2027-01-01', 't2', 't2', 1, 1, 100, 5_000, 5_000],
            ['2026-01-01', '2027-01-01', null, null, 0, 1, 50, 2_500, 2_500],
        ], $this->flat(new TrainingLedger($this->trainings())->byUnit('training_hours', Trainings::year(), null)));
    }

    public function testUnitsOfTheSameNameAreOrderedByIdentifier(): void
    {
        $trainings = new Trainings([
            new TrainingActivity(Trainings::on('2026-05-01'), 1, unit: 'twin-b'),
            new TrainingActivity(Trainings::on('2026-05-01'), 2, unit: 'twin-a'),
            new TrainingActivity(Trainings::on('2026-05-01'), 3, unit: 'twin-b'),
        ]);

        self::assertSame([
            ['2026-01-01', '2027-01-01', 'twin-a', 'Twin', 2, 1, 2, 0, 0],
            ['2026-01-01', '2027-01-01', 'twin-b', 'Twin', 2, 2, 4, 0, 0],
        ], $this->flat(new TrainingLedger($trainings, new Units())->byUnit('training_hours', Trainings::year(), null, false)));
    }

    public function testUnitsNamedByNumbers(): void
    {
        $trainings = new Trainings([
            new TrainingActivity(Trainings::on('2026-05-01'), 1, unit: '7'),
            new TrainingActivity(Trainings::on('2026-05-01'), 2, unit: '42'),
        ]);

        self::assertSame([
            ['2026-01-01', '2027-01-01', '42', '42', 1, 1, 2, 0, 0],
            ['2026-01-01', '2027-01-01', '7', '7', 1, 1, 1, 0, 0],
        ], self::flat(new TrainingLedger($trainings)->byUnit('training_hours', Trainings::year(), null)));
    }

    public function testEveryMonthHasItsTotalsWhenEveryUnitCountsTogether(): void
    {
        $totals = new TrainingLedger($this->trainings(), new Units())->overall(Trainings::year(), TrainingStep::Month);

        self::assertCount(12, $totals);
        self::assertSame([
            [1, 100, 5_000, 5_000],
            [1, 150, 10_000, 8_000],
            [1, 25, 1_000, 0],
            [1, 200, 0, 0],
            [0, 0, 0, 0],
            [0, 0, 0, 0],
            [0, 0, 0, 0],
            [0, 0, 0, 0],
            [0, 0, 0, 0],
            [0, 0, 0, 0],
            [1, 100, 0, 0],
            [1, 50, 2_500, 2_500],
        ], array_map(static fn (TrainingTotals $total): array => [$total->activities, $total->hundredthsOfHours, $total->cost, $total->eligibleCost], $totals));
        self::assertSame(['2026-12-01', '2027-01-01', []], [$totals[11]->period->start->format('Y-m-d'), $totals[11]->period->end->format('Y-m-d'), $totals[11]->path]);
        self::assertNull($totals[11]->unit());

        $year = new TrainingLedger($this->trainings())->overall(Trainings::year(), null);
        self::assertSame([[6, 625, 18_500, 15_500]], array_map(static fn (TrainingTotals $total): array => [$total->activities, $total->hundredthsOfHours, $total->cost, $total->eligibleCost], $year));
        self::assertEquals(Trainings::year(), $year[0]->period);

        $years = new TrainingLedger($this->trainings())->overall(new Period(Trainings::on('2025-01-01'), Trainings::on('2027-01-01')), TrainingStep::Year);
        self::assertSame([[1, 999], [6, 625]], array_map(static fn (TrainingTotals $total): array => [$total->activities, $total->hundredthsOfHours], $years));
    }

    public function testTheTotalsAreBounded(): void
    {
        $three = new TrainingLedger($this->trainings(), new Units(), 3);
        self::assertCount(3, $three->byUnit('training_hours', Trainings::year(), null, false, 'hq'));

        $this->expectExceptionObject(ReportTooLarge::of('training_hours', 2));

        new TrainingLedger($this->trainings(), new Units(), 2)->byUnit('training_hours', Trainings::year(), null, false, 'hq');
    }

    public function testTheDefaultBound(): void
    {
        self::assertSame(50_000, new ReflectionProperty(TrainingLedger::class, 'maxTotals')->getValue(new TrainingLedger(new Trainings())));
        self::assertSame('CAD', new TrainingLedger(new Trainings())->amounts()->currency);
        self::assertSame('JPY', new TrainingLedger(new Trainings([], 'JPY'))->amounts()->currency);
    }

    public function testTheSteps(): void
    {
        self::assertSame(['1 month', '3 months', '1 year'], array_map(static fn (TrainingStep $step): string => $step->interval(), TrainingStep::cases()));
    }

    private function trainings(): Trainings
    {
        return new Trainings([
            new TrainingActivity(Trainings::on('2026-04-01'), 200, unit: 'sales'),
            new TrainingActivity(Trainings::on('2026-12-31 23:59:59'), 50, 2_500, 2_500),
            new TrainingActivity(Trainings::on('2026-02-10'), 150, 10_000, 8_000, 't1'),
            new TrainingActivity(Trainings::on('2026-01-01'), 100, 5_000, 5_000, 't2'),
            new TrainingActivity(Trainings::on('2027-01-01'), 999, 1, 1, 't1'),
            new TrainingActivity(Trainings::on('2025-12-31 23:59:59'), 999, 1, 1, 't1'),
            new TrainingActivity(Trainings::on('2026-03-31 23:59:59'), 25, 1_000, 0, 'gone'),
            new TrainingActivity(Trainings::on('2026-11-15'), 100, unit: 't1'),
        ]);
    }

    /**
     * @param list<TrainingTotals> $totals
     *
     * @return list<array{string, string, ?string, ?string, int, int, int, int, int}>
     */
    private function flat(array $totals): array
    {
        return array_map(static fn (TrainingTotals $total): array => [
            $total->period->start->format('Y-m-d'),
            $total->period->end->format('Y-m-d'),
            $total->unit()?->id,
            $total->unit()?->name,
            \count($total->path),
            $total->activities,
            $total->hundredthsOfHours,
            $total->cost,
            $total->eligibleCost,
        ], $totals);
    }
}
