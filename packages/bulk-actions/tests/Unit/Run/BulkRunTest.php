<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Run;

use DateTimeImmutable;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\NothingToRetry;
use Phil\BulkActions\Internal\Check;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\ItemOutcome;
use Phil\BulkActions\Run\ItemResult;
use Phil\BulkActions\Run\Report;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Run\RunStatus;
use Phil\BulkActions\Run\StoredRun;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkRun::class)]
#[CoversClass(StoredRun::class)]
#[CoversClass(Report::class)]
#[CoversClass(ItemResult::class)]
#[CoversClass(ItemOutcome::class)]
#[CoversClass(RunStatus::class)]
#[CoversClass(NothingToRetry::class)]
#[CoversClass(InvalidInput::class)]
#[CoversClass(Check::class)]
final class BulkRunTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testAnExplicitSelectionIsPendingItemByItem(): void
    {
        $run = $this->newRun(Selection::identifiers(['a', 'b', 'c'], ['b' => 2]));

        self::assertSame(self::ID, $run->id->value);
        self::assertSame('products', $run->target);
        self::assertSame('edit', $run->action);
        self::assertSame('ana', $run->actor);
        self::assertSame(['status' => 'live'], $run->changes->values);
        self::assertSame(RunStatus::Pending, $run->status);
        self::assertSame(3, $run->size());
        self::assertSame(['a', 'b'], $run->pending(2));
        self::assertSame(['a', 'b', 'c'], $run->pending(10));
        self::assertSame([], $run->pending(0));
        self::assertTrue($run->exhausted);
        self::assertFalse($run->truncated);
        self::assertNull($run->cursor);
        self::assertSame(1, $run->attempt);
        self::assertNull($run->endedAt);
        self::assertNull($run->stopReason);
        self::assertSame('2', $run->expectedVersion('b'));
        self::assertNull($run->expectedVersion('a'));
    }

    public function testPendingItemsKeepTheirIdentifiersAsStringsWhenTheyAreNumbers(): void
    {
        $run = $this->newRun(Selection::identifiers(['123', '456']));

        self::assertSame(['123', '456'], $run->pending(10));
    }

    public function testAFilterIsReadChunkByChunk(): void
    {
        $run = $this->newRun(Selection::filter(FilterCriteria::of('status')));

        self::assertSame(0, $run->size());
        self::assertFalse($run->exhausted);
        self::assertFalse($run->finishIfDone($this->now()));

        $run->discover(['a', 'b'], false, 10);
        self::assertSame(['a', 'b'], $run->pending(10));
        self::assertSame('b', $run->cursor);
        self::assertFalse($run->exhausted);

        $run->discover(['b', 'c'], true, 10);
        self::assertSame(['a', 'b', 'c'], $run->pending(10));
        self::assertSame('c', $run->cursor);
        self::assertTrue($run->exhausted);

        $run->discover([], true, 10);
        self::assertSame('c', $run->cursor);
    }

    public function testAReadOfKnownItemsOnlyEndsTheFilter(): void
    {
        $run = $this->newRun(Selection::filter(FilterCriteria::of('status')));
        $run->discover(['a', 'b'], false, 10);
        $run->discover(['a', 'b'], false, 10);

        self::assertTrue($run->exhausted);
        self::assertNull($run->nextFilter());
        self::assertSame(2, $run->size());
    }

    public function testTheNextFilterIsTheSelectionsWhileItHasMore(): void
    {
        $filter = FilterCriteria::of('status');
        $run = $this->newRun(Selection::filter($filter));

        self::assertSame($filter, $run->nextFilter());
        self::assertNull($this->newRun(Selection::identifiers(['a']))->nextFilter());

        $run->stop(ItemOutcome::Failed, 'r', $this->now());
        self::assertTrue($run->exhausted);
        self::assertNull($run->nextFilter());
    }

    public function testAFilterStopsAtTheLimitAndSaysSo(): void
    {
        $run = $this->newRun(Selection::filter(FilterCriteria::of('status')));
        $run->discover(['a', 'b'], false, 3);
        $run->discover(['c', 'd', 'e'], false, 3);

        self::assertSame(['a', 'b', 'c'], $run->pending(10));
        self::assertTrue($run->truncated);
        self::assertTrue($run->exhausted);
        self::assertTrue($run->report()->truncated);
    }

    public function testAFilterReachingTheLimitExactlyIsNotTruncated(): void
    {
        $run = $this->newRun(Selection::filter(FilterCriteria::of('status')));
        $run->discover(['a', 'b', 'c'], true, 3);

        self::assertFalse($run->truncated);
        self::assertSame(3, $run->size());
    }

    public function testItFinishesOnceNothingIsPending(): void
    {
        $run = $this->newRun(Selection::identifiers(['a', 'b']));
        $run->record('a', ItemOutcome::Succeeded);

        self::assertSame(RunStatus::Running, $run->status);
        self::assertFalse($run->finishIfDone($this->now()));
        self::assertNull($run->endedAt);

        $run->record('b', ItemOutcome::Denied);
        self::assertTrue($run->finishIfDone($this->now()));
        self::assertSame(RunStatus::Finished, $run->status);
        self::assertEquals($this->now(), $run->endedAt);
        self::assertTrue($run->finishIfDone(new DateTimeImmutable('2030-01-01')));
        self::assertEquals($this->now(), $run->endedAt);
    }

    public function testAStoppedRunGivesItsPendingItemsTheOutcomeGiven(): void
    {
        $run = $this->newRun(Selection::identifiers(['a', 'b', 'c']));
        $run->record('a', ItemOutcome::Succeeded);
        $run->stop(ItemOutcome::Denied, 'bulk_actions.reason.access_lost', $this->now());

        self::assertSame(RunStatus::Stopped, $run->status);
        self::assertSame('bulk_actions.reason.access_lost', $run->stopReason);
        self::assertEquals($this->now(), $run->endedAt);
        self::assertEquals([
            new ItemResult('a', ItemOutcome::Succeeded),
            new ItemResult('b', ItemOutcome::Denied, 'bulk_actions.reason.access_lost'),
            new ItemResult('c', ItemOutcome::Denied, 'bulk_actions.reason.access_lost'),
        ], $run->report()->results);
        self::assertTrue($run->finishIfDone($this->now()));
        self::assertSame(RunStatus::Stopped, $run->status);
    }

    public function testARetryMakesTheFailuresPendingAgainForTheNewActor(): void
    {
        $run = $this->newRun(Selection::identifiers(['a', 'b', 'c', 'd'], ['b' => 1]));
        $run->record('a', ItemOutcome::Succeeded);
        $run->record('b', ItemOutcome::Conflict);
        $run->record('c', ItemOutcome::Failed, 'bulk_actions.reason.error');
        $run->record('d', ItemOutcome::Refused, 'x.locked');
        $run->finishIfDone($this->now());

        self::assertSame(1, $run->retry('bob', false));
        self::assertSame(['c'], $run->pending(10));
        self::assertSame('bob', $run->actor);
        self::assertSame(RunStatus::Pending, $run->status);
        self::assertSame(2, $run->attempt);
        self::assertNull($run->endedAt);
        self::assertNull($run->expectedVersion('b'));
        self::assertEquals(new ItemResult('c', ItemOutcome::Pending), $run->report()->results[2]);
    }

    public function testARetryTakesTheConflictsWhenAsked(): void
    {
        $run = $this->newRun(Selection::identifiers(['a', 'b']));
        $run->record('a', ItemOutcome::Conflict);
        $run->record('b', ItemOutcome::Failed);
        $run->stop(ItemOutcome::Failed, 'r', $this->now());

        self::assertSame(2, $run->retry('ana', true));
        self::assertSame(['a', 'b'], $run->pending(10));
        self::assertNull($run->stopReason);
        self::assertTrue($run->exhausted);
    }

    public function testARunningRunIsNotRetried(): void
    {
        $run = $this->newRun(Selection::identifiers(['a']));

        $this->expectExceptionObject(NothingToRetry::stillRunning(self::ID));
        $run->retry('ana', true);
    }

    public function testARunWithoutFailureIsNotRetried(): void
    {
        $run = $this->newRun(Selection::identifiers(['a', 'b']));
        $run->record('a', ItemOutcome::Succeeded);
        $run->record('b', ItemOutcome::Conflict);
        $run->finishIfDone($this->now());

        try {
            $run->retry('ana', false);
            self::fail('A run without failure was retried.');
        } catch (NothingToRetry $nothingToRetry) {
            self::assertEquals(NothingToRetry::noFailure(self::ID), $nothingToRetry);
        }

        self::assertSame(1, $run->attempt);
        self::assertSame('ana', $run->actor);
        self::assertSame(RunStatus::Finished, $run->status);
    }

    public function testItReadsBackWhatItStored(): void
    {
        $run = $this->newRun(Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['x']));
        $run->discover(['10', '2', 'a'], false, 100);
        $run->record('10', ItemOutcome::Refused, 'x.locked');
        $run->record('2', ItemOutcome::Succeeded);

        $data = $run->toArray();

        self::assertSame([
            'id' => self::ID,
            'target' => 'products',
            'action' => 'edit',
            'actor' => 'ana',
            'changes' => ['status' => 'live'],
            'selection' => $run->selection->toArray(),
            'confirmed_at' => '2026-11-21T10:00:00+00:00',
            'status' => 'running',
            'items' => [['10', 'refused', 'x.locked'], ['2', 'succeeded', null], ['a', 'pending', null]],
            'cursor' => 'a',
            'exhausted' => false,
            'truncated' => false,
            'attempt' => 1,
            'ended_at' => null,
            'stop_reason' => null,
        ], $data);

        $decoded = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $read = BulkRun::fromArray($decoded);
        self::assertEquals($run->toArray(), $read->toArray());
        self::assertSame(['a'], $read->pending(10));
        self::assertSame(['10', '2', 'a'], array_map(static fn (ItemResult $result): string => $result->item, $read->report()->results));

        $run->stop(ItemOutcome::Failed, 'why', $this->now());
        self::assertEquals($run->toArray(), BulkRun::fromArray($run->toArray())->toArray());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function storedRuns(): iterable
    {
        yield 'no id' => ['id', null];
        yield 'an id that is not a string' => ['id', 3];
        yield 'a target that is not a string' => ['target', 1];
        yield 'no action' => ['action', null];
        yield 'no actor' => ['actor', null];
        yield 'changes that are not an array' => ['changes', 'x'];
        yield 'a selection that is not an array' => ['selection', null];
        yield 'no confirmation date' => ['confirmed_at', null];
        yield 'a confirmation date in another format' => ['confirmed_at', '2026-11-21'];
        yield 'an unknown status' => ['status', 'lost'];
        yield 'items that are not an array' => ['items', 'x'];
        yield 'an item that is not an array' => ['items', ['a']];
        yield 'an item without identifier' => ['items', [[null, 'pending', null]]];
        yield 'an item with an integer identifier' => ['items', [[1, 'pending', null]]];
        yield 'an item without outcome' => ['items', [['a', null, null]]];
        yield 'an item with an unknown outcome' => ['items', [['a', 'lost', null]]];
        yield 'an item with a reason that is not a string' => ['items', [['a', 'failed', 3]]];
        yield 'a cursor that is not a string' => ['cursor', 3];
        yield 'exhausted that is not a boolean' => ['exhausted', 1];
        yield 'truncated that is not a boolean' => ['truncated', null];
        yield 'an attempt that is not an integer' => ['attempt', '1'];
        yield 'an attempt of zero' => ['attempt', 0];
        yield 'an end date in another format' => ['ended_at', 'yesterday'];
        yield 'a stop reason that is not a string' => ['stop_reason', false];
    }

    #[DataProvider('storedRuns')]
    public function testItRefusesAStoredRunItCannotHaveWritten(string $key, mixed $value): void
    {
        $data = $this->newRun(Selection::identifiers(['a']))->toArray();
        $data[$key] = $value;

        $this->expectException(InvalidInput::class);

        BulkRun::fromArray($data);
    }

    public function testAStoredItemWithoutReasonIsRead(): void
    {
        $data = $this->newRun(Selection::identifiers(['a']))->toArray();
        $data['items'] = [['a', 'failed']];

        self::assertEquals([new ItemResult('a', ItemOutcome::Failed)], BulkRun::fromArray($data)->report()->results);
    }

    public function testTheStoredMessageNamesTheKeyAtFault(): void
    {
        $data = $this->newRun(Selection::identifiers(['a']))->toArray();
        $data['exhausted'] = 'yes';

        $this->expectExceptionObject(InvalidInput::because('stored run', 'must have a valid exhausted'));
        BulkRun::fromArray($data);
    }

    public function testAStoredRunWithoutStatusOrDateSaysSo(): void
    {
        $data = $this->newRun(Selection::identifiers(['a']))->toArray();
        $data['status'] = 'gone';

        try {
            BulkRun::fromArray($data);
            self::fail('An unknown status was read.');
        } catch (InvalidInput $invalidInput) {
            self::assertSame('The stored run must have a known status.', $invalidInput->getMessage());
        }

        $data = $this->newRun(Selection::identifiers(['a']))->toArray();
        $data['confirmed_at'] = null;
        $this->expectExceptionObject(InvalidInput::because('stored run', 'must have its confirmed_at'));
        BulkRun::fromArray($data);
    }

    private function newRun(Selection $selection): BulkRun
    {
        return BulkRun::confirm(RunId::fromString(self::ID), 'products', 'edit', 'ana', ChangeSet::of(['status' => 'live']), $selection, $this->now());
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-21 10:00:00+00:00');
    }
}
