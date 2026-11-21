<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * One entry of the journal of an incident: a communication to a third party, a notice put off for an investigation and
 * its end, or the consultation of the person in charge of the protection of personal information (sections 3.5 and 3.7
 * of chapter P-39.1).
 *
 * The journal is only ever added to, its entries numbered per incident from 1; an entry not yet added has the number 0.
 * It lives as long as the line of the register it belongs to, and goes with it.
 */
final readonly class JournalEntry
{
    public const int MAX_PARTY = 255;

    /**
     * @param int                    $sequence   the number of the entry in the journal of its incident, 0 before it is added
     * @param DateTimeImmutable      $at         when it was done: the communication, the start of the deferral, its end, the consultation
     * @param string                 $party      who: the recipient of the communication, the body that investigates, the person consulted
     * @param string                 $details    what was done and why: the information communicated and the purpose, the reason of the deferral, the opinion
     * @param DateTimeImmutable|null $until      for a deferral, until when it is expected to last, when known
     * @param DateTimeImmutable      $recordedAt when the entry was written
     */
    public function __construct(
        public string $incidentId,
        public int $sequence,
        public JournalKind $kind,
        public DateTimeImmutable $at,
        public string $party,
        public string $details,
        public ?DateTimeImmutable $until,
        public DateTimeImmutable $recordedAt,
    ) {
        if (1 !== preg_match(Incident::ID_PATTERN, $incidentId)) {
            throw InvalidPrivacyValue::because('An incident identifier is 32 lowercase hexadecimal characters.');
        }

        if ($sequence < 0) {
            throw InvalidPrivacyValue::because('The number of an entry of the journal cannot be negative.');
        }

        if ('' === trim($party) || mb_strlen($party) > self::MAX_PARTY) {
            throw InvalidPrivacyValue::because(\sprintf('An entry of the journal names who received the information, who investigates or who was consulted, in at most %d characters.', self::MAX_PARTY));
        }

        if (mb_strlen($details) > Incident::MAX_TEXT) {
            throw InvalidPrivacyValue::because(\sprintf('The details of an entry of the journal are at most %d characters.', Incident::MAX_TEXT));
        }

        if ($kind->needsDetails() && '' === trim($details)) {
            throw InvalidPrivacyValue::because(match ($kind) {
                JournalKind::ThirdPartyCommunication => 'A communication to a third party says which personal information was communicated, and how it could reduce the risk of injury.', JournalKind::NoticeDeferral => 'A deferral of the notice says which investigation the notice could hamper.', default => 'A consultation says what the person in charge of the protection of personal information was asked, and what they answered.',
            });
        }

        if ($until instanceof DateTimeImmutable && JournalKind::NoticeDeferral !== $kind) {
            throw InvalidPrivacyValue::because('Only a deferral of the notice has an expected end.');
        }

        if ($until instanceof DateTimeImmutable && $until < $at) {
            throw InvalidPrivacyValue::because('A deferral of the notice cannot end before it starts.');
        }

        if ($at > $recordedAt) {
            throw InvalidPrivacyValue::because('An entry of the journal records what was done, never ahead of time.');
        }
    }

    /**
     * The same entry, numbered in the journal of its incident.
     */
    public function numbered(int $sequence): self
    {
        return new self($this->incidentId, $sequence, $this->kind, $this->at, $this->party, $this->details, $this->until, $this->recordedAt);
    }
}
