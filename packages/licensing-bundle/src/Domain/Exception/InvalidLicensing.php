<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the licensing refuses: an empty or too long text, an address that is not http or https, a malformed code.
 */
final class InvalidLicensing extends InvalidArgumentException implements LicensingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
