<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use DateTimeImmutable;
use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\Idempotency\IdempotencyDecision;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyOutcome;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\IdempotentRun;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use Phil\Idempotency\Tests\Unit\Fixture\RecordingStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(IdempotencyGuard::class)]
#[CoversClass(IdempotentRun::class)]
#[CoversClass(IdempotencyKeyReused::class)]
#[CoversClass(RequestInProgress::class)]
#[CoversClass(InvalidIdempotencyValue::class)]
final class IdempotencyGuardTest extends TestCase
{
    private RecordingStore $store;

    private MockClock $clock;

    private IdempotencyScope $scope;

    private IdempotencyKey $key;

    protected function setUp(): void
    {
        $this->store = new RecordingStore();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-11 12:00:00 UTC'));
        $this->scope = IdempotencyScope::of('client:1');
        $this->key = IdempotencyKey::fromString('k-1');
    }

    public function testTheFirstRunReservesRunsAndKeepsItsAnswerForTheRetention(): void
    {
        $runs = 0;
        $run = $this->guard()->run($this->scope, $this->key, $this->fingerprint(), static function () use (&$runs): StoredResponse {
            ++$runs;

            return StoredResponse::of(201, [], 'made');
        });

        self::assertSame(1, $runs);
        self::assertFalse($run->replayed);
        self::assertSame('made', $run->response->body);
        self::assertSame(['find', 'reserve', 'complete'], $this->store->calls);
        self::assertSame('2026-11-11 12:05:00', $this->store->reservedUntil?->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-12 12:00:00', $this->store->keptUntil?->format('Y-m-d H:i:s'));
    }

    public function testARetryReplaysWithoutRunning(): void
    {
        $guard = $this->guard();
        $guard->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => StoredResponse::of(201, [], 'made'));

        $run = $guard->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => throw new RuntimeException('Ran twice.'));

