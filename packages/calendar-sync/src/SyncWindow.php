<?php

declare(strict_types=1);

namespace Phil\CalendarSync;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\CalendarSync\Exception\InvalidSync;

/**
 * The period read from a calendar, in UTC: from a moment, up to another, excluded. At most `MAX_DAYS` days, so that one
 * read costs a bounded time, whatever a rule repeats.
 */
final readonly class SyncWindow
{
    public const int MAX_DAYS = 400;

    public DateTimeImmutable $from;

    public DateTimeImmutable $to;

    /**
     * @throws InvalidSync when the end is not after the start, or is more than `MAX_DAYS` days after it
     */
    public function __construct(DateTimeInterface $from, DateTimeInterface $to)
    {
        $utc = new DateTimeZone('UTC');
        $this->from = DateTimeImmutable::createFromInterface($from)->setTimezone($utc);
        $this->to = DateTimeImmutable::createFromInterface($to)->setTimezone($utc);

        if ($this->to <= $this->from) {
            throw InvalidSync::because('A window ends after it starts.');
        }

        if ($this->to > $this->from->modify(\sprintf('+%d days', self::MAX_DAYS))) {
            throw InvalidSync::because(\sprintf('A window is %d days at most.', self::MAX_DAYS));
        }
    }

    /**
     * So many days from a moment.
     */
    public static function days(DateTimeInterface $from, int $days): self
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw InvalidSync::because(\sprintf('A window is 1 to %d days.', self::MAX_DAYS));
        }

        return new self($from, DateTimeImmutable::createFromInterface($from)->modify(\sprintf('+%d days', $days)));
    }

    /**
     * Whether a moment falls in the window: from its start, before its end.
     */
    public function holds(DateTimeInterface $moment): bool
    {
        return $moment >= $this->from && $moment < $this->to;
    }
}
