<?php

declare(strict_types=1);

namespace Phil\CalendarSync\ICalendar;

use Phil\CalendarSync\Exception\InvalidCalendarData;

/**
 * The components of an iCalendar text that hold busy times, `VEVENT` and `VFREEBUSY`, each with the properties read of
 * it — not those of a component inside it, an alarm —: lines unfolded, nesting checked, sizes bounded.
 *
 * @internal
 */
final class Components
{
    /** The properties read; the others are skipped without being decoded. */
    private const array READ = ['DTSTART', 'DTEND', 'DURATION', 'RRULE', 'RDATE', 'EXDATE', 'RECURRENCE-ID', 'UID', 'STATUS', 'TRANSP', 'FREEBUSY'];

    private const string PARAMETERS = '/\G;([A-Za-z0-9-]++)=((?:"[^"]*+"|[^";:,]*+)(?:,(?:"[^"]*+"|[^";:,]*+))*+)/';

    /** @var list<string> */
    private array $stack = [];

    private int $count = 0;

    /** @var list<array{string, array<string, list<array{array<string, string>, string}>>}> */
    private array $found = [];

    /** @var array<string, list<array{array<string, string>, string}>> */
    private array $properties = [];

    private function __construct()
    {
    }

    /**
     * @return list<array{string, array<string, list<array{array<string, string>, string}>>}>
     *
     * @throws InvalidCalendarData
     */
    public static function of(string $text): array
    {
        $components = new self();
        $text = str_starts_with($text, "\u{FEFF}") ? substr($text, 3) : $text;

        $lines = preg_split('/\r\n|\n|\r/', preg_replace('/(?:\r\n|\n|\r)[ \t]/', '', $text) ?? '');

        foreach (false === $lines ? [] : $lines as $line) {
            if ('' !== $line) {
                $components->line($line);
            }
        }

        if (0 === $components->count) {
            throw InvalidCalendarData::because('A calendar starts with BEGIN:VCALENDAR.');
        }

        if ([] !== $components->stack) {
            throw InvalidCalendarData::because('A calendar ends with END:VCALENDAR.');
        }

        return $components->found;
    }

    private function line(string $line): void
    {
        $nameLength = strcspn($line, ';:');
        $name = strtoupper(substr($line, 0, $nameLength));
        $colon = $this->colon($line, $nameLength);
        $value = strtoupper(trim(substr($line, $colon + 1)));

        if ([] === $this->stack && ('BEGIN' !== $name || 'VCALENDAR' !== $value)) {
            throw InvalidCalendarData::because(0 === $this->count ? 'A calendar starts with BEGIN:VCALENDAR.' : 'A calendar holds nothing after END:VCALENDAR.');
        }

        match (true) {
            'BEGIN' === $name => $this->begin($value),
            'END' === $name => $this->end($value),
            2 === \count($this->stack) && \in_array($name, self::READ, true) => $this->property($name, $line, $nameLength, $colon),
            default => null,
        };
    }

    private function begin(string $component): void
    {
        if (++$this->count > ICalendarReader::MAX_COMPONENTS || \count($this->stack) >= ICalendarReader::MAX_DEPTH) {
            throw InvalidCalendarData::because(\sprintf('A calendar holds %d components at most, %d deep.', ICalendarReader::MAX_COMPONENTS, ICalendarReader::MAX_DEPTH));
        }

        $this->stack[] = $component;

        if (2 === \count($this->stack)) {
            $this->properties = [];
        }
    }

    private function end(string $component): void
    {
        if (array_last($this->stack) !== $component) {
            throw InvalidCalendarData::because('The components of a calendar end in the order they begin.');
        }

        if (2 === \count($this->stack) && \in_array($component, ['VEVENT', 'VFREEBUSY'], true)) {
            $this->found[] = [$component, $this->properties];
        }

        array_pop($this->stack);
    }

    private function property(string $name, string $line, int $nameLength, int $colon): void
    {
        if (\strlen($line) > ICalendarReader::MAX_LINE) {
            throw InvalidCalendarData::because(\sprintf('A property of a calendar read is %d bytes at most.', ICalendarReader::MAX_LINE));
        }

        $this->properties[$name][] = [$this->parameters(substr($line, 0, $colon), $nameLength), substr($line, $colon + 1)];
    }

    /**
     * Where the value of a line starts: the first colon outside the quotes of its parameters.
     */
    private function colon(string $line, int $from): int
    {
        for ($at = $from, $length = \strlen($line); $at < $length; ++$at) {
            if (':' === $line[$at]) {
                return $at;
            }

            if ('"' === $line[$at]) {
                $close = strpos($line, '"', $at + 1);
                $at = false === $close ? $length : $close;
            }
        }

        throw InvalidCalendarData::because('A line of a calendar is a name, its parameters, a colon and a value.');
    }

    /**
     * @return array<string, string> by uppercase name, the quotes of a value taken away
     */
    private function parameters(string $text, int $at): array
    {
        $parameters = [];

        for ($length = \strlen($text); $at < $length; $at += \strlen($match[0])) {
            if (1 !== preg_match(self::PARAMETERS, $text, $match, 0, $at)) {
                throw InvalidCalendarData::because('A parameter of a calendar is a name, an equals sign and a value.');
            }

            $parameters[strtoupper($match[1])] = trim($match[2], '"');
        }

        return $parameters;
    }
}
