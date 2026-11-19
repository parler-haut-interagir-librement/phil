<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Http;

use Phil\CalendarBundle\Application\ICalendar;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A member's own feed, at the address of their secret: the occurrences of their calendar — attended, hosted, followed —
 * from a month ago to a year ahead, as an iCalendar file to subscribe to. A secret malformed, unknown or revoked is
 * answered `404 Not Found`, as any address that does not exist.
 *
 * The answer is private to the member: no shared cache keeps it, no robot indexes it, and no page it links to learns
 * its address. It carries an entity tag of its occurrences and their events' versions, and a client that sends it back
 * is answered `304 Not Modified`.
 */
final readonly class PersonalFeedController
{
    public function __construct(
        private PersonalFeeds $feeds,
        private PersonalCalendar $calendar,
        private ClockInterface $clock,
        private ?string $name = null,
    ) {
    }

    public function ics(Request $request, string $token): Response
    {
        $member = $this->feeds->memberOf($token);

        if (!$member instanceof Member) {
            return $this->private(new Response('', Response::HTTP_NOT_FOUND));
        }

        $now = $this->clock->now();
        $occurrences = $this->calendar->occurrences($member, $now->modify('-1 month'), $now->modify('+1 year'));
        $tag = hash('xxh128', implode("\n", [$this->name ?? '', ...array_map(static fn (Occurrence $occurrence): string => \sprintf('%s:%d', $occurrence->id(), $occurrence->event->version()), $occurrences)]));

        $unchanged = $this->private(new Response());
        $unchanged->setEtag($tag);

        if ($unchanged->isNotModified($request)) {
            return $unchanged;
        }

        $response = $this->private(new Response(ICalendar::ofOccurrences($occurrences, $now, name: $this->name), Response::HTTP_OK, [
            'Content-Type' => ICalendar::MEDIA_TYPE.'; charset=utf-8',
            'Content-Disposition' => 'inline; filename=calendar.ics',
        ]));
        $response->setEtag($tag);

        return $response;
    }

    private function private(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
