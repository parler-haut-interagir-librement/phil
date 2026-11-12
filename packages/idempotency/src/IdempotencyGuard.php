<?php

declare(strict_types=1);

namespace Phil\Idempotency;

use Closure;
use DateTimeImmutable;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\RequestInProgress;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Runs the sequence of the store: decide, reserve, then complete or fail.
 *
 * `run()` wraps an operation that happens in one call — a message handler, a webhook, a form submission.
 * `begin()`, `complete()` and `fail()` split it for a caller that sees the start and the end apart, such as a
 * listener on the request and the response.
 *
 * A key is reserved for `$reservationTtl` seconds, or the retention if shorter, then kept for its retention once
 * answered or failed. The guard keeps no state between calls.
 */
final readonly class IdempotencyGuard
{
    /** How many times `begin()` reads again after losing a reservation to a concurrent run. */
    private const int ATTEMPTS = 3;

    public function __construct(
        private IdempotencyStore $store,
        private ClockInterface $clock,
        private int $retention = 86_400,
        private int $reservationTtl = 300,
    ) {
        if ($retention < 1 || $reservationTtl < 1) {
            throw InvalidIdempotencyValue::because('The retention and the reservation of an idempotency key are at least 1 second.');
        }
    }

    /**
     * Decides, and on `Proceed` holds the reservation: the caller runs, then calls `complete()` or `fail()`.
     *
     * @param ?int $retention the seconds an answered key is kept, instead of the guard's
     */
    public function begin(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, ?int $retention = null): IdempotencyDecision
    {
        $expiresAt = $this->in(min($this->retention($retention), $this->reservationTtl));

        for ($attempt = 1;; ++$attempt) {
            $decision = IdempotencyDecision::for($this->store->find($scope, $key), $fingerprint);

            if (IdempotencyOutcome::Proceed !== $decision->outcome || $this->store->reserve($scope, $key, $fingerprint, $expiresAt)) {
                return $decision;
            }

            if (self::ATTEMPTS === $attempt) {
                // The key keeps changing hands: a reservation that cannot be seen is still a run in progress.
                return IdempotencyDecision::for(IdempotencyRecord::inProgress($fingerprint, $expiresAt), $fingerprint);
            }
        }
    }

    public function complete(IdempotencyScope $scope, IdempotencyKey $key, StoredResponse $response, ?int $retention = null): void
    {
        $this->store->complete($scope, $key, $response, $this->in($this->retention($retention)));
    }

    public function fail(IdempotencyScope $scope, IdempotencyKey $key, ?int $retention = null): void
    {
        $this->store->fail($scope, $key, $this->in($this->retention($retention)));
    }

    /**
     * Runs `$operation` once per key: a replay returns the stored response without running it; a throwable marks
     * the key failed and is thrown again.
     *
     * @param Closure(): StoredResponse $operation
     *
     * @throws IdempotencyKeyReused when the key was used with another payload
     * @throws RequestInProgress    when the first run is still in progress
     */
    public function run(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, Closure $operation, ?int $retention = null): IdempotentRun
    {
        $decision = $this->begin($scope, $key, $fingerprint, $retention);

        if (IdempotencyOutcome::Reused === $decision->outcome) {
            throw IdempotencyKeyReused::withAnotherRequest();
        }

        if (IdempotencyOutcome::InProgress === $decision->outcome) {
            throw $this->inProgress($decision);
        }

        if (IdempotencyOutcome::Replay === $decision->outcome) {
            return new IdempotentRun($decision->replay() ?? StoredResponse::nothing(), true);
        }

        try {
            $response = $operation();
        } catch (Throwable $throwable) {
            $this->fail($scope, $key, $retention);

            throw $throwable;
        }

        $this->complete($scope, $key, $response, $retention);

        return new IdempotentRun($response, false);
    }

    /**
     * The exception of a run in progress, with the seconds left on its reservation.
     */
    public function inProgress(IdempotencyDecision $decision): RequestInProgress
    {
        return RequestInProgress::retryAfter($decision->existing?->secondsLeft($this->clock->now()) ?? $this->reservationTtl);
    }

    private function retention(?int $retention): int
    {
        if (null !== $retention && $retention < 1) {
            throw InvalidIdempotencyValue::because(\sprintf('The retention of an idempotency key is at least 1 second; %d is not.', $retention));
        }

        return $retention ?? $this->retention;
    }

    private function in(int $seconds): DateTimeImmutable
    {
        return $this->clock->now()->modify(\sprintf('+%d seconds', $seconds));
    }
}
