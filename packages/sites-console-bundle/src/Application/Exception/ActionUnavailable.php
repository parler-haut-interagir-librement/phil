<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Exception;

use Phil\SitesConsoleBundle\Domain\Exception\SitesConsoleException;
use RuntimeException;

/**
 * An action the agent was not given the means to perform: no configuration applier, no update runner, no console key ring.
 */
final class ActionUnavailable extends RuntimeException implements SitesConsoleException
{
    public static function configuration(): self
    {
        return new self('This site applies no pushed configuration.');
    }

    public static function update(): self
    {
        return new self('This site runs no update.');
    }

    public static function keys(): self
    {
        return new self('This site takes no console key.');
    }
}
