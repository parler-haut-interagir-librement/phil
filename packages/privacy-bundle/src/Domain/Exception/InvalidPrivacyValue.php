<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value object, a declaration or a schedule refused its own construction.
 *
 * Validation is plain PHP in constructors: the failure is binary and the object must not exist.
 */
final class InvalidPrivacyValue extends InvalidArgumentException implements PrivacyError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
