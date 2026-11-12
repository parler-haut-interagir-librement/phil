<?php

declare(strict_types=1);

namespace Phil\Publishing\Exception;

use InvalidArgumentException;

/**
 * An event built with a language that is not a BCP 47 tag, or with an owner identifier that is empty, too long, or
 * holds a control character or surrounding spaces.
 */
final class InvalidPageEvent extends InvalidArgumentException
{
    public static function locale(string $locale): self
    {
        return new self(\sprintf('"%s" is not a BCP 47 language tag, as "fr" or "fr-CA".', mb_substr($locale, 0, 40)));
    }

    public static function ownerId(string $ownerId, int $maxLength): self
    {
        return new self(\sprintf('"%s" is not an owner identifier: 1 to %d bytes, without a control character or surrounding spaces.', mb_substr($ownerId, 0, 40), $maxLength));
    }
}
