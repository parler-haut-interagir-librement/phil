<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Exception;

use InvalidArgumentException;

/**
 * A value refused its own construction: an identifier, a text, a list or a page out of its bounds. The message names
 * the rule, never the value.
 */
final class InvalidCodeOfConduct extends InvalidArgumentException implements CodeOfConductException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
