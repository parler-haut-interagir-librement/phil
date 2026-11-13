<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use DateTimeImmutable;
use Override;
use Phil\AccessCodes\Contract\CodeStore;
use Phil\AccessCodes\Exception\InvalidConfiguration;

/**
 * The batches and codes of one process: for tests, and for scripts that create and redeem in one run.
 */
final class InMemoryCodeStore implements CodeStore
{
    /** @var array<string, Batch> */
    private array $batches = [];

    /** @var array<string, CodeState> by hash */
    private array $codes = [];

    #[Override]
    public function add(Batch $batch, array $hashes): void
    {
        if (isset($this->batches[$batch->id])) {
            throw InvalidConfiguration::because(\sprintf('A batch with the identifier "%s" is already stored.', $batch->id));
        }

        if (\count($hashes) !== $batch->size || \count(array_unique($hashes)) !== $batch->size) {
            throw InvalidConfiguration::because('A batch is stored with one distinct hash per code.');
        }

        foreach ($hashes as $hash) {
            if (isset($this->codes[$hash])) {
                throw InvalidConfiguration::because('A code of the batch is already stored.');
            }
        }

        $this->batches[$batch->id] = $batch;

        foreach ($hashes as $hash) {
            $this->codes[$hash] = new CodeState($batch->id, $batch->expiresAt);
        }
    }

    #[Override]
    public function redeem(array $candidates, string $redeemer, DateTimeImmutable $now): ?string
    {
        $hash = $this->hashOf($candidates);

        if (null === $hash) {
            return null;
        }

        $state = $this->codes[$hash];

        if (null !== $state->redeemedAt || null !== $state->revokedAt || (null !== $state->expiresAt && $state->expiresAt <= $now)) {
            return null;
        }

        $this->codes[$hash] = new CodeState($state->batchId, $state->expiresAt, $now, $redeemer);

        return $state->batchId;
    }

    #[Override]
    public function find(array $candidates): ?CodeState
    {
        $hash = $this->hashOf($candidates);

        return null === $hash ? null : $this->codes[$hash];
    }

    #[Override]
    public function batch(string $batchId): ?Batch
    {
        return $this->batches[$batchId] ?? null;
    }

    #[Override]
    public function revoke(string $batchId, DateTimeImmutable $now): int
    {
        $batch = $this->batches[$batchId] ?? null;

        if (null === $batch || $batch->isRevoked()) {
            return 0;
        }

        $this->batches[$batchId] = new Batch($batch->id, $batch->name, $batch->size, $batch->createdAt, $batch->expiresAt, $now);
        $revoked = 0;

        foreach ($this->codes as $hash => $state) {
            if ($state->batchId === $batchId && null === $state->redeemedAt) {
                $this->codes[$hash] = new CodeState($state->batchId, $state->expiresAt, revokedAt: $now);
                ++$revoked;
            }
        }

        return $revoked;
    }

    #[Override]
    public function counts(string $batchId): BatchCounts
    {
        $total = 0;
        $redeemed = 0;
        $revoked = 0;

        foreach ($this->codes as $state) {
            if ($state->batchId !== $batchId) {
                continue;
            }

            ++$total;
            $redeemed += null === $state->redeemedAt ? 0 : 1;
            $revoked += null === $state->revokedAt ? 0 : 1;
        }

        return new BatchCounts($total, $redeemed, $revoked);
    }

    /**
     * The stored hash among the candidates. The lookup is a hash table's, whose timing says nothing of a code: the
     * hashes are keyed.
     *
     * @param non-empty-list<non-empty-string> $candidates
     */
    private function hashOf(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($this->codes[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
