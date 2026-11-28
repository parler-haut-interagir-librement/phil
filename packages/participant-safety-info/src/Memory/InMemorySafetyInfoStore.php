<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Memory;

use DateTimeImmutable;
use Override;
use Phil\ParticipantSafetyInfo\Contract\SafetyInfoStore;
use Phil\ParticipantSafetyInfo\SealedSafetyInfo;

/**
 * A store in memory, for tests and demonstrations: it keeps its records for the life of the object, so a long-running
 * worker never shares one between requests.
 */
final class InMemorySafetyInfoStore implements SafetyInfoStore
{
    /** @var array<string, SealedSafetyInfo> */
    private array $records = [];

    #[Override]
    public function get(string $event, string $participant): ?SealedSafetyInfo
    {
        return $this->records[$this->key($event, $participant)] ?? null;
    }

    #[Override]
    public function save(SealedSafetyInfo $info): void
    {
        $this->records[$this->key($info->event, $info->participant)] = $info;
    }

    #[Override]
    public function delete(string $event, string $participant): bool
    {
        $key = $this->key($event, $participant);
        $existed = isset($this->records[$key]);
        unset($this->records[$key]);

        return $existed;
    }

    #[Override]
    public function deleteRetainedUntilBefore(DateTimeImmutable $before, int $limit): int
    {
        $deleted = 0;

        foreach ($this->records as $key => $record) {
            if ($deleted >= $limit) {
                break;
            }

            if ($record->retainUntil < $before) {
                unset($this->records[$key]);
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function key(string $event, string $participant): string
    {
        return $event."\x1F".$participant;
    }
}
