<?php

declare(strict_types=1);

namespace Phil\ApiContract\Exception;

use InvalidArgumentException;

/**
 * An OpenAPI document that cannot be verified at all: not JSON, not an object, too large or too deep.
 *
 * A document that reads but breaks rules is not refused: its findings are in the report.
 */
final class UnreadableDocument extends InvalidArgumentException implements ApiContractException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
