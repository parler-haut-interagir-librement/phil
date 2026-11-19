<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Exception;

use RuntimeException;

/**
 * A request that does not exist, or that the person asking may not see: both say the same.
 */
final class TicketNotFound extends RuntimeException implements HelpdeskException
{
    public static function of(string $id): self
    {
        return new self(\sprintf('No request "%s" for you.', $id));
    }
}
