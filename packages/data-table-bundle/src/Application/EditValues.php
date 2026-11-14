<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\ColumnType;
use Phil\DataTableBundle\Application\Exception\InvalidEditValue;

/**
 * Between a column's value and what its control writes.
 */
final readonly class EditValues
{
    public function __construct(
        private RowValues $values,
    ) {
    }

    /**
     * The value of a row as its control writes it.
     */
    public function raw(Column $column, mixed $value): string
    {
        return match ($column->type) {
            ColumnType::Date => $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value)->setTimezone($this->zone($column))->format('Y-m-d') : '',
            ColumnType::Boolean => (bool) $value ? '1' : '0',
            default => $this->values->string($value),
        };
    }

    /**
     * What the control wrote, as the column's type.
     *
     * @throws InvalidEditValue when it cannot be read
     */
    public function convert(Column $column, string $raw): mixed
    {
        return match ($column->type) {
            ColumnType::Number => $this->number($column, $raw),
            ColumnType::Date => $this->date($column, $raw),
            ColumnType::Enum => $this->choice($column, $raw),
            ColumnType::Boolean => $this->boolean($column, $raw),
            default => $raw,
        };
    }

    private function number(Column $column, string $raw): int|float|null
    {
        if ('' === $raw) {
            return null;
        }

        if (!is_numeric($raw) || !is_finite((float) $raw)) {
            throw InvalidEditValue::forColumn($column->name);
        }

        return 0 === $column->decimals ? (int) $raw : $raw + 0;
    }

    private function date(Column $column, string $raw): ?DateTimeImmutable
    {
        if ('' === $raw) {
            return null;
        }

        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $this->zone($column));

        if (false === $day || $day->format('Y-m-d') !== $raw) {
            throw InvalidEditValue::forColumn($column->name);
        }

        return $day;
    }

    private function choice(Column $column, string $raw): mixed
    {
        if ('' === $raw) {
            return null;
        }

        if (!isset($column->choices[$raw])) {
            throw InvalidEditValue::forColumn($column->name);
        }

        if (null === $column->enum) {
            return $raw;
        }

        return array_find($column->enum::cases(), static fn (BackedEnum $case): bool => (string) $case->value === $raw);
    }

    private function boolean(Column $column, string $raw): bool
    {
        return match ($raw) {
            '1' => true,
            '0' => false,
            default => throw InvalidEditValue::forColumn($column->name),
        };
    }

    private function zone(Column $column): DateTimeZone
    {
        return new DateTimeZone($column->timezone ?? date_default_timezone_get());
    }
}
