<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

/**
 * A component of an iCalendar text — `VCALENDAR`, `VEVENT`, `VTIMEZONE`, `VALARM`… —: its name, its lines, the
 * components inside it, and the first error of a line inside it or inside a component it holds.
 *
 * @internal
 */
final class Component
{
    /** @var list<ContentLine> */
    public array $lines = [];

    /** @var list<self> */
    public array $children = [];

    public ?string $error = null;

    public function __construct(
        public readonly string $name,
    ) {
    }

    /**
     * The lines of a property, in the order of the text.
     *
     * @return list<ContentLine>
     */
    public function all(string $name): array
    {
        return array_values(array_filter($this->lines, static fn (ContentLine $line): bool => $line->name === $name));
    }

    /**
     * @return list<self>
     */
    public function childrenNamed(string $name): array
    {
        return array_values(array_filter($this->children, static fn (self $child): bool => $child->name === $name));
    }
}
