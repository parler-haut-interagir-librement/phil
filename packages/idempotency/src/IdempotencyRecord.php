<?php

declare(strict_types=1);

namespace Phil\Idempotency;

use DateTimeImmutable;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;

/**
 * What a store holds for one key in one scope: the fingerprint of the first run, its state, when the record
 * expires, and the response once the run has completed.
 */
final readonly class IdempotencyRecord
{
    private function __construct(
        public PayloadFingerprint $fingerprint,
        public IdempotencyState $state,
        public DateTimeImmutable $expiresAt,
        public ?StoredResponse $response,
    ) {
    }

    public static function inProgress(PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): self
    {
        return new self($fingerprint, IdempotencyState::InProgress, $expiresAt, null);
    }

    public static function completed(PayloadFingerprint $fingerprint, StoredResponse $response, DateTimeImmutable $expiresAt): self
    {
        return new self($fingerprint, IdempotencyState::Completed, $expiresAt, $response);
    }

    public static function failed(PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): self
    {
        return new self($fingerprint, IdempotencyState::Failed, $expiresAt, null);
    }

    /**
     * For a store that reads a record back: a completed record has its response, the others none.
     */
    public static function of(PayloadFingerprint $fingerprint, IdempotencyState $state, DateTimeImmutable $expiresAt, ?StoredResponse $response = null): self
    {
        if ((IdempotencyState::Completed === $state) !== $response instanceof StoredResponse) {
            throw InvalidIdempotencyValue::because('A completed idempotency record has a response, and only a completed one.');
        }

        return new self($fingerprint, $state, $expiresAt, $response);
    }

    /**
     * Whole seconds from `$now` to the expiry, at least 1: the `Retry-After` of a run in progress.
     */
    public function secondsLeft(DateTimeImmutable $now): int
    {
        return max(1, $this->expiresAt->getTimestamp() - $now->getTimestamp());
    }
}
