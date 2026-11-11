<?php

declare(strict_types=1);

namespace Phil\DddBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value object refused its own construction.
 *
 * Value objects validate themselves in their constructors, in plain PHP, and `symfony/validator` never enters
 * `Domain`: a validator produces a list of violations meant for a form, whereas here failure is binary and the
 * object must not exist.
 */
final class InvalidValue extends InvalidArgumentException implements DomainError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
