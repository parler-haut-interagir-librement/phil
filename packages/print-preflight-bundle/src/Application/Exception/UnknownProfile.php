<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application\Exception;

use InvalidArgumentException;
use Phil\PrintPreflight\Exception\PrintPreflightException;

/**
 * A printer profile name the configuration does not declare; the message lists the names it does.
 */
final class UnknownProfile extends InvalidArgumentException implements PrintPreflightException
{
    /**
     * @param list<array-key> $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(\sprintf('There is no printer profile "%s"; the profiles are "%s".', $name, implode('", "', $known)));
    }
}
