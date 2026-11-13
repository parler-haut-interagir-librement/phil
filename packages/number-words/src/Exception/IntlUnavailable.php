<?php

declare(strict_types=1);

namespace Phil\NumberWords\Exception;

use LogicException;

/**
 * The `intl` extension of PHP is not loaded: the library spells numbers with ICU's rules and has no other source.
 */
final class IntlUnavailable extends LogicException implements NumberWordsException
{
    public static function create(): self
    {
        return new self('Spelling numbers needs the intl extension of PHP, which is not loaded.');
    }
}
