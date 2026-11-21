<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Incident\JournalEntry;

/**
 * The journal of each incident of the register: communications to a third party, notices put off for an investigation
 * and the consultations of the person in charge (sections 3.5 and 3.7 of chapter P-39.1). A register that keeps it
 * implements this beside `IncidentRegister`, and removes the journal of a line with the line.
 */
interface IncidentJournal
{
    /**
     * Adds the entry after the last one of its incident, and returns it with its number.
     */
    public function append(JournalEntry $entry): JournalEntry;

    /**
     * @return list<JournalEntry> the oldest entry first
     */
    public function journal(string $incidentId): array;
}
