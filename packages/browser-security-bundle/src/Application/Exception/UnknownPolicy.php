<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A policy name that the configuration does not declare.
 */
final class UnknownPolicy extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No content security policy is named "%s".', $name));
    }
}
