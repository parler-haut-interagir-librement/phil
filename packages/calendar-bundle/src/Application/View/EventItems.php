<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventTexts;
use Phil\CalendarBundle\Application\EventTextSet;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;

/**
 * The calendar's events as items, in the period's time zone: a time moved to it, whole days kept on their dates; their
 * titles, descriptions and places in the request's locale when they are translated. For a member, `itemsOf()` gives
 * those of their own calendar, `PersonalCalendar`.
 */
final readonly class EventItems implements ItemSource
{
    public function __construct(
        private Calendar $calendar,
        private EventRepository $events,
        private ?EventTexts $texts = null,
        private ?PersonalCalendar $personal = null,
    ) {
    }

    #[Override]
    public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->itemsFor($this->calendar->occurrences($from, $until->modify('-1 second')), $from->getTimezone());
    }

    /**
     * The items of a member's own calendar; none without a `PersonalCalendar`.
     *
     * @return list<Item>
     */
    public function itemsOf(Member $member, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->itemsFor($this->personal?->occurrences($member, $from, $until->modify('-1 second')) ?? [], $from->getTimezone());
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<Item>
     */
    private function itemsFor(array $occurrences, DateTimeZone $zone): array
    {
        $events = [];

        foreach ($occurrences as $occurrence) {
            $events[$occurrence->event->id] = $occurrence->event;
        }

        // The hosts of every event in one query.
        $hosts = array_map(static fn (array $of): array => array_map(static fn (EventHost $host): string => $host->kind.':'.$host->reference, $of), $this->events->hostsOfEach($events));

        // The texts in the request's locale, in one query per kind of record.
        $texts = $this->texts?->of($events) ?? new EventTextSet();

        return array_map(function (Occurrence $occurrence) use ($zone, $hosts, $texts): Item {
            $event = $occurrence->event;

            return new Item(
                $occurrence->id(),
                $texts->title($event),
                $this->moved($occurrence->start, $event->isAllDay(), $zone),
                $this->moved($occurrence->end, $event->isAllDay(), $zone),
                $event->isAllDay(),
                Item::EVENT,
                $event->urlOn($occurrence->start),
                $event->tags(),
                $hosts[$event->id] ?? [],
                $event->venue()?->id,
                $texts->place($event),
                $event->image(),
                $texts->description($event),
                $event->isCancelled(),
            );
        }, $occurrences);
    }

    private function moved(DateTimeImmutable $moment, bool $allDay, DateTimeZone $zone): DateTimeImmutable
    {
        return $allDay ? new DateTimeImmutable($moment->format('Y-m-d'), $zone) : $moment->setTimezone($zone);
    }
}
