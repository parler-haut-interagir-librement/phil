<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use DateTimeImmutable;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdempotencyRecord::class)]
#[CoversClass(IdempotencyState::class)]
final class IdempotencyRecordTest extends TestCase
{
    public function testEachStateHasItsConstructor(): void
    {
        $fingerprint = PayloadFingerprint::ofParts('x');
        $until = new DateTimeImmutable('2026-11-11 12:00:00 UTC');
        $response = StoredResponse::of(201);

        $inProgress = IdempotencyRecord::inProgress($fingerprint, $until);
        $completed = IdempotencyRecord::completed($fingerprint, $response, $until);
        $failed = IdempotencyRecord::failed($fingerprint, $until);

        self::assertSame([IdempotencyState::InProgress, null], [$inProgress->state, $inProgress->response]);
        self::assertSame([IdempotencyState::Completed, $response], [$completed->state, $completed->response]);
        self::assertSame([IdempotencyState::Failed, null], [$failed->state, $failed->response]);
        self::assertSame($until, $failed->expiresAt);
        self::assertSame($fingerprint, $failed->fingerprint);
    }

    public function testTheStatesAreWrittenAsTheStoresKeepThem(): void
    {
        self::assertSame(['in_progress', 'completed', 'failed'], array_map(static fn (IdempotencyState $state): string => $state->value, IdempotencyState::cases()));
    }

    public function testARecordReadBackKeepsWhatItWasGiven(): void
    {
        $response = StoredResponse::of(200);
        $record = IdempotencyRecord::of(PayloadFingerprint::ofParts('x'), IdempotencyState::Completed, new DateTimeImmutable('@0'), $response);

        self::assertSame($response, $record->response);
        self::assertNull(IdempotencyRecord::of(PayloadFingerprint::ofParts('x'), IdempotencyState::Failed, new DateTimeImmutable('@0'))->response);
    }

    public function testACompletedRecordWithoutResponseIsRefused(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        IdempotencyRecord::of(PayloadFingerprint::ofParts('x'), IdempotencyState::Completed, new DateTimeImmutable('@0'));
    }

    public function testARecordInProgressWithAResponseIsRefused(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        IdempotencyRecord::of(PayloadFingerprint::ofParts('x'), IdempotencyState::InProgress, new DateTimeImmutable('@0'), StoredResponse::of(200));
    }

    #[TestWith(['@1000', 1])]
    #[TestWith(['@999', 1])]
    #[TestWith(['@1001', 1])]
    #[TestWith(['@0', 1000])]
    #[TestWith(['@940', 60])]
    #[TestWith(['@939', 61])]
    public function testTheSecondsLeftAreAtLeastOne(string $now, int $seconds): void
    {
        $record = IdempotencyRecord::inProgress(PayloadFingerprint::ofParts('x'), new DateTimeImmutable('@1000'));

        self::assertSame($seconds, $record->secondsLeft(new DateTimeImmutable($now)));
    }
}
