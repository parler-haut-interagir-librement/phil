<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Exception;

use Phil\SitesConsoleBundle\Domain\Exception\SitesConsoleException;
use RuntimeException;

/**
 * A push carrying a key the agent's configuration does not allow.
 */
final class ConfigurationRefused extends RuntimeException implements SitesConsoleException
{
    /**
     * @param list<string> $keys
     */
    public static function keys(array $keys): self
    {
        return new self(\sprintf('This site does not accept the keys %s.', implode(', ', $keys)));
    }
}
