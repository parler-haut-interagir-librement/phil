<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A queue whose name is malformed, or that the configuration does not declare.
 */
final class InvalidQueue extends InvalidArgumentException implements HelpdeskException
{
    public static function malformed(): self
    {
        return new self('The name of a queue holds 1 to 64 lowercase letters, digits, dashes and underscores.');
    }

    public static function unknown(string $queue): self
    {
        return new self(\sprintf('No queue "%s" is declared.', $queue));
    }
}
