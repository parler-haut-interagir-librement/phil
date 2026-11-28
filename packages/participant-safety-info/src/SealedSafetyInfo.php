<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

use DateTimeImmutable;

/**
 * What the store keeps of a participant's safety information for an event: the sealed text, opaque without the KMS
 * and bound to its event and participant, when it was sealed and until when it is kept.
 */
final readonly class SealedSafetyInfo
{
    public function __construct(
        public string $event,
        public string $participant,
        public string $sealed,
        public DateTimeImmutable $sealedAt,
        public DateTimeImmutable $retainUntil,
    ) {
        Identifier::check($event, 'An event');
        Identifier::check($participant, 'A participant');
    }
}
