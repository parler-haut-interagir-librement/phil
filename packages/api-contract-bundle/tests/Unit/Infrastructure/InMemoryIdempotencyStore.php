<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;

/**
 * Keys in memory, for the listener's tests; each call is written down.
 */
final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    public array $records = [];

    /** @var list<string> */
    public array $calls = [];

    public bool $reserves = true;

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
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): bool
    {
        $this->calls[] = 'reserve '.$scope->value.'|'.$key->value;
        $this->expiresAt = $expiresAt;

        if ($this->reserves) {
            $this->records[$scope->value.'|'.$key->value] = IdempotencyRecord::inProgress($fingerprint, $expiresAt);
        }

        return $this->reserves;
    }

    #[Override]
    public function complete(IdempotencyScope $scope, IdempotencyKey $key, StoredResponse $response, DateTimeImmutable $expiresAt): void
    {
        $this->calls[] = 'complete '.$scope->value.'|'.$key->value;
        $this->completedUntil = $expiresAt;
        $existing = $this->records[$scope->value.'|'.$key->value];
        $this->records[$scope->value.'|'.$key->value] = IdempotencyRecord::completed($existing->fingerprint, $response, $expiresAt);
    }

    #[Override]
    public function fail(IdempotencyScope $scope, IdempotencyKey $key, DateTimeImmutable $expiresAt): void
    {
        $this->calls[] = 'fail '.$scope->value.'|'.$key->value;
        $this->completedUntil = $expiresAt;
        $existing = $this->records[$scope->value.'|'.$key->value];
        $this->records[$scope->value.'|'.$key->value] = IdempotencyRecord::failed($existing->fingerprint, $expiresAt);
    }

    #[Override]
    public function release(IdempotencyScope $scope, IdempotencyKey $key): void
    {
        $this->calls[] = 'release '.$scope->value.'|'.$key->value;
        unset($this->records[$scope->value.'|'.$key->value]);
    }
}
