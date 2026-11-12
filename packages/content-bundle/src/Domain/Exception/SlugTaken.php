<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Exception;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;
use RuntimeException;

/**
 * The slug is the current address of another root of the same kind, in the same locale.
 */
final class SlugTaken extends RuntimeException implements DomainError
{
    public static function by(ContentReference $owner, Locale $locale, Slug $slug): self
    {
        return new self(\sprintf('The slug "%s" is already the address of "%s" in the locale "%s".', $slug->value, $owner->toString(), $locale->value));
    }
}
