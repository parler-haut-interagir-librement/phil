<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Exception;

use Phil\SitesConsoleBundle\Domain\Exception\SitesConsoleException;
use RuntimeException;

/**
 * A store of the agent that did not keep what it was given: a nonce, which would let the request be replayed, or the
 * console keys of a rotation, which would leave a retired key trusted. The request fails rather than go on.
 */
final class StateNotKept extends RuntimeException implements SitesConsoleException
{
    public static function nonce(): self
    {
        return new self('The nonce of the request could not be kept.');
    }

    public static function consoleKeys(): self
    {
        return new self('The console keys could not be kept.');
    }
}
