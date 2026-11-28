<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Contract;

use Phil\ParticipantSafetyInfo\AccessRecord;

/**
 * The journal of the readings by someone other than the participant. The vault writes to it before it opens the
 * information: a journal that throws stops the reading. It is kept apart from the safety information and outlives
 * its purge, for the time the application's retention calendar gives it.
 */
interface AccessJournal
{
    public function record(AccessRecord $record): void;

    /**
     * The latest readings of a participant's information for an event, most recent first.
     *
     * @return list<AccessRecord> at most `$limit`
     */
    public function recordsOf(string $event, string $participant, int $limit): array;
}
