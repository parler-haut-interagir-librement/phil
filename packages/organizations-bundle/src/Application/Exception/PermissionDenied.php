<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The actor lacks a permission the change requires, or would grant roles wider than their own.
 */
final class PermissionDenied extends RuntimeException implements OrganizationsException
{
    public static function lacking(string $permission): self
    {
        return new self(\sprintf('The permission "%s" is required.', $permission));
    }

    public static function widerRoles(): self
    {
        return new self('A member grants, changes or removes only roles whose permissions they hold themselves.');
    }
}
