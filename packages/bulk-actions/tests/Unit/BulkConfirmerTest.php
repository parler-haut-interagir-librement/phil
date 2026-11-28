<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit;

use DateTimeImmutable;
use Phil\BulkActions\BulkConfirmer;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\SelectionStore;
use Phil\BulkActions\Exception\AccessDenied;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\SelectionUnavailable;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunStatus;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;
use Phil\BulkActions\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkConfirmer::class)]
#[CoversClass(Guard::class)]
#[CoversClass(SelectionUnavailable::class)]
#[CoversClass(AccessDenied::class)]
#[CoversClass(InvalidInput::class)]
final class BulkConfirmerTest extends TestCase
{
    private Fixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new Fixture();
        $this->fixture->products->seed(5);
    }

    public function testAConfirmationUsesTheTokenFilesTheRunAndAsksForItsFirstChunk(): void
    {
        $token = $this->select(Selection::identifiers(['p0001', 'p0002']));
        $run = $this->fixture->confirmer->confirm('ana', $token, ChangeSet::of(['status' => 'live']), Fixture::now());

        self::assertSame('products', $run->target);
        self::assertSame('edit', $run->action);
        self::assertSame('ana', $run->actor);
        self::assertSame(['status' => 'live'], $run->changes->values);
        self::assertSame(['p0001', 'p0002'], $run->pending(10));
        self::assertSame(RunStatus::Pending, $run->status);
        self::assertEquals(Fixture::now(), $run->confirmedAt);
        self::assertEquals($run->toArray(), $this->fixture->stored($run->id)->toArray());
        self::assertSame([$run->id->value], $this->fixture->dispatcher->dispatched);
        self::assertSame(['confirmed:ana:edit:2'], $this->fixture->audit->events);
        self::assertNull($this->fixture->selections->peek($token->fingerprint()));
        self::assertSame([], $this->fixture->products->finds, 'Nothing changes before the worker runs.');
    }

    public function testATokenIsUsedOnce(): void
    {
        $token = $this->select(Selection::identifiers(['p0001']));
        $this->fixture->confirmer->confirm('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now());

        $this->expectExceptionObject(SelectionUnavailable::create());
        $this->fixture->confirmer->confirm('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now());
    }

    public function testAnotherPersonDoesNotUseUpTheOwnersToken(): void
    {
        $this->fixture->authorizer->runners = ['ana', 'bob'];
        $token = $this->select(Selection::identifiers(['p0001']));

        try {
            $this->fixture->confirmer->confirm('bob', $token, ChangeSet::of(['price' => 1]), Fixture::now());
            self::fail('Another person confirmed the selection.');
        } catch (SelectionUnavailable) {
        }

        self::assertNotNull($this->fixture->selections->peek($token->fingerprint()));
        self::assertSame('ana', $this->fixture->confirmer->confirm('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now())->actor);
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $token = $this->select(Selection::identifiers(['p0001']));

        $this->expectExceptionObject(SelectionUnavailable::create());
        $this->fixture->confirmer->confirm('ana', $token, ChangeSet::of(['price' => 1]), new DateTimeImmutable('2026-11-21 10:15:00+00:00'));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function races(): iterable
    {
        yield 'taken by another confirmation' => [false];
        yield "replaced by another person's selection" => [true];
    }

    #[DataProvider('races')]
    public function testATokenTakenMeanwhileIsRefused(bool $replaced): void
    {
        $token = $this->select(Selection::identifiers(['p0001']));
        $racing = new readonly class($this->fixture, $token, $replaced) implements SelectionStore {
            public function __construct(private Fixture $fixture, private SelectionToken $token, private bool $replaced)
            {
            }

            public function save(PendingSelection $selection): void
            {
            }

            public function peek(string $fingerprint): ?PendingSelection
            {
                return $this->fixture->selections->peek($this->token->fingerprint());
            }

            public function take(string $fingerprint): ?PendingSelection
            {
                $pending = $this->peek($fingerprint);

                return $this->replaced && $pending instanceof PendingSelection
                    ? new PendingSelection($pending->fingerprint, 'bob', $pending->target, $pending->action, $pending->selection, $pending->expiresAt)
                    : null;
            }
        };
        $confirmer = new BulkConfirmer($this->fixture->targets, $this->fixture->authorizer, $racing, $this->fixture->runs, $this->fixture->dispatcher, $this->fixture->limits);

        $this->expectExceptionObject(SelectionUnavailable::create());
        $confirmer->confirm('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now());
    }

    public function testTheRightIsAskedAgainAndNothingIsFiled(): void
    {
        $token = $this->select(Selection::identifiers(['p0001']));
        $this->fixture->authorizer->runners = [];

        try {
            $this->fixture->confirmer->confirm('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now());
            self::fail('A run was confirmed without the right.');
        } catch (AccessDenied) {
        }

        self::assertSame([], $this->fixture->dispatcher->dispatched);
        self::assertSame([], $this->fixture->audit->events);
    }

    public function testTheChangeIsCheckedAgainstTheAction(): void
    {
        $token = $this->select(Selection::identifiers(['p0001']));

        $this->expectExceptionObject(InvalidInput::because('field "name"', 'must be one the action "edit" sets'));
        $this->fixture->confirmer->confirm('ana', $token, ChangeSet::of(['name' => 'x']), Fixture::now());
    }

    public function testAFilterIsCheckedAgainWhenStarted(): void
    {
        $this->expectExceptionObject(InvalidInput::because('filter "price"', 'must be one the target "products" supports'));

        $this->fixture->confirmer->start('ana', 'products', 'edit', Selection::filter(FilterCriteria::of('price')), ChangeSet::of(['price' => 1]), Fixture::now());
    }

    public function testACallerWithItsOwnConfirmationStartsARunDirectly(): void
    {
        $run = $this->fixture->confirmer->start('ana', 'products', 'archive', Selection::filter(FilterCriteria::of('status', ['status' => 'draft'])), ChangeSet::none(), Fixture::now());

        self::assertInstanceOf(BulkRun::class, $this->fixture->runs->get($run->id));
        self::assertSame(0, $run->size());
        self::assertFalse($run->exhausted);
        self::assertSame([$run->id->value], $this->fixture->dispatcher->dispatched);
    }

    public function testAConfirmerWithoutAuditTrailWorks(): void
    {
        $confirmer = new BulkConfirmer($this->fixture->targets, $this->fixture->authorizer, $this->fixture->selections, $this->fixture->runs, $this->fixture->dispatcher);

        self::assertSame(1, $confirmer->start('ana', 'products', 'archive', Selection::identifiers(['p0001']), ChangeSet::none(), Fixture::now())->size());
    }

    private function select(Selection $selection): SelectionToken
    {
        return $this->fixture->selector->select('ana', 'products', 'edit', $selection, Fixture::now());
    }
}
