<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Profile;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ImportWizard\Plan\Reason;

/**
 * The type of a column, and how a cell of that type is read. A cell is read trimmed; an empty cell never reaches here.
 */
enum ColumnType: string
{
    /** Any text, as written. */
    case Text = 'text';
    /** A whole number of at most 18 digits, with an optional sign. */
    case Integer = 'integer';
    /** A decimal number kept as text, so that no digit is lost: `12,5` reads as `12.5`. */
    case Decimal = 'decimal';
    /** Yes or no: `1`, `true`, `yes`, `y`, `oui`, `o`, and `0`, `false`, `no`, `n`, `non`, in any case. */
    case Boolean = 'boolean';
    /** A calendar date written `YYYY-MM-DD`, read at midnight UTC. */
    case Date = 'date';
    /** An email address, read in lower case. */
    case Email = 'email';

    private const array TRUE = ['1', 'true', 'yes', 'y', 'oui', 'o'];

    private const array FALSE = ['0', 'false', 'no', 'n', 'non'];

    public function parse(string $cell): string|int|bool|DateTimeImmutable|Reason
    {
        return match ($this) {
            self::Text => $cell,
            self::Integer => 1 === preg_match('/^[+-]?\d{1,18}$/D', $cell) ? (int) $cell : Reason::NotAnInteger,
            self::Decimal => 1 === preg_match('/^[+-]?\d{1,30}(?:[.,]\d{1,30})?$/D', $cell) ? strtr($cell, ',', '.') : Reason::NotADecimal,
            self::Boolean => self::boolean(strtolower($cell)),
            self::Date => self::date($cell),
            self::Email => self::email(mb_strtolower($cell)),
        };
    }

    /**
     * The text of a value of this type, as a natural key and a fingerprint read it.
     */
    public static function text(string|int|bool|DateTimeImmutable $value): string
    {
        return match (true) {
            $value instanceof DateTimeImmutable => $value->format('Y-m-d'),
            \is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private static function boolean(string $cell): bool|Reason
    {
        return match (true) {
            \in_array($cell, self::TRUE, true) => true,
            \in_array($cell, self::FALSE, true) => false,
            default => Reason::NotABoolean,
        };
    }

    private static function date(string $cell): DateTimeImmutable|Reason
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $cell, new DateTimeZone('UTC'));

        return false !== $date && $date->format('Y-m-d') === $cell ? $date : Reason::NotADate;
    }

    private static function email(string $cell): string|Reason
    {
        return \strlen($cell) <= 254 && false !== filter_var($cell, \FILTER_VALIDATE_EMAIL, \FILTER_FLAG_EMAIL_UNICODE) ? $cell : Reason::NotAnEmail;
    }
}
