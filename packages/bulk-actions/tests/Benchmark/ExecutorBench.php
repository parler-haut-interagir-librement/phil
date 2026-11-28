<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Benchmark;

use LogicException;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Tests\Support\Fixture;

/**
 * The hot paths of a worker: a run of 5,000 items by filter in chunks of 100, and the store's round trip of a run of
 * 10,000 items.
 */
final readonly class ExecutorBench
{
    private BulkRun $large;

    public function __construct()
    {
        $this->large = BulkRun::confirm(
            RunId::generate(),
            'products',
            'edit',
            'ana',
            ChangeSet::of(['price' => 1]),
            Selection::identifiers(array_map(static fn (int $i): string => 'p'.$i, range(1, 10_000))),
            Fixture::now(),
        );
    }

    public function benchFilterRunOfFiveThousandItems(): void
    {
        $fixture = new Fixture(new Limits(maxItems: 10_000, chunkSize: 100));
        $fixture->products->seed(5_000);

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::filter(FilterCriteria::of('status', ['status' => 'draft'])), ChangeSet::of(['price' => 1]), Fixture::now());

        while ($fixture->executor->execute($run->id, Fixture::now())) {
        }
    }

    public function benchStoredRunOfTenThousandItems(): void
    {
        if ([] === BulkRun::fromArray($this->large->toArray())->pending(100)) {
            throw new LogicException('The run lost its items.');
        }
    }
}
