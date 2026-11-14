<?php

declare(strict_types=1);

namespace Phil\Idempotency;

use Closure;
use DateTimeImmutable;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\Idempotency\Exception\ReservationLost;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Runs the sequence of the store: decide, reserve, then complete or fail.
 *
 * `run()` wraps an operation that happens in one call — a message handler, a webhook, a form submission.
 * `begin()`, `complete()` and `fail()` split it for a caller that sees the start and the end apart, such as a
 * listener on the request and the response: the decision of `begin()` carries the reservation that `complete()` and
 * `fail()` take back.
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
     * Decides, and on `Proceed` holds the reservation, in `$decision->reservation`: the caller runs, then gives it to
     * `complete()` or `fail()`.
     *
     * @param ?int $retention the seconds an answered key is kept, instead of the guard's
     */
    public function begin(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, ?int $retention = null): IdempotencyDecision
    {
        $expiresAt = $this->in(min($this->retention($retention), $this->reservationTtl));

        for ($attempt = 1;; ++$attempt) {
            $decision = IdempotencyDecision::for($this->store->find($scope, $key), $fingerprint);

            if (IdempotencyOutcome::Proceed !== $decision->outcome) {
                return $decision;
            }

            $reservation = $this->store->reserve($scope, $key, $fingerprint, $expiresAt);

            if ($reservation instanceof IdempotencyReservation) {
                return $decision->reserved($reservation);
            }

            if (self::ATTEMPTS === $attempt) {
                // The key keeps changing hands: a reservation that cannot be seen is still a run in progress.
                return IdempotencyDecision::for(IdempotencyRecord::inProgress($fingerprint, $expiresAt), $fingerprint);
            }
        }
    }

    /**
     * @throws ReservationLost when another run holds the key since the reservation expired
     */
    public function complete(IdempotencyReservation $reservation, StoredResponse $response, ?int $retention = null): void
    {
        $this->store->complete($reservation, $response, $this->in($this->retention($retention)));
    }

    /**
     * @throws ReservationLost when another run holds the key since the reservation expired
     */
    public function fail(IdempotencyReservation $reservation, ?int $retention = null): void
    {
        $this->store->fail($reservation, $this->in($this->retention($retention)));
    }

    /**
     * Runs `$operation` once per key: a replay returns the stored response without running it; a throwable marks
     * the key failed and is thrown again.
     *
     * When the run outlived its reservation and another run reserved the key since, the response is not kept and
     * `ReservationLost` is thrown. A throwable of the operation is thrown as it is, whether or not its failure could
     * be written: the key then stays with the other run.
     *
     * @param Closure(): StoredResponse $operation
     *
     * @throws IdempotencyKeyReused when the key was used with another payload
     * @throws RequestInProgress    when the first run is still in progress
     * @throws ReservationLost      when the run ended after another run took its key
     */
    public function run(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, Closure $operation, ?int $retention = null): IdempotentRun
    {
        $decision = $this->begin($scope, $key, $fingerprint, $retention);
        $reservation = $decision->reservation;

        if ($reservation instanceof IdempotencyReservation) {
            return $this->reserved($reservation, $operation, $retention);
        }

        if (IdempotencyOutcome::Reused === $decision->outcome) {
            throw IdempotencyKeyReused::withAnotherRequest();
        }

        if (IdempotencyOutcome::InProgress === $decision->outcome) {
            throw $this->inProgress($decision);
        }

        return new IdempotentRun($decision->replay() ?? StoredResponse::nothing(), true);
    }

    /**
     * The exception of a run in progress, with the seconds left on its reservation.
     */
    public function inProgress(IdempotencyDecision $decision): RequestInProgress
    {
        return RequestInProgress::retryAfter($decision->existing?->secondsLeft($this->clock->now()) ?? $this->reservationTtl);
    }

    /**
     * @param Closure(): StoredResponse $operation
     */
    private function reserved(IdempotencyReservation $reservation, Closure $operation, ?int $retention): IdempotentRun
    {
        try {
            $response = $operation();
        } catch (Throwable $throwable) {
            try {
                $this->fail($reservation, $retention);
            } catch (ReservationLost) {
                // The operation's own failure is what its caller handles; the key stays with the run that holds it.
            }

            throw $throwable;
        }

        $this->complete($reservation, $response, $retention);

        return new IdempotentRun($response, false);
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
