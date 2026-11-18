<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * Joining by the verified domain is off, the organization has no verified domain, or the address is not in it.
 */
final class DomainJoinRefused extends RuntimeException implements OrganizationsException
{
    public static function disabled(): self
    {
        return new self('Joining by a verified email domain is off.');
    }

    public static function notInDomain(): self
    {
        return new self('The address is not in the verified domain of the organization.');
    }
}
