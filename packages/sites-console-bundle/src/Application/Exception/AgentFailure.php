<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Exception;

use Phil\SitesConsoleBundle\Domain\Exception\SitesConsoleException;
use RuntimeException;
use Throwable;

/**
 * A request to an agent that did not get a verified answer: the site could not be reached, it answered with an
 * error, its response was not signed by its key, or its document was malformed.
 */
final class AgentFailure extends RuntimeException implements SitesConsoleException
{
    public static function unreachable(string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The agent could not be reached: %s', $reason), 0, $previous);
    }

    public static function unsigned(int $status): self
    {
        return new self(\sprintf('The agent answered %d without a valid signature.', $status));
    }

    public static function status(int $status, string $error): self
    {
        return new self('' === $error ? \sprintf('The agent answered %d.', $status) : \sprintf('The agent answered %d: %s', $status, $error));
    }

    public static function malformed(string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The agent answered a malformed document: %s', $reason), 0, $previous);
    }
}
