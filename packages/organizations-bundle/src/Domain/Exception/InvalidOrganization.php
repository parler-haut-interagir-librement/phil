<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the model refuses: an identifier, a name, a role, a domain or a limit out of shape.
 */
final class InvalidOrganization extends InvalidArgumentException implements OrganizationsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
