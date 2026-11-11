<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Exception;

use InvalidArgumentException;

/**
 * A template, a design or one of their parts refused at construction, or read back from an array no code of the
 * library could have written.
 */
final class InvalidDefinition extends InvalidArgumentException implements ProductTemplateException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
