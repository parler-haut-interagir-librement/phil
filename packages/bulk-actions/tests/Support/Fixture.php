<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use DateTimeImmutable;
use LogicException;
use Phil\BulkActions\BulkConfirmer;
use Phil\BulkActions\BulkExecutor;
use Phil\BulkActions\BulkPreviewer;
use Phil\BulkActions\BulkRetrier;
use Phil\BulkActions\BulkSelector;
use Phil\BulkActions\Contract\RunStore;
use Phil\BulkActions\Memory\InMemoryRunStore;
use Phil\BulkActions\Memory\InMemorySelectionStore;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;

/**
 * Every service of the library over the products, wired as an application would.
 */
final readonly class Fixture
{
    public MemoryProducts $products;

    public EditProducts $edit;

    public ArchiveProducts $archive;

    public RuleAuthorizer $authorizer;

    public InMemorySelectionStore $selections;

    public RunStore $runs;

    public MemoryTransactions $transactions;

    public RecordingDispatcher $dispatcher;

    public RecordingAudit $audit;

    public Targets $targets;

    public BulkSelector $selector;

    public BulkPreviewer $previewer;

    public BulkConfirmer $confirmer;

    public BulkExecutor $executor;

    public BulkRetrier $retrier;

    public function __construct(public Limits $limits = new Limits(maxIdentifiers: 50, maxItems: 500, chunkSize: 10, sampleSize: 3), ?RunStore $runs = null)
    {
        $this->products = new MemoryProducts();
        $this->edit = new EditProducts();
        $this->archive = new ArchiveProducts();
        $this->authorizer = new RuleAuthorizer();
        $this->selections = new InMemorySelectionStore();
        $this->runs = $runs ?? new InMemoryRunStore();
        $this->transactions = new MemoryTransactions($this->products);
        $this->dispatcher = new RecordingDispatcher();
        $this->audit = new RecordingAudit();
        $this->targets = new Targets([new Target('products', Product::class, $this->products, [$this->edit, $this->archive])]);
        $this->selector = new BulkSelector($this->targets, $this->authorizer, $this->selections, $limits);
        $this->previewer = new BulkPreviewer($this->targets, $this->authorizer, $this->selections, $limits);
        $this->confirmer = new BulkConfirmer($this->targets, $this->authorizer, $this->selections, $this->runs, $this->dispatcher, $limits, $this->audit);
        $this->executor = new BulkExecutor($this->targets, $this->authorizer, $this->runs, $this->transactions, $limits, $this->audit);
        $this->retrier = new BulkRetrier($this->targets, $this->authorizer, $this->runs, $this->dispatcher, $limits, $this->audit);
    }

    public function stored(RunId $id): BulkRun
    {
        return $this->runs->get($id) ?? throw new LogicException('The run is not stored.');
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-21 10:00:00+00:00');
    }
}
