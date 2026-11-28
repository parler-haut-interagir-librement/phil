<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Contract;

use DateTimeImmutable;
use Phil\ParticipantSafetyInfo\SealedSafetyInfo;

/**
 * The sealed safety information, one record per event and participant. The store never sees a plaintext.
 */
interface SafetyInfoStore
{
    public function get(string $event, string $participant): ?SealedSafetyInfo;

    /**
     * Saves the record, replacing the one of the same event and participant.
     */
    public function save(SealedSafetyInfo $info): void;

    /**
     * Deletes the record of the event and participant; whether there was one.
     */
    public function delete(string $event, string $participant): bool;

    /**
     * Deletes up to `$limit` records kept until before `$before`.
     *
     * @return int how many were deleted
     */
    public function deleteRetainedUntilBefore(DateTimeImmutable $before, int $limit): int;
}
