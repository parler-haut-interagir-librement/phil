<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit;

use Phil\BulkActions\BulkRetrier;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\AccessDenied;
use Phil\BulkActions\Exception\NothingToRetry;
use Phil\BulkActions\Exception\RunNotFound;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Run\ItemContext;
use Phil\BulkActions\Run\ItemOutcome;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Run\RunStatus;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkRetrier::class)]
#[CoversClass(Guard::class)]
#[CoversClass(RunNotFound::class)]
#[CoversClass(NothingToRetry::class)]
#[CoversClass(AccessDenied::class)]
final class BulkRetrierTest extends TestCase
{
    public function testOnlyTheFailedItemsRunAgain(): void
    {
        $fixture = new Fixture();
        $fixture->products->seed(4);
        $fixture->edit->exploding = ['p0002'];
        $fixture->edit->conflicting = ['p0003'];
        $fixture->authorizer->runners = ['ana', 'bob'];

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p0001', 'p0002', 'p0003', 'p0004']), ChangeSet::of(['status' => 'live']), Fixture::now());
        $fixture->executor->execute($run->id, Fixture::now());
        $fixture->edit->exploding = [];
        $fixture->edit->forgetContexts();

        $retried = $fixture->retrier->retry('bob', $run->id);

        self::assertSame(['p0002'], $retried->pending(10));
        self::assertSame('bob', $retried->actor);
        self::assertSame(2, $retried->attempt);
        self::assertSame(RunStatus::Pending, $fixture->stored($run->id)->status);
        self::assertSame([$run->id->value, $run->id->value], $fixture->dispatcher->dispatched);
        self::assertSame('retried:bob:edit:4', $fixture->audit->events[2]);

        self::assertFalse($fixture->executor->execute($run->id, Fixture::now()));
        $report = $fixture->stored($run->id)->report();
        self::assertSame(3, $report->count(ItemOutcome::Succeeded));
        self::assertSame(1, $report->count(ItemOutcome::Conflict));
        self::assertSame(['bob'], array_map(static fn (ItemContext $context): string => $context->actor, $fixture->edit->contexts));
        self::assertSame([2], array_map(static fn (ItemContext $context): int => $context->attempt, $fixture->edit->contexts));
    }

    public function testConflictsRunAgainWhenAsked(): void
    {
        $fixture = new Fixture();
        $fixture->products->seed(2);
        $fixture->products->touch('p0002');

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p0001', 'p0002'], ['p0001' => 1, 'p0002' => 1]), ChangeSet::of(['price' => 3]), Fixture::now());
        $fixture->executor->execute($run->id, Fixture::now());

        self::assertSame(['p0002'], $fixture->retrier->retry('ana', $run->id, true)->pending(10));
        self::assertFalse($fixture->executor->execute($run->id, Fixture::now()));
        self::assertSame(3, $fixture->products->rows['p0002']['price']);
    }

    public function testTheRightIsAskedOfThePersonRetrying(): void
    {
        $fixture = new Fixture();
        $fixture->products->seed(1);
        $fixture->edit->exploding = ['p0001'];

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p0001']), ChangeSet::of(['price' => 3]), Fixture::now());
        $fixture->executor->execute($run->id, Fixture::now());

        try {
            $fixture->retrier->retry('bob', $run->id);
            self::fail('A person without the right retried a run.');
        } catch (AccessDenied) {
        }

        self::assertSame(1, $fixture->stored($run->id)->attempt);
        self::assertCount(1, $fixture->dispatcher->dispatched);
    }

    public function testARunStillRunningIsNotRetried(): void
    {
        $fixture = new Fixture();
        $fixture->products->seed(1);

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p0001']), ChangeSet::of(['price' => 3]), Fixture::now());

        $this->expectExceptionObject(NothingToRetry::stillRunning($run->id->value));
        $fixture->retrier->retry('ana', $run->id);
    }

    public function testAnUnknownRunIsNotFound(): void
    {
        $id = RunId::generate();

        $this->expectExceptionObject(RunNotFound::withId($id->value));
        new Fixture()->retrier->retry('ana', $id);
    }

    public function testARetrierWithoutAuditTrailWorks(): void
    {
        $fixture = new Fixture();
        $fixture->products->seed(1);
        $fixture->edit->exploding = ['p0001'];

        $run = $fixture->confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p0001']), ChangeSet::of(['price' => 3]), Fixture::now());
        $fixture->executor->execute($run->id, Fixture::now());

        self::assertSame(2, new BulkRetrier($fixture->targets, $fixture->authorizer, $fixture->runs, $fixture->dispatcher)->retry('ana', $run->id)->attempt);
    }
}
