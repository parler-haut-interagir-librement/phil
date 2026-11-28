<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Exception;

use RuntimeException;

/**
 * No template under that key, for that site and locale, in the store nor among the default files; no published version
 * of that number; or no draft where one is needed.
 */
final class UnknownTemplate extends RuntimeException implements EmailTemplatesException
{
    public static function named(string $key, string $locale): self
    {
        return new self(\sprintf('No email template "%s" exists for the locale "%s", nor a default for it.', $key, $locale));
    }

    public static function version(string $key, string $locale, int $version): self
    {
        return new self(\sprintf('The email template "%s" has no published version %d for the locale "%s".', $key, $version, $locale));
    }

    public static function draft(string $key, string $locale): self
    {
        return new self(\sprintf('The email template "%s" has no draft for the locale "%s": save one first.', $key, $locale));
    }
}