        self::assertTrue($run->replayed);
        self::assertSame('made', $run->response->body);
        self::assertSame(201, $run->response->status);
    }

    public function testAnotherPayloadUnderTheSameKeyIsRefused(): void
    {
        $guard = $this->guard();
        $guard->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => StoredResponse::nothing());

        $this->expectException(IdempotencyKeyReused::class);
        $this->expectExceptionMessageIsOrContains('This idempotency key was used with another request.');

        $guard->run($this->scope, $this->key, PayloadFingerprint::ofParts('other'), static fn (): StoredResponse => StoredResponse::nothing());
    }

    public function testARunInProgressIsRetriedAfterTheSecondsLeftOnItsReservation(): void
    {
        $this->store->records['client:1|k-1'] = IdempotencyRecord::inProgress($this->fingerprint(), new DateTimeImmutable('2026-11-11 12:01:30 UTC'));

        try {
            $this->guard()->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => throw new RuntimeException('Ran beside the first.'));
            self::fail('A run in progress was not refused.');
        } catch (RequestInProgress $requestInProgress) {
            self::assertSame(90, $requestInProgress->retryAfter);
            self::assertSame('A request with this idempotency key is in progress.', $requestInProgress->getMessage());
        }
    }

    public function testAFailedRunIsMarkedFailedAndThrownAgainThenRunsAgain(): void
    {
        $guard = $this->guard();

        try {
            $guard->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => throw new RuntimeException('Down.'));
            self::fail('The failure was swallowed.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Down.', $runtimeException->getMessage());
        }

        self::assertSame(IdempotencyState::Failed, $this->store->records['client:1|k-1']->state);
        self::assertSame('2026-11-12 12:00:00', $this->store->keptUntil?->format('Y-m-d H:i:s'));

        $run = $guard->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => StoredResponse::of(200, [], 'second'));

        self::assertFalse($run->replayed);
        self::assertSame('second', $run->response->body);
    }

    public function testTheReservationIsTheShorterOfItsTtlAndTheRetention(): void
    {
        $this->guard()->begin($this->scope, $this->key, $this->fingerprint(), 60);

        self::assertSame('2026-11-11 12:01:00', $this->store->reservedUntil?->format('Y-m-d H:i:s'));
    }

    public function testARetentionOfTheCallReplacesTheGuards(): void
    {
        $this->guard()->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => StoredResponse::nothing(), 3600);

        self::assertSame('2026-11-11 13:00:00', $this->store->keptUntil?->format('Y-m-d H:i:s'));
    }

    public function testALostReservationIsDecidedAgainOnWhatTookIt(): void
    {
        $this->store->losses = 1;
        $this->store->stolenBy = IdempotencyRecord::completed($this->fingerprint(), StoredResponse::of(202, [], 'other run'), new DateTimeImmutable('2026-11-12 12:00:00 UTC'));

        $decision = $this->guard()->begin($this->scope, $this->key, $this->fingerprint());

        self::assertSame(IdempotencyOutcome::Replay, $decision->outcome);
        self::assertSame('other run', $decision->replay()?->body);
        self::assertSame(['find', 'reserve', 'find'], $this->store->calls);
    }

    public function testAKeyThatKeepsChangingHandsIsInProgressAfterThreeAttempts(): void
    {
        $this->store->losses = 5;

        $decision = $this->guard()->begin($this->scope, $this->key, $this->fingerprint());

        self::assertSame(IdempotencyOutcome::InProgress, $decision->outcome);
        self::assertSame(['find', 'reserve', 'find', 'reserve', 'find', 'reserve'], $this->store->calls);
        self::assertSame(300, $this->guard()->inProgress($decision)->retryAfter);
    }

    public function testAKeyReservedOnTheSecondAttemptProceeds(): void
    {
        $this->store->losses = 2;

        self::assertSame(IdempotencyOutcome::Proceed, $this->guard()->begin($this->scope, $this->key, $this->fingerprint())->outcome);
        self::assertCount(6, $this->store->calls);
    }

    public function testADecisionWithoutRecordWaitsTheWholeReservation(): void
    {
        $decision = IdempotencyDecision::for(null, $this->fingerprint());

        self::assertSame(300, $this->guard()->inProgress($decision)->retryAfter);
    }

    #[TestWith([0, 300])]
    #[TestWith([86_400, 0])]
    public function testTheTimesOfTheGuardAreAtLeastOneSecond(int $retention, int $reservation): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        new IdempotencyGuard($this->store, $this->clock, $retention, $reservation);
    }

    public function testTheRetentionOfACallIsAtLeastOneSecond(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);
        $this->expectExceptionMessageIsOrContains('The retention of an idempotency key is at least 1 second; 0 is not.');

        $this->guard()->complete($this->scope, $this->key, StoredResponse::nothing(), 0);
    }

    public function testFailingKeepsTheKeyForTheRetention(): void
    {
        $this->guard()->begin($this->scope, $this->key, $this->fingerprint());
        $this->guard()->fail($this->scope, $this->key, 120);

        self::assertSame('2026-11-11 12:02:00', $this->store->keptUntil?->format('Y-m-d H:i:s'));
    }

    public function testByDefaultAKeyIsReservedFiveMinutesAndKeptOneDay(): void
    {
        new IdempotencyGuard($this->store, $this->clock)->run($this->scope, $this->key, $this->fingerprint(), static fn (): StoredResponse => StoredResponse::nothing());

        self::assertSame(['2026-11-11 12:05:00', '2026-11-12 12:00:00'], [$this->store->reservedUntil?->format('Y-m-d H:i:s'), $this->store->keptUntil?->format('Y-m-d H:i:s')]);
    }

    public function testOneSecondIsEnough(): void
    {
        $guard = new IdempotencyGuard($this->store, $this->clock, 1, 1);
        $guard->begin($this->scope, $this->key, $this->fingerprint());
        $guard->complete($this->scope, $this->key, StoredResponse::nothing(), 1);

        self::assertSame(['2026-11-11 12:00:01', '2026-11-11 12:00:01'], [$this->store->reservedUntil?->format('Y-m-d H:i:s'), $this->store->keptUntil?->format('Y-m-d H:i:s')]);
    }

    #[TestWith([-5, 1])]
    #[TestWith([0, 1])]
    #[TestWith([1, 1])]
    #[TestWith([7, 7])]
    public function testARetryIsAtLeastOneSecondAway(int $seconds, int $retryAfter): void
    {
        self::assertSame($retryAfter, RequestInProgress::retryAfter($seconds)->retryAfter);
    }

    private function guard(): IdempotencyGuard
    {
        return new IdempotencyGuard($this->store, $this->clock, 86_400, 300);
    }

    private function fingerprint(): PayloadFingerprint
    {
        return PayloadFingerprint::ofRequest('POST', '/api/things', '{"a":1}');
    }
}
