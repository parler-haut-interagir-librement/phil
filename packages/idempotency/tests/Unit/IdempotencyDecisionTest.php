<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use DateTimeImmutable;
use Phil\Idempotency\IdempotencyDecision;
use Phil\Idempotency\IdempotencyOutcome;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdempotencyDecision::class)]
final class IdempotencyDecisionTest extends TestCase
{
    public function testANewKeyProceeds(): void
    {
        $decision = IdempotencyDecision::for(null, $this->fingerprint('{"a":1}'));

        self::assertSame(IdempotencyOutcome::Proceed, $decision->outcome);
        self::assertNull($decision->existing);
        self::assertNull($decision->replay());
    }

    public function testTheSamePayloadAfterCompletionReplaysTheOriginalResponse(): void
    {
        $original = StoredResponse::of(202, [], '{"id":"1"}');
        $record = IdempotencyRecord::completed($this->fingerprint('{"a":1}'), $original, $this->later());

        $decision = IdempotencyDecision::for($record, $this->fingerprint('{"a":1}'));

        self::assertSame(IdempotencyOutcome::Replay, $decision->outcome);
        self::assertSame($original, $decision->replay());
        self::assertSame($record, $decision->existing);
    }

    public function testADifferentPayloadReusesTheKey(): void
    {
        $record = IdempotencyRecord::completed($this->fingerprint('{"a":1}'), StoredResponse::of(202), $this->later());

        $decision = IdempotencyDecision::for($record, $this->fingerprint('{"a":2}'));

        self::assertSame(IdempotencyOutcome::Reused, $decision->outcome);
        self::assertNull($decision->replay());
    }

    public function testTheSamePayloadWhileTheFirstRunIsInProgressWaits(): void
    {
        $record = IdempotencyRecord::inProgress($this->fingerprint('{"a":1}'), $this->later());

        $decision = IdempotencyDecision::for($record, $this->fingerprint('{"a":1}'));

        self::assertSame(IdempotencyOutcome::InProgress, $decision->outcome);
        self::assertNull($decision->replay());
    }

    /**
     * The reuse is reported before the state, so a caller learns of its mistake on the first retry.
     */
    public function testADifferentPayloadWhileTheFirstRunIsInProgressReusesTheKey(): void
    {
        $record = IdempotencyRecord::inProgress($this->fingerprint('{"a":1}'), $this->later());

        self::assertSame(IdempotencyOutcome::Reused, IdempotencyDecision::for($record, $this->fingerprint('{"a":2}'))->outcome);
    }

    public function testTheSamePayloadAfterAFailureRunsAgain(): void
    {
        $record = IdempotencyRecord::failed($this->fingerprint('{"a":1}'), $this->later());

        $decision = IdempotencyDecision::for($record, $this->fingerprint('{"a":1}'));

        self::assertSame(IdempotencyOutcome::Proceed, $decision->outcome);
        self::assertSame($record, $decision->existing);
    }

    public function testADifferentPayloadAfterAFailureIsStillRefused(): void
    {
        $record = IdempotencyRecord::failed($this->fingerprint('{"a":1}'), $this->later());

        self::assertSame(IdempotencyOutcome::Reused, IdempotencyDecision::for($record, $this->fingerprint('{"a":2}'))->outcome);
    }

    private function fingerprint(string $body): PayloadFingerprint
    {
        return PayloadFingerprint::ofRequest('POST', '/api/things', $body);
    }

    private function later(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-11 12:05:00 UTC');
    }
}
