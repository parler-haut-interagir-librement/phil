<?php

declare(strict_types=1);

namespace Phil\NumberWords\Exception;

use InvalidArgumentException;

/**
 * ICU has no spelling rules for the language of a locale, the locale is not one, or the language has no rules for
 * amounts in words.
 */
final class UnsupportedLocale extends InvalidArgumentException implements NumberWordsException
{
    public static function noRules(string $locale): self
    {
        return new self(\sprintf('ICU has no rules to spell numbers in "%s".', $locale));
    }

    public static function otherLanguage(string $locale, string $language): self
    {
        return new self(\sprintf('The rules passed are for "%s", not for "%s".', $language, $locale));
    }

    public static function noAmounts(string $locale): self
    {
        return new self(\sprintf('Amounts in words have no rules for "%s": pass a LanguageRules that has them.', $locale));
    }
}
