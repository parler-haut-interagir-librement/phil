<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Http;

use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Domain\Model\Event;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The calendar's feed, for a host and a tag given in the query — `?host=association:42&tag=larp`: the events of the
 * coming year, and of the past month, as an iCalendar file to subscribe to. The calendar on a page is the
 * `PhilCalendar:Calendar` component.
 *
 * Calendars poll the address: the answer carries an entity tag made of the events it holds and their versions, and a
 * client or a shared cache that sends it back is answered `304 Not Modified` without the file being written. The file
 * changes when an event is changed, or enters or leaves the year.
 */
final readonly class CalendarFeedController
{
    public function __construct(
        private Calendar $calendar,
        private ClockInterface $clock,
        private ?string $name = null,
    ) {
    }

    public function ics(Request $request): Response
    {
        $now = $this->clock->now();
        [$kind, $reference] = $this->host($request);
        $events = $this->calendar->events($now->modify('-1 month'), $now->modify('+1 year'), $kind, $reference, $this->tag($request));
        $tag = hash('xxh128', implode("\n", [$this->name ?? '', ...array_map(static fn (Event $event): string => \sprintf('%s:%d', $event->id, $event->version()), $events)]));

        $unchanged = $this->cached(new Response(), $tag);

        if ($unchanged->isNotModified($request)) {
            return $unchanged;
        }

        return $this->cached(new ICalendarResponse($events, $now, $this->name), $tag);
    }

    /**
     * A response any cache may keep, provided it asks again with the entity tag before using it.
     */
    private function cached(Response $response, string $tag): Response
    {
        $response->setEtag($tag);
        $response->setPublic();
        $response->headers->addCacheControlDirective('no-cache');

        return $response;
    }

    /**
     * @return array{?string, ?string}
     */
    private function host(Request $request): array
    {
        $host = explode(':', $request->query->getString('host'), 2);

        return 2 === \count($host) ? [$host[0], $host[1]] : [null, null];
    }

    private function tag(Request $request): ?string
    {
        $tag = $request->query->getString('tag');

        return '' === $tag ? null : $tag;
    }
}
