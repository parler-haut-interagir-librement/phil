<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\PrivacyBundle\Domain\Erasure\AttentionReason;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Erasure\StepState;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ErasureWorkbench;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DbalErasureRequests::class)]
#[CoversClass(PrivacyTables::class)]
final class DbalErasureRequestsTest extends TestCase
{
    private const string ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private ErasureWorkbench $bench;

    private DbalErasureRequests $store;

    private DateTimeImmutable $at;

    #[Override]
    protected function setUp(): void
    {
        $this->bench = new ErasureWorkbench();
        $this->store = $this->bench->store;
        $this->at = new DateTimeImmutable('2026-11-20 10:00:00 UTC');
        $this->store->open(
            new ErasureRequest(self::ID, DataSubject::fromString('member-7'), null, '0123456789abcdef', RequestState::Running, $this->at),
            [ErasureStep::pending(self::ID, 'forum', $this->at), ErasureStep::pending(self::ID, 'billing', $this->at)],
            static function (): void {
            },
        );
    }

    public function testARequestAndItsStepsAreReadBackAsTheyWereWritten(): void
    {
        self::assertEquals(new ErasureRequest(self::ID, DataSubject::fromString('member-7'), null, '0123456789abcdef', RequestState::Running, $this->at), $this->store->find(self::ID));
        self::assertNull($this->store->find(str_repeat('0', 32)));
        self::assertEquals([ErasureStep::pending(self::ID, 'billing', $this->at), ErasureStep::pending(self::ID, 'forum', $this->at)], $this->store->steps(self::ID));
        self::assertSame([], $this->store->steps(str_repeat('0', 32)));
        self::assertNull($this->store->step(self::ID, 'crm'));
        self::assertSame(self::ID.':forum', $this->bench->connection->fetchOne("SELECT idempotency_key FROM phil_privacy_erasure_step WHERE module = 'forum'"));
    }

    public function testAGuestRequestKeepsItsOriginAndItsHash(): void
    {
        [$request] = ErasureRequest::forGuest(str_repeat('b', 32), '0123456789abcdef', $this->at);
        $this->store->open($request, [], static function (): void {
        });

        self::assertEquals($request, $this->store->find($request->id));
    }

    public function testAFailureInTheTransactionKeepsNothing(): void
    {
        try {
            $this->store->open(
                ErasureRequest::forSubject(DataSubject::fromString('member-8'), '0123456789abcdef', $this->at, str_repeat('c', 32)),
                [ErasureStep::pending(str_repeat('c', 32), 'forum', $this->at)],
                static function (): never {
                    throw new RuntimeException('The outbox is down.');
                },
            );
            self::fail('The failure was swallowed.');
        } catch (RuntimeException) {
        }

        self::assertNull($this->store->find(str_repeat('c', 32)));
        self::assertSame([], $this->store->steps(str_repeat('c', 32)));
    }

    public function testALeaseHoldsUntilItRunsOutAndMarksCheckItsToken(): void
    {
        $until = $this->at->modify('+60 seconds');

        self::assertTrue($this->store->lease(self::ID, 'forum', 'one', $this->at, $until));
        self::assertFalse($this->store->lease(self::ID, 'forum', 'two', $this->at->modify('+59 seconds'), $until));
        self::assertSame(StepState::Running, $this->store->step(self::ID, 'forum')?->state);
        self::assertTrue($this->store->lease(self::ID, 'forum', 'two', $until, $until->modify('+60 seconds')));
        self::assertFalse($this->store->complete(self::ID, 'forum', 'one', [], $until));
        self::assertFalse($this->store->release(self::ID, 'forum', 'one', $until));
        self::assertFalse($this->store->await(self::ID, 'forum', 'one', $until));
        self::assertTrue($this->store->complete(self::ID, 'forum', 'two', ['posts' => ErasureEffect::Anonymised, 'votes' => ErasureEffect::Deleted], $until));
        self::assertFalse($this->store->lease(self::ID, 'forum', 'three', $until->modify('+1 day'), $until->modify('+2 days')), 'A completed step is never leased again.');
        self::assertFalse($this->store->flag(self::ID, 'forum', AttentionReason::RetriesExhausted, $until));

        $step = $this->store->step(self::ID, 'forum');
        self::assertSame(StepState::Completed, $step?->state);
        self::assertSame(['posts' => ErasureEffect::Anonymised, 'votes' => ErasureEffect::Deleted], $step->effects);
        self::assertEquals($until, $step->completedAt);
        self::assertEquals($until, $step->updatedAt);
        self::assertNull($this->bench->connection->fetchOne("SELECT lease FROM phil_privacy_erasure_step WHERE module = 'forum'"));
    }

