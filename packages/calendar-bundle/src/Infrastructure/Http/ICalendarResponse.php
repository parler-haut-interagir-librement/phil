<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Http;

use DateTimeInterface;
use Phil\CalendarBundle\Application\ICalendar;
use Phil\CalendarBundle\Domain\Model\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * Events as an `.ics` file, inline: calendars subscribe to the address.
 */
final class ICalendarResponse extends Response
{
    /**
     * @param iterable<Event> $events
     */
    public function __construct(iterable $events, DateTimeInterface $stamp, ?string $name = null, string $product = '-//PHIL//phil/calendar-bundle//EN')
    {
        parent::__construct(ICalendar::of($events, $stamp, $product, $name), self::HTTP_OK, [
            'Content-Type' => ICalendar::MEDIA_TYPE.'; charset=utf-8',
            'Content-Disposition' => 'inline; filename=calendar.ics',
        ]);
    }
}
