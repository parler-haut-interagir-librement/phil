<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use InvalidArgumentException;
use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;

/**
 * A role the configuration does not declare.
 */
final class UnknownRole extends InvalidArgumentException implements OrganizationsException
{
    public static function named(string $role): self
    {
        return new self(\sprintf('The role "%s" is not declared.', $role));
    }
}
