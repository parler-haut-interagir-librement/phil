<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * The columns of a row read back with their types: drivers give integers as strings, and a row that is not what the
 * tables hold is refused rather than guessed at.
 *
 * @internal
 */
final readonly class Row
{
    private const int MICROSECONDS = 1_000_000;

    /**
     * @param array<string, mixed> $row
     */
    public function __construct(
        private array $row,
    ) {
    }

    /**
     * @throws UnexpectedValueException when the column is not a text
     */
    public function string(string $column): string
    {
        $value = $this->row[$column] ?? null;

        if (!\is_string($value)) {
            throw new UnexpectedValueException(\sprintf('The column "%s" of the stockroom is not a text.', $column));
        }

        return $value;
    }

    /**
     * @throws UnexpectedValueException when the column is neither null nor a text
     */
    public function optional(string $column): ?string
    {
        return null === ($this->row[$column] ?? null) ? null : $this->string($column);
    }

    /**
     * @throws UnexpectedValueException when the column is not a whole number
     */
    public function integer(string $column): int
    {
        $value = $this->row[$column] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        if (!\is_string($value) || 1 !== preg_match('/^-?\d{1,19}$/D', $value)) {
            throw new UnexpectedValueException(\sprintf('The column "%s" of the stockroom is not a whole number.', $column));
        }

        return (int) $value;
    }

    /**
     * The moment a column of microseconds since the Unix epoch holds, in UTC.
     *
     * @throws UnexpectedValueException when the column is not a whole number
     */
    public function moment(string $column): DateTimeImmutable
    {
        $microseconds = $this->integer($column);
        $seconds = intdiv($microseconds, self::MICROSECONDS);
        $fraction = $microseconds % self::MICROSECONDS;

        return new DateTimeImmutable('@'.$seconds)->modify(\sprintf('%+d microseconds', $fraction))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * A moment as the microseconds since the Unix epoch a column keeps.
     */
    public static function microseconds(DateTimeImmutable $moment): int
    {
        return (int) $moment->format('U') * self::MICROSECONDS + (int) $moment->format('u');
    }
}
