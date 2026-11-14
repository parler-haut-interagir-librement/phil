<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A form, or one of its fields, defined against the rules.
 */
final class InvalidForm extends InvalidArgumentException implements FormsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
