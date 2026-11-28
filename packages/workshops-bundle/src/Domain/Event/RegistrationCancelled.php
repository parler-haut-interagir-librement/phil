<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Event;

/**
 * A registration given up, its seats freed: the session's waiting list, when there is one, offers them. It carries
 * identifiers only: a listener reads the rest.
 */
final readonly class RegistrationCancelled
{
    public function __construct(
        public string $registrationId,
        public string $sessionId,
    ) {
    }
}
