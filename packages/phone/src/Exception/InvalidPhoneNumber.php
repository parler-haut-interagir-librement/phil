<?php

declare(strict_types=1);

namespace Phil\Phone\Exception;

use InvalidArgumentException;

/**
 * An input that is no phone number, or a region with no numbering plan.
 */
final class InvalidPhoneNumber extends InvalidArgumentException implements PhoneException
{
    public static function notANumber(string $input): self
    {
        return new self(\sprintf('"%s" is not a phone number.', self::shown($input)));
    }

    public static function unknownCountryCode(string $input): self
    {
        return new self(\sprintf('"%s" starts with no known country code.', self::shown($input)));
    }

    public static function noRegion(string $input): self
    {
        return new self(\sprintf('"%s" is written without its country code, and no region is given.', self::shown($input)));
    }

    public static function unknownRegion(string $region): self
    {
        return new self(\sprintf('"%s" is no region with a numbering plan.', self::shown($region)));
    }

    private static function shown(string $input): string
    {
        return mb_substr($input, 0, 100);
    }
}
