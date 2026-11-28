<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * One cell: its kind and its value. A text is a text whatever it looks like, a number is an integer or a finite float,
 * and a date keeps the wall-clock time it was given, as a spreadsheet has no time zones.
 *
 * An empty text is an empty cell: no format tells them apart.
 */
final readonly class Cell
{
    private function __construct(
        public CellType $type,
        public string|int|float|bool|DateTimeImmutable|null $value,
    ) {
    }

    public static function empty(): self
    {
        return new self(CellType::Empty, null);
    }

    public static function text(string $text): self
    {
        return '' === $text ? self::empty() : new self(CellType::Text, $text);
    }

    /**
     * @throws InvalidSpreadsheet when the number is infinite or not a number
     */
    public static function number(int|float $number): self
    {
        if (\is_float($number) && !is_finite($number)) {
            throw InvalidSpreadsheet::because('A number cell holds a finite number.');
        }

        return new self(CellType::Number, $number);
    }

    public static function boolean(bool $value): self
    {
        return new self(CellType::Boolean, $value);
    }

    /**
     * A day: the time of day of the date given is dropped.
     */
    public static function date(DateTimeInterface $date): self
    {
        return new self(CellType::Date, DateTimeImmutable::createFromInterface($date)->setTime(0, 0));
    }

    public static function dateTime(DateTimeInterface $dateTime): self
    {
        return new self(CellType::DateTime, DateTimeImmutable::createFromInterface($dateTime));
    }

    /**
     * The cell of a PHP value, by its PHP type alone: a string is a text, an integer or a float a number, a boolean a
     * boolean, a date a date and time, null an empty cell, and a cell itself.
     *
     * @throws InvalidSpreadsheet when the value is of another type, or a number that is not finite
     */
    public static function from(mixed $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            null === $value => self::empty(),
            \is_string($value) => self::text($value),
            \is_int($value), \is_float($value) => self::number($value),
            \is_bool($value) => self::boolean($value),
            $value instanceof DateTimeInterface => self::dateTime($value),
            default => throw InvalidSpreadsheet::because(\sprintf('A cell cannot hold a value of type %s.', get_debug_type($value))),
        };
    }

    public function isEmpty(): bool
    {
        return CellType::Empty === $this->type;
    }
}
