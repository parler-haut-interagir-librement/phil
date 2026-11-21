<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Contract\IncidentJournal;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Exception\JournalRefused;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\JournalEntry;
use Phil\PrivacyBundle\Domain\Incident\JournalKind;
use Psr\Clock\ClockInterface;

/**
 * Writes the journal of an incident of the register: a communication to a third party, a notice put off for an
 * investigation and its end, or the consultation of the person in charge of the protection of personal information
 * (sections 3.5 and 3.7 of chapter P-39.1).
 *
 * An entry belongs to an incident of the register, never comes before the start of its discovery nor after now, and a
 * deferral is lifted only while one is in force. The judgement behind each entry belongs to the person in charge.
 */
final readonly class IncidentJournalRecorder
{
    public function __construct(
        private IncidentRegister $register,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string                 $party   the recipient of the communication, the body that investigates, or the person consulted
     * @param string                 $details the information communicated and the purpose, the reason of the deferral, or the opinion
     * @param DateTimeImmutable|null $until   for a deferral, until when it is expected to last, when known
     *
     * @throws JournalRefused when the register keeps no journal, has no such incident, or the entry does not fit it
     */
    public function record(string $incidentId, JournalKind $kind, DateTimeImmutable $at, string $party, string $details = '', ?DateTimeImmutable $until = null): JournalEntry
    {
        $journal = $this->journal();
        $incident = $this->register->find($incidentId);

        if (!$incident instanceof Incident) {
            throw JournalRefused::unknownIncident($incidentId);
        }

        if ($at < $incident->discoveredAt) {
            throw JournalRefused::beforeDiscovery();
        }

        if (JournalKind::DeferralLifted === $kind && !$this->isDeferred($journal->journal($incidentId))) {
            throw JournalRefused::nothingDeferred();
        }

        return $journal->append(new JournalEntry($incidentId, 0, $kind, $at, $party, $details, $until, $this->clock->now()));
    }

    /**
     * @return list<JournalEntry> the oldest entry first
     *
     * @throws JournalRefused when the register keeps no journal
     */
    public function entries(string $incidentId): array
    {
        return $this->journal()->journal($incidentId);
    }

    /**
     * Whether the notice of the incident is put off now: its last deferral has not been lifted.
     *
     * @param list<JournalEntry> $entries
     */
    public function isDeferred(array $entries): bool
    {
        $deferred = false;

        foreach ($entries as $entry) {
            if (JournalKind::NoticeDeferral === $entry->kind) {
                $deferred = true;
            } elseif (JournalKind::DeferralLifted === $entry->kind) {
                $deferred = false;
            }
        }

        return $deferred;
    }

    private function journal(): IncidentJournal
    {
        if (!$this->register instanceof IncidentJournal) {
            throw JournalRefused::withoutJournal();
        }

        return $this->register;
    }
}
