<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit;

use Closure;
use LogicException;
use Phil\BulkActions\BulkExecutor;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\Transactions;
use Phil\BulkActions\Exception\ItemRefused;
use Phil\BulkActions\Exception\VersionConflict;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\ItemContext;
use Phil\BulkActions\Run\ItemOutcome;
use Phil\BulkActions\Run\ItemResult;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Run\RunStatus;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;
use Phil\BulkActions\Tests\Support\FailingRunStore;
use Phil\BulkActions\Tests\Support\Fixture;
use Phil\BulkActions\Tests\Support\OtherItem;
use Phil\BulkActions\Tests\Support\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkExecutor::class)]
#[CoversClass(Guard::class)]
#[CoversClass(BulkRun::class)]
#[CoversClass(ItemContext::class)]
#[CoversClass(ItemRefused::class)]
#[CoversClass(VersionConflict::class)]
final class BulkExecutorTest extends TestCase
{
    private Fixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new Fixture();
        $this->fixture->products->seed(25);
    }

    public function testAChunkIsOneTransactionAndTheRunGoesOnUntilDone(): void
    {
        $run = $this->start(Selection::identifiers($this->ids(1, 25)), ['status' => 'live']);

        self::assertTrue($this->fixture->executor->execute($run->id, Fixture::now()));
        self::assertSame(1, $this->fixture->transactions->commits);
        self::assertSame(RunStatus::Running, $this->stored($run)->status);
        self::assertSame(10, $this->stored($run)->report()->count(ItemOutcome::Succeeded));
        self::assertSame('live', $this->fixture->products->rows['p0010']['status']);
        self::assertSame('draft', $this->fixture->products->rows['p0011']['status']);

        self::assertTrue($this->fixture->executor->execute($run->id, Fixture::now()));
        self::assertFalse($this->fixture->executor->execute($run->id, Fixture::now()));
        self::assertSame(3, $this->fixture->transactions->commits);
        self::assertSame(0, $this->fixture->transactions->rollbacks);

        $stored = $this->stored($run);
        self::assertSame(RunStatus::Finished, $stored->status);
        self::assertEquals(Fixture::now(), $stored->endedAt);
        self::assertSame(25, $stored->report()->count(ItemOutcome::Succeeded));
        self::assertSame(['confirmed:ana:edit:25', 'finished:ana:edit:25'], $this->fixture->audit->events);
        self::assertSame(2, $this->fixture->products->rows['p0025']['version']);
    }

    public function testAFinishedRunDoesNothingMore(): void
    {
        $run = $this->start(Selection::identifiers(['p0001']), ['status' => 'live']);
        self::assertFalse($this->fixture->executor->execute($run->id, Fixture::now()));
        $this->fixture->products->finds = [];

        self::assertFalse($this->fixture->executor->execute($run->id, Fixture::now()));
        self::assertFalse($this->fixture->executor->execute(RunId::generate(), Fixture::now()));
        self::assertSame([], $this->fixture->products->finds);
        self::assertSame(1, $this->fixture->transactions->commits);
    }

    public function testEachItemIsCheckedBeforeItChanges(): void
    {
        $this->fixture->authorizer->deniedItems = ['p0002'];
        $this->fixture->products->impostors['p0003'] = new OtherItem();
        $this->fixture->products->touch('p0004');
        $run = $this->start(Selection::identifiers(['p0001', 'p0002', 'p0003', 'p0004', 'gone', 'p0005'], ['p0004' => '1', 'p0005' => '1']), ['price' => 5]);

        $this->fixture->executor->execute($run->id, Fixture::now());

        self::assertEquals([
            new ItemResult('p0001', ItemOutcome::Succeeded),
            new ItemResult('p0002', ItemOutcome::Denied),
            new ItemResult('p0003', ItemOutcome::Missing),
            new ItemResult('p0004', ItemOutcome::Conflict),
            new ItemResult('gone', ItemOutcome::Missing),
            new ItemResult('p0005', ItemOutcome::Succeeded),
        ], $this->stored($run)->report()->results);
        self::assertNull($this->fixture->products->rows['p0002']['price']);
        self::assertNull($this->fixture->products->rows['p0004']['price']);
        self::assertSame(5, $this->fixture->products->rows['p0005']['price']);
        self::assertContains('apply:ana:PRODUCT_EDIT:p0002', $this->fixture->authorizer->asked);
        self::assertSame(['p0001', 'p0005'], array_map(static fn (ItemContext $context): string => $context->item, $this->fixture->edit->contexts));
    }

    public function testTheActionReceivesTheRunsContext(): void
    {
        $run = $this->start(Selection::identifiers(['p0001']), ['price' => 5]);
        $this->fixture->executor->execute($run->id, Fixture::now());

        self::assertEquals([new ItemContext($run->id, 'p0001', 'ana', 1)], $this->fixture->edit->contexts);
    }

    public function testAFailedChunkIsRolledBackThenAppliedItemByItem(): void
    {
        $this->fixture->edit->refused = ['p0002'];
        $this->fixture->edit->conflicting = ['p0003'];
        $this->fixture->edit->exploding = ['p0004'];

        $run = $this->start(Selection::identifiers($this->ids(1, 5)), ['status' => 'live']);

        self::assertFalse($this->fixture->executor->execute($run->id, Fixture::now()));

        self::assertEquals([
            new ItemResult('p0001', ItemOutcome::Succeeded),
            new ItemResult('p0002', ItemOutcome::Refused, 'products.refused.locked'),
            new ItemResult('p0003', ItemOutcome::Conflict),
            new ItemResult('p0004', ItemOutcome::Failed, BulkExecutor::REASON_ERROR),
            new ItemResult('p0005', ItemOutcome::Succeeded),
        ], $this->stored($run)->report()->results);
        self::assertSame(1 + 3, $this->fixture->transactions->rollbacks);
        self::assertSame(2, $this->fixture->transactions->commits);
        self::assertSame(['live', 'draft', 'draft', 'draft', 'live'], array_map(fn (string $id): string => $this->fixture->products->rows[$id]['status'], $this->ids(1, 5)));
        self::assertSame(2, $this->fixture->products->rows['p0001']['version']);
        self::assertSame(1, $this->fixture->products->rows['p0002']['version']);
    }

    public function testAnItemGoneOrDeniedBeforeItsOwnTransactionIsRecordedSo(): void
    {
        $this->fixture->edit->exploding = ['p0003'];
        $authorizer = $this->fixture->authorizer;
        $products = $this->fixture->products;
        $this->fixture->edit->refused = [];
        $run = $this->start(Selection::identifiers(['p0001', 'p0002', 'p0003']), ['status' => 'live']);

        // The chunk fails on p0003; before the items are applied one by one, p0001 is deleted and p0002 refused.
        $executor = new BulkExecutor(
            $this->fixture->targets,
            $authorizer,
            $this->fixture->runs,
            new class($this->fixture->transactions, static function () use ($products, $authorizer): void {
                unset($products->rows['p0001']);
                $authorizer->deniedItems = ['p0002'];
            }) implements Transactions {
                private bool $first = true;

                public function __construct(private readonly Transactions $inner, private readonly Closure $afterFirst)
                {
                }

                public function run(Closure $work): void
                {
                    try {
                        $this->inner->run($work);
                    } finally {
                        if ($this->first) {
                            $this->first = false;
                            ($this->afterFirst)();
                        }
                    }
                }
            },
            $this->fixture->limits,
        );

        $executor->execute($run->id, Fixture::now());

        self::assertEquals([
            new ItemResult('p0001', ItemOutcome::Missing),
            new ItemResult('p0002', ItemOutcome::Denied),
            new ItemResult('p0003', ItemOutcome::Failed, BulkExecutor::REASON_ERROR),
        ], $this->stored($run)->report()->results);
    }

    public function testTheOutcomesAreSavedWithTheChangesOfTheChunk(): void
    {
        $runs = new FailingRunStore();
        $fixture = new Fixture(new Limits(chunkSize: 2), $runs);
        $fixture->products->seed(3);

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p0001', 'p0002', 'p0003']), ChangeSet::of(['status' => 'live']), Fixture::now());

        // The save inside the chunk's transaction fails: the chunk is rolled back, then each item is applied again.
        $runs->failures = 1;
        self::assertTrue($fixture->executor->execute($run->id, Fixture::now()));

        self::assertSame(1, $fixture->transactions->rollbacks);
        self::assertSame(['p0003'], $fixture->stored($run->id)->pending(10));
        self::assertSame(ItemOutcome::Succeeded, $fixture->stored($run->id)->report()->results[0]->outcome);
        self::assertSame(2, $fixture->products->rows['p0001']['version']);
    }

    public function testTheOutcomeOfAnItemIsSavedWithItsOwnChange(): void
    {
        $runs = new FailingRunStore();
        $fixture = new Fixture(new Limits(chunkSize: 2), $runs);
        $fixture->products->seed(2);

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p0001', 'p0002']), ChangeSet::of(['status' => 'live']), Fixture::now());

        // The save of the chunk fails, then the save of the first item alone: that item is rolled back and failed.
        $runs->failures = 2;
        self::assertFalse($fixture->executor->execute($run->id, Fixture::now()));

        self::assertSame(2, $fixture->transactions->rollbacks);
        self::assertEquals([
            new ItemResult('p0001', ItemOutcome::Failed, BulkExecutor::REASON_ERROR),
            new ItemResult('p0002', ItemOutcome::Succeeded),
        ], $fixture->stored($run->id)->report()->results);
        self::assertSame(1, $fixture->products->rows['p0001']['version']);
        self::assertSame(2, $fixture->products->rows['p0002']['version']);
    }

    public function testAPersonWhoLostTheRightStopsTheRun(): void
    {
        $run = $this->start(Selection::identifiers($this->ids(1, 15)), ['status' => 'live']);
        $this->fixture->executor->execute($run->id, Fixture::now());
        $this->fixture->authorizer->runners = [];

        self::assertFalse($this->fixture->executor->execute($run->id, Fixture::now()));

        $stored = $this->stored($run);
        self::assertSame(RunStatus::Stopped, $stored->status);
        self::assertSame(BulkExecutor::REASON_ACCESS_LOST, $stored->stopReason);
        self::assertSame(10, $stored->report()->count(ItemOutcome::Succeeded));
        self::assertEquals(new ItemResult('p0011', ItemOutcome::Denied, BulkExecutor::REASON_ACCESS_LOST), $stored->report()->results[10]);
        self::assertSame('stopped:ana:edit:15', $this->fixture->audit->events[1]);
        self::assertSame('draft', $this->fixture->products->rows['p0011']['status']);
    }

    public function testAnActionLeftOutOfTheConfigurationStopsTheRun(): void
    {
        $run = $this->start(Selection::identifiers(['p0001']), ['status' => 'live']);
        $executor = new BulkExecutor(
            new Targets([new Target('products', Product::class, $this->fixture->products, [$this->fixture->archive])]),
            $this->fixture->authorizer,
            $this->fixture->runs,
            $this->fixture->transactions,
        );

        self::assertFalse($executor->execute($run->id, Fixture::now()));
        self::assertEquals([new ItemResult('p0001', ItemOutcome::Failed, BulkExecutor::REASON_UNKNOWN_ACTION)], $this->stored($run)->report()->results);
        self::assertSame(RunStatus::Stopped, $this->stored($run)->status);
    }

    public function testAFilterIsReadAgainChunkByChunkWhenTheRunExecutes(): void
    {
        $run = $this->start(Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['p0002']), ['price' => 7]);
        // Changed after the confirmation: the filter is read when the run executes.
        $this->fixture->products->setStatus('p0003', 'live');

        self::assertTrue($this->fixture->executor->execute($run->id, Fixture::now()));
        self::assertSame(['p0001', 'p0004', 'p0005', 'p0006', 'p0007', 'p0008', 'p0009', 'p0010', 'p0011', 'p0012'], array_map(static fn (ItemResult $result): string => $result->item, $this->stored($run)->report()->results));
        self::assertSame('p0012', $this->stored($run)->cursor);

        self::assertTrue($this->fixture->executor->execute($run->id, Fixture::now()));
        self::assertFalse($this->fixture->executor->execute($run->id, Fixture::now()));

        $stored = $this->stored($run);
        self::assertSame(23, $stored->size());
        self::assertSame(23, $stored->report()->count(ItemOutcome::Succeeded));
        self::assertFalse($stored->truncated);
        self::assertNull($this->fixture->products->rows['p0002']['price']);
        self::assertNull($this->fixture->products->rows['p0003']['price']);
    }

    public function testAFilterOfExactlyOneChunkEndsOnAnEmptyRead(): void
    {
        $fixture = new Fixture(new Limits(chunkSize: 5));
        $fixture->products->seed(5);

        $run = $fixture->confirmer->start('ana', 'products', 'archive', Selection::filter(FilterCriteria::of('status', ['status' => 'draft'])), ChangeSet::none(), Fixture::now());

        self::assertTrue($fixture->executor->execute($run->id, Fixture::now()));
        self::assertFalse($fixture->executor->execute($run->id, Fixture::now()));
        self::assertSame(5, $fixture->stored($run->id)->report()->count(ItemOutcome::Succeeded));
        self::assertSame(1, $fixture->transactions->commits);
        self::assertCount(1, $fixture->products->finds, 'An empty chunk reads nothing.');
    }

    public function testAFilterGrowingPastTheLimitIsTruncated(): void
    {
        $fixture = new Fixture(new Limits(maxItems: 12, chunkSize: 5));
        $fixture->products->seed(12);

        $run = $fixture->confirmer->start('ana', 'products', 'archive', Selection::filter(FilterCriteria::of('status', ['status' => 'draft'])), ChangeSet::none(), Fixture::now());
        $fixture->products->add(new Product('p9999', 'Late'));

        while ($fixture->executor->execute($run->id, Fixture::now())) {
        }

        $stored = $fixture->stored($run->id);
        self::assertNotNull($stored);
        self::assertSame(12, $stored->size());
        self::assertTrue($stored->truncated);
        self::assertSame('draft', $fixture->products->rows['p9999']['status']);
    }

    public function testARepositoryIgnoringTheCursorStillEnds(): void
    {
        $fixture = new Fixture(new Limits(chunkSize: 5));
        $fixture->products->seed(8);
        $fixture->products->ignoreCursor = true;

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::filter(FilterCriteria::of('status', ['status' => 'draft'])), ChangeSet::of(['price' => 1]), Fixture::now());

        $calls = 0;

        while ($fixture->executor->execute($run->id, Fixture::now())) {
            self::assertLessThan(5, ++$calls);
        }

        self::assertSame(5, $fixture->stored($run->id)->report()->count(ItemOutcome::Succeeded));
    }

    public function testAFilterTheRepositoryNoLongerAcceptsStopsTheRun(): void
    {
        $run = $this->start(Selection::filter(FilterCriteria::of('status', ['status' => 'draft'])), ['price' => 1]);
        $this->fixture->products->acceptsFilters = false;

        self::assertFalse($this->fixture->executor->execute($run->id, Fixture::now()));
        self::assertSame(RunStatus::Stopped, $this->stored($run)->status);
        self::assertSame(BulkExecutor::REASON_FILTER_REFUSED, $this->stored($run)->stopReason);
        self::assertSame(0, $this->stored($run)->size());
    }

    public function testAnExecutorWithoutAuditTrailWorks(): void
    {
        $run = $this->start(Selection::identifiers(['p0001']), ['price' => 1]);
        $executor = new BulkExecutor($this->fixture->targets, $this->fixture->authorizer, $this->fixture->runs, $this->fixture->transactions);

        self::assertFalse($executor->execute($run->id, Fixture::now()));
        self::assertSame(RunStatus::Finished, $this->stored($run)->status);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function start(Selection $selection, array $changes): BulkRun
    {
        return $this->fixture->confirmer->start('ana', 'products', 'edit', $selection, ChangeSet::of($changes), Fixture::now());
    }

    private function stored(BulkRun $run): BulkRun
    {
        return $this->fixture->runs->get($run->id) ?? throw new LogicException('The run is not stored.');
    }

    /**
     * @return list<string>
     */
    private function ids(int $from, int $to): array
    {
        return array_map(static fn (int $i): string => \sprintf('p%04d', $i), range($from, $to));
    }
}
