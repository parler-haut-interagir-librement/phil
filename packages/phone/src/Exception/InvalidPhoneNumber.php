<?php

declare(strict_types=1);

namespace Phil\Phone\Exception;

use InvalidArgumentException;

/**
 * An input that is no phone number, or a region with no numbering plan. A number mistyped is most often the real
 * number, which is personal data: the input stays out of the message, which may reach a log, and is kept in `$input`,
 * cut to 100 characters, for a caller that shows it back to the person who typed it. A region is no personal data and
 * is named in the message.
 */
final class InvalidPhoneNumber extends InvalidArgumentException implements PhoneException
{
    /** The longest part of the input kept in `$input`, in characters. */
    public const int SHOWN_LENGTH = 100;

    /**
     * @param string $message the reason, without the input
     * @param string $input   the input, cut to `SHOWN_LENGTH` characters by the named constructors
     */
    public function __construct(
        string $message,
        public readonly string $input,
    ) {
        parent::__construct($message);
    }

    public static function notANumber(string $input): self
    {
        return new self('The text is not a phone number.', self::shown($input));
    }

    public static function unknownCountryCode(string $input): self
    {
        return new self('The number starts with no known country code.', self::shown($input));
    }

    public static function noRegion(string $input): self
    {
        return new self('The number is written without its country code, and no region is given.', self::shown($input));
    }

    public static function unknownRegion(string $region): self
    {
        $shown = self::shown($region);

        return new self(\sprintf('"%s" is no region with a numbering plan.', $shown), $shown);
    }

    private static function shown(string $input): string
    {
        return mb_substr($input, 0, self::SHOWN_LENGTH);
    }
}
