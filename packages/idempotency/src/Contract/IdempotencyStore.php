<?php

declare(strict_types=1);

namespace Phil\Idempotency\Contract;

use DateTimeImmutable;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;

/**
 * Where keys are remembered.
 *
 * The expected sequence: `find()`, then `IdempotencyDecision::for()`; on `Proceed`, `reserve()`; after the run,
 * `complete()`, or `fail()` if it failed in a way the caller may retry. When `reserve()` returns `false`, another
 * run took the key between the two calls: the caller reads it again and decides again. `IdempotencyGuard` runs
 * this sequence.
 *
 * **An expired record is absent, for every method.** `find()` never returns one, and `reserve()` takes its
 * place: a record left behind past its expiry, not purged yet, must not keep the key blocked.
 *
 * **Two expiries.** The expiry given to `reserve()` bounds the wait after a crash between `reserve()` and
 * `complete()`, and is meant to be short: longer than the slowest run, far shorter than the key's retention.
 * `complete()` and `fail()` then give the key its retention.
 */
interface IdempotencyStore
{
    /**
     * An expired record is never returned: past its expiry, the key is new again.
     */
    public function find(IdempotencyScope $scope, IdempotencyKey $key): ?IdempotencyRecord;

    /**
     * Records the key as in progress, **atomically**: returns `false` if an unexpired record for this scope and
     * key is in progress or completed, which is the concurrent duplicate `find()` alone cannot see. An expired
     * record, or a failed one, is replaced.
     */
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): bool;

    /**
     * Attaches the response to the reservation, and keeps the key until `$expiresAt`, its retention, whatever
     * expiry the reservation had.
     */
    public function complete(IdempotencyScope $scope, IdempotencyKey $key, StoredResponse $response, DateTimeImmutable $expiresAt): void;

    /**
     * Marks the reservation failed until `$expiresAt`: a retry with the same payload runs again, another payload
     * is still refused.
     */
    public function fail(IdempotencyScope $scope, IdempotencyKey $key, DateTimeImmutable $expiresAt): void;

    /**
     * Forgets the key, whatever its state.
     */
    public function release(IdempotencyScope $scope, IdempotencyKey $key): void;
}
