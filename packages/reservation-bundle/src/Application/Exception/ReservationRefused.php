<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Application\Exception;

use Phil\Reservation\Decision;
use Phil\Reservation\Exception\ReservationException;
use Phil\Reservation\Violation;
use RuntimeException;

/**
 * A request the resource refuses, with the decision that gives every reason.
 */
final class ReservationRefused extends RuntimeException implements ReservationException
{
    public function __construct(
        public readonly Decision $decision,
    ) {
        parent::__construct(\sprintf('The reservation is refused: %s.', implode(', ', array_map(static fn (Violation $violation): string => $violation->code->value, $decision->violations))));
    }
}
