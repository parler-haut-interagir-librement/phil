<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

use DateTimeInterface;
use IntlDateFormatter;
use NumberFormatter;
use Phil\DataTableBundle\Application\Definition\Column;

/**
 * Writes numbers and dates for one locale. It lives for one render: a formatter is built once per column of it, and
 * nothing stays for the next request.
 */
final class Formats
{
    /** @var array<string, NumberFormatter> */
    private array $numbers = [];

    /** @var array<string, IntlDateFormatter> */
    private array $dates = [];

    public function __construct(
        private readonly string $locale,
    ) {
    }

    public function number(Column $column, int|float|string $value): string
    {
        $formatter = $this->numbers[$column->name] ??= $this->numberFormatter($column);

        return (string) $formatter->format(\is_string($value) ? (float) $value : $value);
    }

    public function date(Column $column, DateTimeInterface $value): string
    {
        $this->dates[$column->name] ??= new IntlDateFormatter($this->locale, $column->dateStyle, $column->timeStyle, $column->timezone ?? date_default_timezone_get());

        return (string) $this->dates[$column->name]->format($value);
    }

    private function numberFormatter(Column $column): NumberFormatter
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);

        if (null !== $column->decimals) {
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $column->decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $column->decimals);
        }

        return $formatter;
    }
}
