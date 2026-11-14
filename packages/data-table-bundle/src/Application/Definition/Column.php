<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

use BackedEnum;
use Closure;
use IntlDateFormatter;

/**
 * A column of a table. A value is read from the row's property of the column's name, unless `of()` names another path
 * or `reading()` gives a function. It is immutable: each method returns a new column.
 */
final readonly class Column
{
    /**
     * @param (Closure(array<string, mixed>|object): mixed)|null         $reader
     * @param array<int|string, string>                                  $choices
     * @param class-string<BackedEnum>|null                              $enum
     * @param (Closure(array<string, mixed>|object): (string|null))|null $href
     */
    private function __construct(
        public string $name,
        public ColumnType $type,
        public string $label,
        public ?string $property = null,
        public ?Closure $reader = null,
        public ?string $sortField = null,
        public ?Editing $editing = null,
        public ?int $decimals = null,
        public int $dateStyle = IntlDateFormatter::MEDIUM,
        public int $timeStyle = IntlDateFormatter::NONE,
        public ?string $timezone = null,
        public array $choices = [],
        public ?string $enum = null,
        public ?Closure $href = null,
        public ?string $template = null,
        public ?string $block = null,
    ) {
    }

    public static function text(string $name, string $label): self
    {
        return new self($name, ColumnType::Text, $label);
    }

    /**
     * @param int|null $decimals the digits after the point, as many as the value has when null
     */
    public static function number(string $name, string $label, ?int $decimals = null): self
    {
        return new self($name, ColumnType::Number, $label, decimals: $decimals);
    }

    /**
     * @param int         $dateStyle an `IntlDateFormatter` style
     * @param int         $timeStyle an `IntlDateFormatter` style, `NONE` for a date alone
     * @param string|null $timezone  the zone the date is shown in, PHP's default when null
     */
    public static function date(string $name, string $label, int $dateStyle = IntlDateFormatter::MEDIUM, int $timeStyle = IntlDateFormatter::NONE, ?string $timezone = null): self
    {
        return new self($name, ColumnType::Date, $label, dateStyle: $dateStyle, timeStyle: $timeStyle, timezone: $timezone);
    }

    /**
     * @param array<int|string, string>|class-string<BackedEnum> $choices value to translation key, or a backed enum
     */
    public static function enum(string $name, string $label, array|string $choices): self
    {
        return new self($name, ColumnType::Enum, $label, choices: Choices::of($choices), enum: \is_string($choices) ? $choices : null);
    }

    public static function boolean(string $name, string $label): self
    {
        return new self($name, ColumnType::Boolean, $label);
    }

    /**
     * @param Closure(array<string, mixed>|object): (string|null) $href the address of a row: `https:`, `http:`, `mailto:`, `tel:` or relative
     */
    public static function link(string $name, string $label, Closure $href): self
    {
        return new self($name, ColumnType::Link, $label, href: $href);
    }

    /**
     * A cell drawn by a block of a template, with `row` and `value` in its context.
     */
    public static function block(string $name, string $label, string $template, string $block): self
    {
        return new self($name, ColumnType::Block, $label, template: $template, block: $block);
    }

    /**
     * Reads the value from another property path of the row, `author.name` or `author?.name`.
     */
    public function of(string $property): self
    {
        return clone ($this, ['property' => $property]);
    }

    /**
     * @param Closure(array<string, mixed>|object): mixed $reader
     */
    public function reading(Closure $reader): self
    {
        return clone ($this, ['reader' => $reader]);
    }

    /**
     * Offers the column's sort.
     *
     * @param string|null $field the field the data source sorts by, the column's path when null
     */
    public function sortable(?string $field = null): self
    {
        return clone ($this, ['sortField' => $field ?? $this->path()]);
    }

    public function editable(Editing $editing): self
    {
        return clone ($this, ['editing' => $editing]);
    }

    public function path(): string
    {
        return $this->property ?? $this->name;
    }
}
