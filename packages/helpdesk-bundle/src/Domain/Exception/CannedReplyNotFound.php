<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Exception;

use RuntimeException;

/**
 * A canned reply that does not exist, or is not offered in the queue of the request.
 */
final class CannedReplyNotFound extends RuntimeException implements HelpdeskException
{
    public static function of(string $id): self
    {
        return new self(\sprintf('No canned reply "%s" for this request.', $id));
    }
}
