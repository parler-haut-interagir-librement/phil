<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

use Phil\CalendarImport\Exception\InvalidCalendar;
use Phil\CalendarImport\Exception\LimitExceeded;
use Phil\CalendarImport\Exception\UnreadableCalendar;

/**
 * The components of an iCalendar text, nested as `BEGIN` and `END` open and close them: one or more `VCALENDAR`, and
 * nothing outside them. The depth, the number of components and the number of events are bounded as they are read.
 *
 * A line that cannot be read inside an event, or inside a component an event holds, marks that event, which is then
 * refused alone; anywhere else, it refuses the text. One instance builds one tree.
 *
 * @internal
 */
final class ComponentTree
{
    /** @var list<Component> */
    private array $calendars = [];

    /** @var list<Component> */
    private array $stack = [];

    private int $components = 0;

    private int $events = 0;

    private function __construct(
        private readonly ReaderLimits $limits,
    ) {
    }

    /**
     * @param list<ContentLine> $lines
     *
     * @return list<Component> the `VCALENDAR` components, in the order of the text
     *
     * @throws UnreadableCalendar
     */
    public static function of(array $lines, ReaderLimits $limits): array
    {
        $tree = new self($limits);

        foreach ($lines as $line) {
            $tree->line($line);
        }

        if ([] === $tree->calendars) {
            throw InvalidCalendar::because('A calendar starts with BEGIN:VCALENDAR.');
        }

        if ([] !== $tree->stack) {
            throw InvalidCalendar::because('A calendar ends with END:VCALENDAR.');
        }

        return $tree->calendars;
    }

    private function line(ContentLine $line): void
    {
        $top = array_last($this->stack);

        if (!$top instanceof Component) {
            if ('BEGIN' !== $line->name || 'VCALENDAR' !== strtoupper($line->value)) {
                throw InvalidCalendar::because([] === $this->calendars ? 'A calendar starts with BEGIN:VCALENDAR.' : 'A calendar holds nothing after END:VCALENDAR.');
            }

            $this->begin('VCALENDAR', null);

            return;
        }

        match (true) {
            null !== $line->error => $this->mark($line->error),
            'BEGIN' === $line->name => $this->begin($this->name($line->value), $top),
            'END' === $line->name => $this->end($this->name($line->value), $top),
            default => $top->lines[] = $line,
        };
    }

    private function begin(string $name, ?Component $top): void
    {
        if (\count($this->stack) >= $this->limits->depth) {
            throw LimitExceeded::of(Limit::Depth, $this->limits->depth);
        }

        if (++$this->components > $this->limits->components) {
            throw LimitExceeded::of(Limit::Components, $this->limits->components);
        }

        if ('VEVENT' === $name && ++$this->events > $this->limits->events) {
            throw LimitExceeded::of(Limit::Events, $this->limits->events);
        }

        $component = new Component($name);

        if ($top instanceof Component) {
            $top->children[] = $component;
        } else {
            $this->calendars[] = $component;
        }

        $this->stack[] = $component;
    }

    private function end(string $name, Component $top): void
    {
        if ($top->name !== $name) {
            throw InvalidCalendar::because('The components of a calendar end in the order they begin.');
        }

        array_pop($this->stack);
    }

    private function name(string $value): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9-]{1,64}$/D', $value)) {
            throw InvalidCalendar::because('A component of a calendar is named by letters, digits and hyphens.');
        }

        return strtoupper($value);
    }

    /**
     * Marks the component a line that cannot be read belongs to: the component at the second level — an event, a time
     * zone —, which holds it or holds the component that does. At the first level, the text is refused.
     */
    private function mark(string $error): void
    {
        $owner = $this->stack[1] ?? throw InvalidCalendar::because($error);
        $owner->error ??= $error;
    }
}
