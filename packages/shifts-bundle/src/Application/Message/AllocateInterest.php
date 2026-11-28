<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application\Message;

/**
 * Asks a worker to allocate the free places of a shift among the interested. It carries the identifier of the shift
 * only: the roster is read from the store when the message is handled.
 */
final readonly class AllocateInterest
{
    public function __construct(
        public string $shiftId,
    ) {
    }
}
