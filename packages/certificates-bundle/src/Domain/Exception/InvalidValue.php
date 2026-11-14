<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the bundle refuses because it does not hold together: a course without a name, a slug that is not one, a
 * score outside 0 to 100, a verification code of the wrong shape.
 */
final class InvalidValue extends InvalidArgumentException implements CertificatesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
