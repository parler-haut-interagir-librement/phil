<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Exception;

use DomainException;

/**
 * A license code the catalog does not hold.
 */
final class UnknownLicense extends DomainException implements LicensingException
{
    public static function named(string $code): self
    {
        return new self(\sprintf('The license "%s" is not in the catalog.', $code));
    }
}
