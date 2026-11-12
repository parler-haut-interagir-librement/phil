<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Exception;

use InvalidArgumentException;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;

/**
 * A dimension refused: a malformed or unserved locale, a version below one, a row merged over a row that is not its
 * counterpart. The refusals of `phil/locale-dimensions` reach the bundle's callers as this exception, the original
 * kept as the previous one.
 *
 * The value must not exist, so the failure is an exception rather than a list of violations: value objects validate
 * themselves in plain PHP, and no validator enters the domain. Like every error of the bundle, it is a `DomainError`,
 * so the application catches it at its boundary without knowing the bundle.
 */
final class InvalidDimension extends InvalidArgumentException implements DomainError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function from(InvalidLocaleDimension $invalid): self
    {
        return new self($invalid->getMessage(), previous: $invalid);
    }
}