    public function testTheEffectsAreKeptAsAReadableObjectWhateverTheNamesOfTheStores(): void
    {
        $this->store->lease(self::ID, 'billing', 'worker', $this->at, $this->at->modify('+60 seconds'));

        self::assertTrue($this->store->complete(self::ID, 'billing', 'worker', [0 => ErasureEffect::Deleted, 'cards/é' => ErasureEffect::Anonymised], $this->at));

        self::assertSame('{"0":"deleted","cards/é":"anonymised"}', $this->bench->connection->fetchOne("SELECT effects FROM phil_privacy_erasure_step WHERE module = 'billing'"));
        self::assertSame(['0' => ErasureEffect::Deleted, 'cards/é' => ErasureEffect::Anonymised], $this->store->step(self::ID, 'billing')?->effects);

        $this->store->lease(self::ID, 'forum', 'worker', $this->at, $this->at->modify('+60 seconds'));
        self::assertTrue($this->store->complete(self::ID, 'forum', 'worker', [0 => ErasureEffect::Deleted], $this->at));
        self::assertSame('{"0":"deleted"}', $this->bench->connection->fetchOne("SELECT effects FROM phil_privacy_erasure_step WHERE module = 'forum'"), 'A single store named by a number is still an object.');
    }

    public function testAReleasedStepCountsItsAttemptAndCanBeLeasedAgain(): void
    {
        $this->store->lease(self::ID, 'forum', 'one', $this->at, $this->at->modify('+60 seconds'));
        self::assertTrue($this->store->release(self::ID, 'forum', 'one', $this->at->modify('+5 seconds')));
        self::assertFalse($this->store->release(self::ID, 'forum', 'one', $this->at->modify('+5 seconds')));
        $this->store->lease(self::ID, 'forum', 'two', $this->at, $this->at->modify('+60 seconds'));
        $this->store->release(self::ID, 'forum', 'two', $this->at->modify('+9 seconds'));

        $step = $this->store->step(self::ID, 'forum');
        self::assertSame(StepState::Pending, $step?->state);
        self::assertSame(2, $step->attempts);
        self::assertEquals($this->at->modify('+9 seconds'), $step->updatedAt);
        self::assertTrue($this->store->lease(self::ID, 'forum', 'three', $this->at, $this->at->modify('+60 seconds')));
    }

    public function testAStepWaitingForItsAcknowledgementIsNeverLeasedAndCompletesOnItsKey(): void
    {
        $this->store->lease(self::ID, 'forum', 'one', $this->at, $this->at->modify('+60 seconds'));
        self::assertTrue($this->store->await(self::ID, 'forum', 'one', $this->at));

        self::assertFalse($this->store->lease(self::ID, 'forum', 'two', $this->at->modify('+1 day'), $this->at->modify('+2 days')));
        self::assertFalse($this->store->acknowledge(self::ID.':billing', [], $this->at), 'A pending step does not wait for an acknowledgement.');
        self::assertTrue($this->store->acknowledge(self::ID.':forum', ['contacts' => ErasureEffect::Deleted], $this->at->modify('+1 hour')));
        self::assertFalse($this->store->acknowledge(self::ID.':forum', ['contacts' => ErasureEffect::Deleted], $this->at->modify('+2 hours')));

        $step = $this->store->step(self::ID, 'forum');
        self::assertSame(StepState::Completed, $step?->state);
        self::assertEquals($this->at->modify('+1 hour'), $step->completedAt);
    }

