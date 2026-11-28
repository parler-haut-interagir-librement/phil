<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Functional;

use LogicException;
use Phil\BulkActions\BulkConfirmer;
use Phil\BulkActions\BulkExecutor;
use Phil\BulkActions\BulkPreviewer;
use Phil\BulkActions\BulkRetrier;
use Phil\BulkActions\BulkSelector;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\SelectionUnavailable;
use Phil\BulkActions\Memory\InMemoryRunStore;
use Phil\BulkActions\Memory\InMemorySelectionStore;
use Phil\BulkActions\Messenger\ExecuteBulkRun;
use Phil\BulkActions\Messenger\ExecuteBulkRunHandler;
use Phil\BulkActions\Run\ItemOutcome;
use Phil\BulkActions\Run\RunStatus;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;
use Phil\BulkActions\Tests\Support\ArchiveProducts;
use Phil\BulkActions\Tests\Support\EditProducts;
use Phil\BulkActions\Tests\Support\Fixture;
use Phil\BulkActions\Tests\Support\MemoryProducts;
use Phil\BulkActions\Tests\Support\MemoryTransactions;
use Phil\BulkActions\Tests\Support\Product;
use Phil\BulkActions\Tests\Support\QueuedDispatcher;
use Phil\BulkActions\Tests\Support\RecordingAudit;
use Phil\BulkActions\Tests\Support\RuleAuthorizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The whole path of a bulk edit, as an administration and a worker take it: a selection by filter, a preview, a
 * confirmation sent back as a page would send it, the messages consumed until the run is over, then a retry.
 */
#[CoversClass(BulkSelector::class)]
#[CoversClass(BulkPreviewer::class)]
#[CoversClass(BulkConfirmer::class)]
#[CoversClass(BulkExecutor::class)]
#[CoversClass(BulkRetrier::class)]
#[CoversClass(ExecuteBulkRunHandler::class)]
final class BulkEditFlowTest extends TestCase
{
    private MemoryProducts $products;

    private EditProducts $edit;

    private RuleAuthorizer $authorizer;

    private QueuedDispatcher $dispatcher;

    private RecordingAudit $audit;

    private BulkSelector $selector;

    private BulkPreviewer $previewer;

    private BulkConfirmer $confirmer;

    private BulkRetrier $retrier;

    private ExecuteBulkRunHandler $handler;

    private InMemoryRunStore $runs;

    protected function setUp(): void
    {
        $limits = new Limits(maxIdentifiers: 100, maxItems: 5_000, chunkSize: 100, sampleSize: 5);
        $this->products = new MemoryProducts();
        $this->products->seed(1_200);

        $this->edit = new EditProducts();
        $this->authorizer = new RuleAuthorizer();
        $this->dispatcher = new QueuedDispatcher();
        $this->audit = new RecordingAudit();
        $this->runs = new InMemoryRunStore();
        $selections = new InMemorySelectionStore();
        $targets = new Targets([new Target('products', Product::class, $this->products, [$this->edit, new ArchiveProducts()])]);
        $this->selector = new BulkSelector($targets, $this->authorizer, $selections, $limits);
        $this->previewer = new BulkPreviewer($targets, $this->authorizer, $selections, $limits);
        $this->confirmer = new BulkConfirmer($targets, $this->authorizer, $selections, $this->runs, $this->dispatcher, $limits, $this->audit);
        $this->retrier = new BulkRetrier($targets, $this->authorizer, $this->runs, $this->dispatcher, $limits, $this->audit);
        $executor = new BulkExecutor($targets, $this->authorizer, $this->runs, new MemoryTransactions($this->products), $limits, $this->audit);
        $this->handler = new ExecuteBulkRunHandler($executor, $this->dispatcher);
    }

    public function testABulkEditOfAThousandItemsGoesFromTheFilterToTheReport(): void
    {
        $this->authorizer->deniedItems = ['p0004'];
        $this->edit->exploding = ['p0500'];

        // The list page: "every draft product", less one unticked row; the page only keeps the token.
        $token = $this->selector->select('ana', 'products', 'edit', Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['p0003']), Fixture::now());
        $sent = $token->value;

        // The form: the price box ticked and the field emptied, the status left alone.
        $changes = ChangeSet::fromSubmission(['status' => 'live', 'price' => null], ['price' => '1']);
        $preview = $this->previewer->preview('ana', SelectionToken::fromString($sent), $changes, Fixture::now());

        self::assertSame(1_199, $preview->count);
        self::assertCount(5, $preview->sample);
        self::assertSame(1, $preview->deniedInSample());
        self::assertSame(['price'], $changes->fields());

        $run = $this->confirmer->confirm('ana', SelectionToken::fromString($sent), $changes, Fixture::now());
        $this->work();

        $report = ($this->runs->get($run->id) ?? throw new LogicException('Lost.'))->report();
        self::assertNotNull($report);
        self::assertSame(RunStatus::Finished, $report->status);
        self::assertSame(1_199, $report->total());
        self::assertSame(1_197, $report->count(ItemOutcome::Succeeded));
        self::assertSame(1, $report->count(ItemOutcome::Denied));
        self::assertSame(1, $report->count(ItemOutcome::Failed));
        self::assertSame('draft', $this->products->rows['p0001']['status'], 'An unticked field is unchanged.');
        self::assertCount(12, $this->dispatcher->sent);
        self::assertSame(serialize(new ExecuteBulkRun($run->id->value)), $this->dispatcher->sent[0]);
        self::assertSame(['confirmed:ana:edit:0', 'finished:ana:edit:1199'], $this->audit->events);

        // The token was used once.
        try {
            $this->confirmer->confirm('ana', SelectionToken::fromString($sent), $changes, Fixture::now());
            self::fail('A token was used twice.');
        } catch (SelectionUnavailable) {
        }

        // The failure is fixed; only that item runs again.
        $this->edit->exploding = [];
        $this->edit->forgetContexts();

        $this->retrier->retry('ana', $run->id);
        $this->work();

        self::assertSame(1_198, ($this->runs->get($run->id) ?? throw new LogicException('Lost.'))->report()->count(ItemOutcome::Succeeded));
        self::assertCount(1, $this->edit->contexts);
        self::assertSame('p0500', $this->edit->contexts[0]->item);
    }

    private function work(): void
    {
        $handled = 0;

        while (null !== $message = array_shift($this->dispatcher->queue)) {
            ($this->handler)($message);
            self::assertLessThan(100, ++$handled);
        }
    }
}
