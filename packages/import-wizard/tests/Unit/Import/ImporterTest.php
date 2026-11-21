<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Import;

use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Exception\PlanChanged;
use Phil\ImportWizard\Import\Batch;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Import\ImportResult;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\ImportPlan;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Plan\PlannedRow;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Plan\RowRejection;
use Phil\ImportWizard\Tests\Support\MemoryTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Importer::class)]
#[CoversClass(Batch::class)]
#[CoversClass(ImportResult::class)]
final class ImporterTest extends TestCase
{
    private const string UNITS = "Code,Name,Parent\nmath,Mathematics,sci\nsci,Sciences,\nalg,Algebra,math\ngeo,Geography,\nart,Arts,\n";

    private function plan(string $csv, MemoryTarget $target): ImportPlan
    {
        return new ImportPlanner()->plan(CsvRowSource::fromString($csv), LmsProfiles::organisationalUnits(), $target);
    }

    /**
     * @return array<int, list<string>>
     */
    private function rejections(ImportResult $result): array
    {
        $rejections = [];

        foreach ($result->rejections as $rejection) {
            $rejections[$rejection->line] = array_map(static fn (CellError $error): string => $error->column.' '.$error->reason->value.' '.$error->relatedLine, $rejection->errors);
        }

        return $rejections;
    }

