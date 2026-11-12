<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions\Exception;

use InvalidArgumentException;

/**
 * A locale dimension refused its own construction: a malformed locale, a version below one, an empty or repeating
 * fallback chain, a fallback that is not served.
 *
 * The value must not exist, so the failure is an exception rather than a list of violations: the value objects
 * validate themselves in plain PHP, and no validator is needed to build them. The message names the value refused; it
 * is written for the developer or the administrator who declared it, not for a visitor.
 */
final class InvalidLocaleDimension extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
