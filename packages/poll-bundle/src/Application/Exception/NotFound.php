<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Exception;

use Phil\PollBundle\Domain\Exception\PollBundleException;
use RuntimeException;

/**
 * No poll has this identifier, or no ballot of the voter, or of this receipt, is kept in it.
 */
final class NotFound extends RuntimeException implements PollBundleException
{
    public static function poll(string $id): self
    {
        return new self(\sprintf('No poll is identified by "%s".', $id));
    }

    public static function ballot(string $poll): self
    {
        return new self(\sprintf('No such ballot is kept in the poll "%s".', $poll));
    }
}
