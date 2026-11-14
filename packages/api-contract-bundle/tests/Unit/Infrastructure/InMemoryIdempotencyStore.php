<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

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
 * Keys in memory, for the listener's tests; each call is written down, and each key keeps the token of the
 * reservation that holds it, which a test changes to take the key over.
 */
final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    public array $records = [];

    /** @var list<string> */
    public array $calls = [];

    public bool $reserves = true;

    /** @var array<string, string> the token that holds each key */
    public array $tokens = [];

    public ?DateTimeImmutable $expiresAt = null;

    public ?DateTimeImmutable $completedUntil = null;

    /**
     * @return list<string>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    #[Override]
    public function find(IdempotencyScope $scope, IdempotencyKey $key): ?IdempotencyRecord
    {
        return $this->records[$scope->value.'|'.$key->value] ?? null;
    }

    #[Override]
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): ?IdempotencyReservation
    {
        $this->calls[] = 'reserve '.$scope->value.'|'.$key->value;
        $this->expiresAt = $expiresAt;

        if (!$this->reserves) {
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
        $name = $this->held($reservation, 'complete');
        $this->completedUntil = $expiresAt;
        $this->records[$name] = IdempotencyRecord::completed($this->records[$name]->fingerprint, $response, $expiresAt);
    }

    #[Override]
    public function fail(IdempotencyReservation $reservation, DateTimeImmutable $expiresAt): void
    {
        $name = $this->held($reservation, 'fail');
        $this->completedUntil = $expiresAt;
        $this->records[$name] = IdempotencyRecord::failed($this->records[$name]->fingerprint, $expiresAt);
    }

    #[Override]
    public function release(IdempotencyScope $scope, IdempotencyKey $key): void
    {
        $this->calls[] = 'release '.$scope->value.'|'.$key->value;
        unset($this->records[$scope->value.'|'.$key->value], $this->tokens[$scope->value.'|'.$key->value]);
    }

    private function held(IdempotencyReservation $reservation, string $call): string
    {
        $name = $reservation->scope->value.'|'.$reservation->key->value;
        $this->calls[] = $call.' '.$name;

        if (($this->tokens[$name] ?? null) !== $reservation->token) {
            throw ReservationLost::toAnotherRun();
        }

        return $name;
    }
}
