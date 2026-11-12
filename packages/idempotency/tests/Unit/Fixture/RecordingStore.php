<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;

/**
 * Keys in memory, for the tests of the guard; each call is written down, and `reserve()` can be made to lose.
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

    public ?DateTimeImmutable $reservedUntil = null;

    public ?DateTimeImmutable $keptUntil = null;

    #[Override]
    public function find(IdempotencyScope $scope, IdempotencyKey $key): ?IdempotencyRecord
    {
        $this->calls[] = 'find';

        return $this->records[$scope->value.'|'.$key->value] ?? null;
    }

    #[Override]
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): bool
    {
        $this->calls[] = 'reserve';
        $this->reservedUntil = $expiresAt;

        if ($this->losses > 0) {
            --$this->losses;

            if ($this->stolenBy instanceof IdempotencyRecord) {
                $this->records[$scope->value.'|'.$key->value] = $this->stolenBy;
            }

            return false;
        }

        $this->records[$scope->value.'|'.$key->value] = IdempotencyRecord::inProgress($fingerprint, $expiresAt);

        return true;
    }

    #[Override]
    public function complete(IdempotencyScope $scope, IdempotencyKey $key, StoredResponse $response, DateTimeImmutable $expiresAt): void
    {
        $this->calls[] = 'complete';
        $this->keptUntil = $expiresAt;
        $existing = $this->records[$scope->value.'|'.$key->value];
        $this->records[$scope->value.'|'.$key->value] = IdempotencyRecord::completed($existing->fingerprint, $response, $expiresAt);
    }

    #[Override]
    public function fail(IdempotencyScope $scope, IdempotencyKey $key, DateTimeImmutable $expiresAt): void
    {
        $this->calls[] = 'fail';
        $this->keptUntil = $expiresAt;
        $existing = $this->records[$scope->value.'|'.$key->value];
        $this->records[$scope->value.'|'.$key->value] = IdempotencyRecord::failed($existing->fingerprint, $expiresAt);
    }

    #[Override]
    public function release(IdempotencyScope $scope, IdempotencyKey $key): void
    {
        $this->calls[] = 'release';
        unset($this->records[$scope->value.'|'.$key->value]);
    }
}
