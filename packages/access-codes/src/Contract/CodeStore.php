<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Contract;

use DateTimeImmutable;
use Phil\AccessCodes\Batch;
use Phil\AccessCodes\BatchCounts;
use Phil\AccessCodes\CodeState;

/**
 * Where the batches and the hashes of their codes live. A store never sees a code in clear: it receives the keyed
 * hashes, and looks a code up by its hash under each key of the hasher.
 */
interface CodeStore
{
    /**
     * Adds a batch and the hashes of its codes, all or none.
     *
     * @param list<non-empty-string> $hashes one per code, as many as the batch's size
     */
    public function add(Batch $batch, array $hashes): void;

    /**
     * Redeems the code one of these hashes stands for, atomically: the code is marked redeemed only if it is neither
     * redeemed, revoked nor expired at that moment, so that two redemptions at the same instant leave one winner.
     *
     * @param non-empty-list<non-empty-string> $candidates the code's hash under each key
     *
     * @return string|null the batch of the code redeemed, or `null` when none was
     */
    public function redeem(array $candidates, string $redeemer, DateTimeImmutable $now): ?string;

    /**
     * What the store knows of the code one of these hashes stands for, or `null` when it holds none.
     *
     * @param non-empty-list<non-empty-string> $candidates
     */
    public function find(array $candidates): ?CodeState;

    public function batch(string $batchId): ?Batch;

    /**
     * Revokes the batch and every code of it not yet redeemed; returns how many codes were revoked, 0 for a batch
     * revoked before or unknown.
     */
    public function revoke(string $batchId, DateTimeImmutable $now): int;

    public function counts(string $batchId): BatchCounts;
}
