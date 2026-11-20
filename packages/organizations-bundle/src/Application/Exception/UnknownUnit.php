<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * No unit of that identifier.
 */
final class UnknownUnit extends RuntimeException implements OrganizationsException
{
    public static function named(string $unit): self
    {
        return new self(\sprintf('No unit "%s".', $unit));
    }
}
