<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

/**
 * One unfolded line of an iCalendar text: its name in uppercase, its parameters by uppercase name, their values
 * unquoted and decoded (RFC 6868), and its value as written. A line that cannot be read keeps its error instead.
 *
 * @internal
 */
final readonly class ContentLine
{
    /**
     * @param array<string, list<string>> $parameters
     */
    public function __construct(
        public string $name,
        public array $parameters,
        public string $value,
        public ?string $error = null,
    ) {
    }

    public static function malformed(string $error): self
    {
        return new self('', [], '', $error);
    }

    /**
     * The first value of a parameter, or null.
     */
    public function parameter(string $name): ?string
    {
        return $this->parameters[$name][0] ?? null;
    }
}
