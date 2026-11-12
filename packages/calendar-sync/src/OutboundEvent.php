<?php

declare(strict_types=1);

namespace Phil\CalendarSync;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\Url\Url;

/**
 * An event the site writes into an external calendar — a booking, a session someone signed up for —: its identifier,
 * stable across writes so that writing it again replaces it, its title, its start and end in its time zone, or whole
 * days, and what else the application chooses to show there.
 *
 * The end is excluded, as iCalendar's `DTEND`: a whole-day event ends on the day after its last. What the event shows
 * is the application's choice: a site that only blocks the time gives a title such as "Busy" and nothing else.
 */
final readonly class OutboundEvent
{
    public const int MAX_UID = 255;

    public const int MAX_TITLE = 255;

    public const int MAX_TEXT = 8000;

    public DateTimeImmutable $start;

    public DateTimeImmutable $end;

    /**
     * @param DateTimeInterface $start in the event's time zone; midnight of the first day for whole days
     * @param DateTimeInterface $end   after the start, excluded; midnight of the day after the last for whole days
     */
    public function __construct(
        public string $uid,
        public string $title,
        DateTimeInterface $start,
        DateTimeInterface $end,
        public bool $allDay = false,
        public ?string $description = null,
        public ?string $location = null,
        public ?Url $url = null,
        public int $sequence = 0,
    ) {
        $this->start = DateTimeImmutable::createFromInterface($start);
        $this->end = DateTimeImmutable::createFromInterface($end)->setTimezone($this->start->getTimezone());
        $reason = match (true) {
            1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $uid) => \sprintf('The identifier of an event is 1 to %d visible ASCII characters.', self::MAX_UID),
            '' === trim($title) || mb_strlen($title) > self::MAX_TITLE => \sprintf('The title of an event is 1 to %d characters.', self::MAX_TITLE),
            mb_strlen((string) $description) > self::MAX_TEXT || mb_strlen((string) $location) > self::MAX_TITLE => 'The description of an event is 8000 characters at most, its place 255.',
            $this->end <= $this->start => 'An event ends after it starts.',
            $allDay && ('00:00:00' !== $this->start->format('H:i:s') || '00:00:00' !== $this->end->format('H:i:s')) => 'A whole-day event starts and ends at midnight.',
            $sequence < 0 => 'The sequence of an event is 0 or more.',
            default => null,
        };

        if (null !== $reason) {
            throw InvalidSync::because($reason);
        }
    }
}
