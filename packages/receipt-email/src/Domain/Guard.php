<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;

/**
 * The checks the values of a receipt share: texts that are printed, numbers that are printed and compared, amounts of
 * one currency. A refusal names the field, never its value, which may be personal.
 *
 * @internal
 */
final class Guard
{
    /** The longest text of a receipt's field, in characters. */
    public const int MAX_TEXT = 500;

    /** The longest number or code, in characters. */
    public const int MAX_IDENTIFIER = 64;

    /**
     * A text that is printed: valid UTF-8, not blank, without control characters, at most `$max` characters.
     */
    public static function text(string $value, string $field, int $max = self::MAX_TEXT): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || '' === trim($value) || 1 === preg_match('/[\x00-\x1F\x7F]/u', $value) || mb_strlen($value) > $max) {
            throw InvalidReceipt::because(\sprintf('%s is a text of 1 to %d characters, without line breaks or control characters.', $field, $max));
        }

        return $value;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function lines(array $lines, string $field, int $max = 10): array
    {
        if (\count($lines) > $max) {
            throw InvalidReceipt::because(\sprintf('%s holds a list of at most %d lines.', $field, $max));
        }

        foreach ($lines as $line) {
            self::text($line, $field);
        }

        return $lines;
    }

    /**
     * A number or a code: 1 to `$max` visible ASCII characters, which a file name and a header may carry.
     */
    public static function identifier(string $value, string $field, int $max = self::MAX_IDENTIFIER): string
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,'.$max.'}$/D', $value)) {
            throw InvalidReceipt::because(\sprintf('%s is 1 to %d visible ASCII characters.', $field, $max));
        }

        return $value;
    }

    public static function currency(Money $amount, Currency $currency, string $field): Money
    {
        if (!$amount->currency->equals($currency)) {
            throw InvalidReceipt::because(\sprintf('%s is in %s, and the receipt in %s.', $field, $amount->currency->code, $currency->code));
        }

        return $amount;
    }
}
