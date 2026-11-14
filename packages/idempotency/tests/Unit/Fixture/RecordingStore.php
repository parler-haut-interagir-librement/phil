<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\ReservationLost;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyReservation;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;

/**
 * Keys in memory, for the tests of the guard; each call is written down, `reserve()` can be made to lose, and a
 * reservation can be taken over by changing its token in `$tokens`.
 */
final class RecordingStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    public array $records = [];

    /** @var list<string> */
    public array $calls = [];

    /** How many of the next reservations lose to a concurrent run, which leaves `$stolenBy` in their place. */
    public int $losses = 0;

    public ?IdempotencyRecord $stolenBy = null;

    /** @var array<string, string> the token that holds each key */
    public array $tokens = [];

    public ?DateTimeImmutable $reservedUntil = null;

    public ?DateTimeImmutable $keptUntil = null;

    #[Override]
    public function find(IdempotencyScope $scope, IdempotencyKey $key): ?IdempotencyRecord
    {
        $this->calls[] = 'find';

        return $this->records[$scope->value.'|'.$key->value] ?? null;
    }

    #[Override]
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): ?IdempotencyReservation
    {
        $this->calls[] = 'reserve';
        $this->reservedUntil = $expiresAt;

        if ($this->losses > 0) {
            --$this->losses;

            if ($this->stolenBy instanceof IdempotencyRecord) {
                $this->records[$scope->value.'|'.$key->value] = $this->stolenBy;
            }

            return null;
        }

        $reservation = IdempotencyReservation::create($scope, $key);
        $this->records[$scope->value.'|'.$key->value] = IdempotencyRecord::inProgress($fingerprint, $expiresAt);
        $this->tokens[$scope->value.'|'.$key->value] = $reservation->token;

        return $reservation;
    }

    #[Override]
    public function complete(IdempotencyReservation $reservation, StoredResponse $response, DateTimeImmutable $expiresAt): void
    {
        $this->calls[] = 'complete';
        $existing = $this->held($reservation);
        $this->keptUntil = $expiresAt;
        $this->records[$reservation->scope->value.'|'.$reservation->key->value] = IdempotencyRecord::completed($existing->fingerprint, $response, $expiresAt);
    }

    #[Override]
    public function fail(IdempotencyReservation $reservation, DateTimeImmutable $expiresAt): void
    {
        $this->calls[] = 'fail';
        $existing = $this->held($reservation);
        $this->keptUntil = $expiresAt;
        $this->records[$reservation->scope->value.'|'.$reservation->key->value] = IdempotencyRecord::failed($existing->fingerprint, $expiresAt);
    }

    #[Override]
    public function release(IdempotencyScope $scope, IdempotencyKey $key): void
    {
        $this->calls[] = 'release';
        unset($this->records[$scope->value.'|'.$key->value], $this->tokens[$scope->value.'|'.$key->value]);
    }

    private function held(IdempotencyReservation $reservation): IdempotencyRecord
    {
        $name = $reservation->scope->value.'|'.$reservation->key->value;

        if (($this->tokens[$name] ?? null) !== $reservation->token) {
            throw ReservationLost::toAnotherRun();
        }

        return $this->records[$name];
    }
}
