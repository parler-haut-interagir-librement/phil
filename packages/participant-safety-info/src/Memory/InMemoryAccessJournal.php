<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Memory;

use Override;
use Phil\ParticipantSafetyInfo\AccessRecord;
use Phil\ParticipantSafetyInfo\Contract\AccessJournal;

/**
 * A journal in memory, for tests and demonstrations.
 */
final class InMemoryAccessJournal implements AccessJournal
{
    /** @var list<AccessRecord> */
    private array $records = [];

    #[Override]
    public function record(AccessRecord $record): void
    {
        $this->records[] = $record;
    }

    #[Override]
    public function recordsOf(string $event, string $participant, int $limit): array
    {
        // `array_slice` numbers the records it keeps from 0 again.
        return \array_slice(array_filter(
            array_reverse($this->records),
            static fn (AccessRecord $record): bool => $record->event === $event && $record->participant === $participant,
        ), 0, $limit);
    }
}