    public function testItWritesThePlanInBatchesNeverHoldingARowAndTheRowItRefersTo(): void
    {
        $target = new MemoryTarget('organisational-unit');
        $result = new Importer(500)->commit($this->plan(self::UNITS, $target), $target);

        self::assertSame([[3], [2], [4, 5, 6]], $target->batchLines());
        self::assertSame([1, 2, 3], array_map(static fn (Batch $batch): int => $batch->number, $target->batches));
        self::assertSame('organisational-unit', $target->batches[0]->type);
        self::assertSame(5, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame(3, $result->batches);
        self::assertSame([], $result->rejections);
    }

    public function testABatchHoldsAtMostItsSize(): void
    {
        $target = new MemoryTarget('organisational-unit');
        $result = new Importer(2)->commit($this->plan("Code,Name\na,A\nb,B\nc,C\nd,D\ne,E\n", $target), $target);

        self::assertSame([[2, 3], [4, 5], [6]], $target->batchLines());
        self::assertSame(3, $result->batches);

        $single = new MemoryTarget('organisational-unit');
        new Importer(1)->commit($this->plan("Code,Name\na,A\nb,B\n", $single), $single);
        self::assertSame([[2], [3]], $single->batchLines());
    }

    public function testTheDefaultBatchHoldsFiveHundredRows(): void
    {
        $lines = ['Code,Name'];

        for ($i = 1; $i <= 501; ++$i) {
            $lines[] = 'u'.$i.',U';
        }

        $target = new MemoryTarget('organisational-unit');
        new Importer()->commit($this->plan(implode("\n", $lines), $target), $target);

        self::assertSame([500, 1], array_map(static fn (Batch $batch): int => \count($batch->rows), $target->batches));
    }

    public function testItCountsCreatesAndUpdates(): void
    {
        $target = new MemoryTarget('organisational-unit', ['organisational-unit' => ['["sci"]', '["geo"]']]);
        $result = new Importer()->commit($this->plan(self::UNITS, $target), $target);

        self::assertSame(3, $result->created);
        self::assertSame(2, $result->updated);
        self::assertSame([3], array_map(static fn (PlannedRow $row): int => $row->line, $target->batches[0]->updates()));
        self::assertSame([], $target->batches[0]->creates());
    }

    public function testABatchSplitsItsCreatesFromItsUpdates(): void
    {
        $target = new MemoryTarget('organisational-unit', ['organisational-unit' => ['["b"]']]);
        new Importer()->commit($this->plan("Code,Name\na,A\nb,B\nc,C\n", $target), $target);
        $batch = $target->batches[0];

        self::assertSame([2, 4], array_map(static fn (PlannedRow $row): int => $row->line, $batch->creates()));
        self::assertSame([3], array_map(static fn (PlannedRow $row): int => $row->line, $batch->updates()));
    }

    public function testARowTheTargetRefusesIsReportedAndWhatRefersToItIsNeverWritten(): void
    {
        $target = new MemoryTarget('organisational-unit', refuse: ['["sci"]' => [CellError::refusedByTarget('name', 'the name is taken')]]);
        $plan = $this->plan(self::UNITS."bad,,\n", $target);
        $result = new Importer()->commit($plan, $target);

        self::assertSame([[3], [5, 6]], $target->batchLines());
        self::assertSame([
            2 => ['parent_code reference-rejected 3'],
            3 => ['name refused-by-target '],
            4 => ['parent_code reference-rejected 2'],
            7 => ['name required '],
        ], $this->rejections($result));
        self::assertSame(['sci', 'Sciences', ''], $result->rejections[1]->cells);
        self::assertSame(['math', 'Mathematics', 'sci'], $result->rejections[0]->cells);
        self::assertSame(2, $result->created);
        self::assertSame(2, $result->batches);
    }

    public function testARowRefusedInTheLastBatchIsReported(): void
    {
        $target = new MemoryTarget('organisational-unit', refuse: ['["b"]' => [CellError::refusedByTarget(null, 'no')]]);
        $result = new Importer()->commit($this->plan("Code,Name\na,A\nb,B\n", $target), $target);

        self::assertSame([3 => [' refused-by-target ']], $this->rejections($result));
        self::assertSame(1, $result->created);
    }

    public function testAPlanWithoutRowsWritesNothing(): void
    {
        $target = new MemoryTarget('organisational-unit');
        $result = new Importer()->commit($this->plan("Code,Name\nx,\n", $target), $target);

        self::assertSame([], $target->batches);
        self::assertSame(0, $result->batches);
        self::assertSame([2 => ['name required ']], $this->rejections($result));
    }

    public function testItWritesOnlyThePlanThatWasPreviewed(): void
    {
        $target = new MemoryTarget('organisational-unit');
        $plan = $this->plan(self::UNITS, $target);
        $result = new Importer()->commit($plan, $target, $plan->fingerprint);
        self::assertSame(5, $result->created);

        $again = $this->plan(self::UNITS, $target);
        self::assertNotSame($plan->fingerprint, $again->fingerprint);

        $this->expectException(PlanChanged::class);
        $this->expectExceptionMessageIsOrContains('The plan differs from the one previewed: preview the file again before writing it.');

        new Importer()->commit($again, $target, $plan->fingerprint);
    }

    public function testItWritesThroughATargetOfTheTypeOfThePlanOnly(): void
    {
        $plan = $this->plan(self::UNITS, new MemoryTarget('organisational-unit'));
        $target = new MemoryTarget('person');

        try {
            new Importer()->commit($plan, $target);
            self::fail('A plan was written through a target of another type.');
        } catch (PlanChanged $planChanged) {
            self::assertSame('The plan imports "organisational-unit", the target writes "person".', $planChanged->getMessage());
        }

        self::assertSame([], $target->batches);
    }

    public function testItRefusesABatchOfNoRow(): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('A batch holds at least one row.');

        new Importer(0);
    }

    public function testTheRejectionsOfThePlanAreKept(): void
    {
        $target = new MemoryTarget('organisational-unit');
        $plan = $this->plan("Code,Name\nx,\ny,Y\n", $target);
        $result = new Importer()->commit($plan, $target);

        self::assertSame($plan->rejections, $result->rejections);
        self::assertInstanceOf(RowRejection::class, $result->rejections[0]);
        self::assertSame(Reason::Required, $result->rejections[0]->errors[0]->reason);
    }

    public function testARowReferringToARowAlreadyWrittenDoesNotCloseTheBatch(): void
    {
        $target = new MemoryTarget('organisational-unit');
        $result = new Importer(500)->commit($this->plan("Code,Name,Parent\na,A,\nb,B,\nc,C,a\nd,D,\ne,E,a\n", $target), $target);

        self::assertSame([[2, 3], [4, 5, 6]], $target->batchLines());
        self::assertSame(2, $result->batches);
    }
}
