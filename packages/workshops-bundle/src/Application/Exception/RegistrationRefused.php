<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Application\Exception;

use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use RuntimeException;

/**
 * A registration the session refuses, with the reason and the parameters of its message.
 */
final class RegistrationRefused extends RuntimeException
{
    /**
     * @param array<string, int> $parameters the message's parameters: `max` for too many seats
     */
    public function __construct(
        public readonly RefusalReason $reason,
        public readonly array $parameters = [],
    ) {
        parent::__construct(\sprintf('The registration is refused: %s.', $reason->value));
    }
}