    public function testAFlaggedStepKeepsItsReasonUntilItIsLeasedOrReopened(): void
    {
        self::assertTrue($this->store->flag(self::ID, 'forum', AttentionReason::UnknownModule, $this->at));
        self::assertFalse($this->store->flag(self::ID, 'forum', AttentionReason::RetriesExhausted, $this->at));
        self::assertSame(AttentionReason::UnknownModule, $this->store->step(self::ID, 'forum')?->reason);

        self::assertTrue($this->store->lease(self::ID, 'forum', 'one', $this->at, $this->at->modify('+60 seconds')));
        self::assertNull($this->store->step(self::ID, 'forum')?->reason);

        $this->store->lease(self::ID, 'billing', 'two', $this->at, $this->at->modify('+60 seconds'));
        self::assertTrue($this->store->flag(self::ID, 'billing', AttentionReason::SubjectUnavailable, $this->at));
        self::assertNull($this->bench->connection->fetchOne("SELECT lease FROM phil_privacy_erasure_step WHERE module = 'billing'"));
    }

    public function testAReopenPutsBackWhatNeedsAttentionOrWaitsForAnAcknowledgement(): void
    {
        $this->store->flag(self::ID, 'billing', AttentionReason::RetriesExhausted, $this->at);
        $this->store->lease(self::ID, 'forum', 'one', $this->at, $this->at->modify('+60 seconds'));

        self::assertSame(['billing'], $this->store->reopen(self::ID, $this->at->modify('+1 minute')), 'A step under its lease is left to its worker.');
        self::assertSame(StepState::Running, $this->store->step(self::ID, 'forum')?->state);

        $this->store->await(self::ID, 'forum', 'one', $this->at);
        $this->store->flag(self::ID, 'billing', AttentionReason::RetriesExhausted, $this->at);
        self::assertSame(['billing', 'forum'], $this->store->reopen(self::ID, $this->at->modify('+2 minutes')));

        foreach ($this->store->steps(self::ID) as $step) {
            self::assertSame(StepState::Pending, $step->state);
            self::assertNull($step->reason);
            self::assertEquals($this->at->modify('+2 minutes'), $step->updatedAt);
        }

        self::assertSame([], $this->store->reopen(self::ID, $this->at));
    }

    public function testAClosedRequestIsLeftAsItIs(): void
    {
        self::assertTrue($this->store->settle(self::ID, RequestState::NeedsAttention, $this->at));
        self::assertNull($this->store->find(self::ID)?->closedAt, 'A request that is not closed has no closing date.');
        self::assertTrue($this->store->settle(self::ID, RequestState::Completed, $this->at->modify('+1 hour')));
        self::assertFalse($this->store->settle(self::ID, RequestState::Running, null));
        self::assertFalse($this->store->settle(self::ID, RequestState::Failed, $this->at));
        self::assertFalse($this->store->settle(str_repeat('0', 32), RequestState::Running, null));

        $request = $this->store->find(self::ID);
        self::assertSame(RequestState::Completed, $request?->state);
        self::assertEquals($this->at->modify('+1 hour'), $request->closedAt);
    }

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function brokenStepRows(): iterable
    {
        yield 'an unknown state' => ['state', 'lost', 'An erasure step of the table has no known state.'];
        yield 'effects that are no JSON' => ['effects', '{', 'The effects of an erasure step are a JSON object of effects by store.'];
        yield 'an unknown effect' => ['effects', '{"posts":"burnt"}', 'The effects of an erasure step name an unknown effect.'];
        yield 'an effect that is not a string' => ['effects', '{"posts":1}', 'The effects of an erasure step name an unknown effect.'];
        yield 'effects nested too deep' => ['effects', '{"posts":{"a":1}}', 'The effects of an erasure step are a JSON object of effects by store.'];
        yield 'no update date' => ['updated_at', 'yesterday', 'The column "updated_at" holds no date and time in the format of its database.'];
    }

