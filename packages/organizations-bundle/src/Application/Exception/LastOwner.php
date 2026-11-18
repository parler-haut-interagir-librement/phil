<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The change would leave the organization without an owner: the last owner can neither leave, be removed nor lose the
 * owner role. Another owner is named first.
 */
final class LastOwner extends RuntimeException implements OrganizationsException
{
    public static function of(string $organizationId): self
    {
        return new self(\sprintf('The organization "%s" keeps at least one owner: name another owner first.', $organizationId));
    }
}
