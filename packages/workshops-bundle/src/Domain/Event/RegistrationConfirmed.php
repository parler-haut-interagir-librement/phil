<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Event;

/**
 * A registration whose seats are taken at once. It carries identifiers only: a listener reads the rest.
 */
final readonly class RegistrationConfirmed
{
    public function __construct(
        public string $registrationId,
        public string $sessionId,
    ) {
    }
}
