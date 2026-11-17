<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Exception;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;
use RuntimeException;
use Throwable;

/**
 * The slug is the current address of another root of the same kind, in the same locale: found so before the route is
 * written, or refused by the store when another publication took it in the meantime.
 */
final class SlugTaken extends RuntimeException implements DomainError
{
    public static function by(ContentReference $owner, Locale $locale, Slug $slug): self
    {
        return new self(\sprintf('The slug "%s" is already the address of "%s" in the locale "%s".', $slug->value, $owner->toString(), $locale->value));
    }

    /**
     * Another publication took the slug between the check and the write; its owner is not read again, in a
     * transaction the refusal has ended.
     */
    public static function meanwhile(Locale $locale, Slug $slug, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The slug "%s" was just taken by another content in the locale "%s".', $slug->value, $locale->value), 0, $previous);
    }
}
