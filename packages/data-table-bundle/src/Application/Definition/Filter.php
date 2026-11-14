<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

use BackedEnum;

/**
 * A filter of a table. Each one names the fields of the data source it reads.
 */
final readonly class Filter
{
    /**
     * @param list<string>              $fields
     * @param array<int|string, string> $choices
     */
    private function __construct(
        public string $name,
        public FilterType $type,
        public string $label,
        public array $fields,
        public array $choices = [],
        public ?string $timezone = null,
    ) {
    }

    /**
     * Looks for the text in any of the fields.
     *
     * @param list<string> $fields the filter's name when empty
     */
    public static function text(string $name, string $label, array $fields = []): self
    {
        return new self($name, FilterType::Text, $label, [] === $fields ? [$name] : $fields);
    }

    /**
     * @param array<int|string, string>|class-string<BackedEnum> $choices value to translation key, or a backed enum
     */
    public static function enum(string $name, string $label, array|string $choices, ?string $field = null): self
    {
        return new self($name, FilterType::Enum, $label, [$field ?? $name], Choices::of($choices));
    }

    /**
     * Between two days, both included, in the address as `<name>_from` and `<name>_until`.
     *
     * @param string|null $timezone the zone of the days, PHP's default when null
     */
    public static function dateRange(string $name, string $label, ?string $field = null, ?string $timezone = null): self
    {
        return new self($name, FilterType::DateRange, $label, [$field ?? $name], timezone: $timezone);
    }

    public static function boolean(string $name, string $label, ?string $field = null): self
    {
        return new self($name, FilterType::Boolean, $label, [$field ?? $name]);
    }
}
