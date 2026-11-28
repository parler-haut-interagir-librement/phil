<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

use Phil\CalendarImport\Exception\InvalidLimits;

/**
 * The bounds of the iCalendar reader. A text past one of them is refused whole, with `LimitExceeded` naming the bound,
 * before the rest of it is read: the work done on a text is bounded by these numbers, never by the text.
 */
final readonly class ReaderLimits
{
    public const int MAX_BYTES = 64 * 1024 * 1024;

    public const int MAX_EVENTS = 100_000;

    public const int MAX_LINE_BYTES = 1024 * 1024;

    public const int MAX_DEPTH = 16;

    public const int MAX_COMPONENTS = 200_000;

    /**
     * @param int $bytes      the bytes of the whole text
     * @param int $events     the events (`VEVENT`) of the text
     * @param int $lineBytes  the bytes of a line once unfolded
     * @param int $depth      the components nested in one another, `VCALENDAR` counted
     * @param int $components the components of the text, of every kind
     */
    public function __construct(
        public int $bytes = 5 * 1024 * 1024,
        public int $events = 5_000,
        public int $lineBytes = 16_384,
        public int $depth = 6,
        public int $components = 20_000,
    ) {
        $this->check('The bytes of a calendar', $bytes, 64, self::MAX_BYTES);
        $this->check('The events of a calendar', $events, 1, self::MAX_EVENTS);
        $this->check('The bytes of a line', $lineBytes, 75, self::MAX_LINE_BYTES);
        $this->check('The depth of a calendar', $depth, 2, self::MAX_DEPTH);
        $this->check('The components of a calendar', $components, 2, self::MAX_COMPONENTS);
    }

    private function check(string $what, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw InvalidLimits::outOfRange($what, $value, $min, $max);
        }
    }
}
