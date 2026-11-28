<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Event;

use Phil\ParticipantSafetyInfo\AccessReason;

/**
 * Someone other than the participant read their safety information. It names the event, the participant, the reader
 * and the reason only; the justification of a broken glass stays in the journal. A listener tells the responsible
 * people when the reason is `AccessReason::BreakGlass`.
 */
final readonly class SafetyInfoRead
{
    public function __construct(
        public string $event,
        public string $participant,
        public string $reader,
        public AccessReason $reason,
    ) {
    }
}