    #[DataProvider('brokenStepRows')]
    public function testAStepRowOutOfShapeIsRefused(string $column, ?string $value, string $message): void
    {
        $this->bench->connection->update(PrivacyTables::ERASURE_STEPS, [$column => $value], ['module' => 'forum']);

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->store->step(self::ID, 'forum');
    }

    public function testAnUnknownReasonReadsAsNone(): void
    {
        $this->bench->connection->update(PrivacyTables::ERASURE_STEPS, ['reason' => 'gone', 'effects' => '[]'], ['module' => 'forum']);

        $step = $this->store->step(self::ID, 'forum');
        self::assertNull($step?->reason);
        self::assertSame([], $step?->effects);
    }

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function brokenRequestRows(): iterable
    {
        yield 'an unknown state' => ['state', 'lost', 'An erasure request of the table has no known state.'];
        yield 'no request date' => ['requested_at', 'yesterday', 'The column "requested_at" holds no date and time in the format of its database.'];
        yield 'a closing date of another format' => ['closed_at', 'yesterday', 'The column "closed_at" holds no date and time in the format of its database.'];
    }

    #[DataProvider('brokenRequestRows')]
    public function testARequestRowOutOfShapeIsRefused(string $column, ?string $value, string $message): void
    {
        $this->bench->connection->update(PrivacyTables::ERASURE_REQUESTS, [$column => $value], ['id' => self::ID]);

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->store->find(self::ID);
    }

    public function testThePurgeRemovesTheClosedRequestsBeforeTheCutoffWithTheirSteps(): void
    {
        foreach (['b' => [RequestState::Completed, '2026-11-20 09:00:00'], 'c' => [RequestState::Failed, '2026-11-20 09:59:59'], 'd' => [RequestState::Completed, '2026-11-20 10:00:00'], 'e' => [RequestState::NeedsAttention, null]] as $letter => [$state, $closedAt]) {
            $id = str_repeat($letter, 32);
            $this->store->open(new ErasureRequest($id, DataSubject::fromString('member-'.$letter), null, '0123456789abcdef', $state, $this->at, null === $closedAt ? null : new DateTimeImmutable($closedAt.' UTC')), [ErasureStep::pending($id, 'forum', $this->at)], static function (): void {
            });
        }

        $this->bench->connection->update(PrivacyTables::ERASURE_REQUESTS, ['closed_at' => '2026-11-20 08:00:00'], ['id' => str_repeat('e', 32)]);

        self::assertSame(PrivacyTables::ERASURE_REQUESTS, $this->store->store());
        self::assertSame(2, $this->store->purge(new PurgeOrder('R-1', PrivacyTables::ERASURE_REQUESTS, $this->at)));

        self::assertNull($this->store->find(str_repeat('b', 32)));
        self::assertSame([], $this->store->steps(str_repeat('b', 32)));
        self::assertNull($this->store->find(str_repeat('c', 32)));
        self::assertNotNull($this->store->find(str_repeat('d', 32)), 'A request closed at the cutoff is kept.');
        self::assertNotNull($this->store->find(str_repeat('e', 32)), 'A request that is not closed is never purged.');
        self::assertCount(1, $this->store->steps(str_repeat('e', 32)));
        self::assertNotNull($this->store->find(self::ID));
        self::assertCount(2, $this->store->steps(self::ID));
    }

    public function testAnIntegerColumnIsReadAsText(): void
    {
        $this->bench->connection->executeStatement('UPDATE phil_privacy_erasure_step SET attempts = 4');

        self::assertSame(4, $this->store->step(self::ID, 'forum')?->attempts);
    }

    public function testAColumnThatHoldsNoTextIsRefused(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['request_id' => 1.5, 'module' => 'forum', 'state' => 'pending', 'attempts' => 0, 'effects' => null, 'reason' => null, 'updated_at' => '2026-11-20 10:00:00', 'completed_at' => null]);
        $connection->method('getDatabasePlatform')->willReturn($this->bench->connection->getDatabasePlatform());

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('A column of the erasure tables holds no text.');

        new DbalErasureRequests($connection)->step(self::ID, 'forum');
    }
}
