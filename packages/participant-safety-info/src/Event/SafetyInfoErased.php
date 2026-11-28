<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Event;

/**
 * A participant's safety information for an event was erased, by the participant. It names the event and the participant only.
 */
final readonly class SafetyInfoErased
{
    public function __construct(
        public string $event,
        public string $participant,
    ) {
    }
}
