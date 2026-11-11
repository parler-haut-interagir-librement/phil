<?php

declare(strict_types=1);

namespace Phil\Typography\Exception;

use InvalidArgumentException;

/**
 * A locale that is not written as a language, an optional script and an optional region.
 */
final class InvalidLocale extends InvalidArgumentException implements TypographyException
{
    public static function of(string $locale): self
    {
        return new self(\sprintf('"%s" is not a locale: write a language, then an optional script and region, as "fr", "fr_CA" or "sr_Latn_RS".', $locale));
    }
}
