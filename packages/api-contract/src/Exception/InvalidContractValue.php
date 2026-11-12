<?php

declare(strict_types=1);

namespace Phil\ApiContract\Exception;

use InvalidArgumentException;

/**
 * A value object refused its own construction.
 *
 * The failure is binary: the object must not exist. Mapping it to an HTTP response (usually `400` with a
 * problem document) is the application's decision.
 */
final class InvalidContractValue extends InvalidArgumentException implements ApiContractException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
