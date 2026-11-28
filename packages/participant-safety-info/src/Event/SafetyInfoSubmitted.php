<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Event;

/**
 * A participant submitted or replaced their safety information for an event. It names the event and the participant only.
 */
final readonly class SafetyInfoSubmitted
{
    public function __construct(
        public string $event,
        public string $participant,
    ) {
    }
}
