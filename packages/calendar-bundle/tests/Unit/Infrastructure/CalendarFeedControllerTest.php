<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Infrastructure\Http\CalendarFeedController;
use Phil\CalendarBundle\Infrastructure\Http\ICalendarResponse;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CalendarFeedController::class)]
#[CoversClass(ICalendarResponse::class)]
final class CalendarFeedControllerTest extends TestCase
{
    private function calendar(): Calendar
    {
        $calendar = new Calendar(new InMemoryEvents());

        foreach ([['Soirée', '2026-11-10 19:00', '2026-11-10 22:00', false, 'FREQ=WEEKLY;COUNT=2', ['jeux']], ['Festival', '2026-11-14 00:00', '2026-11-15 00:00', true, null, []]] as [$title, $start, $end, $allDay, $rule, $tags]) {
            $draft = new EventDraft();
            $draft->title = $title;
            $draft->start = new DateTimeImmutable($start);
            $draft->end = new DateTimeImmutable($end);
            $draft->allDay = $allDay;
            $draft->timezone = 'America/Toronto';
            $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);
            $draft->tags = $tags;
            $draft->url = 'Festival' === $title ? 'https://example.com/festival' : null;
            $event = $calendar->schedule($draft);

            if ('Festival' === $title) {
                $event->cancel();
            } else {
                $calendar->host($event, 'association', '42');
            }
        }

        return $calendar;
    }

    /**
     * The feed of a host or a tag, and of everything when the host is no `kind:reference`.
     */
    public function testTheFeedIsOfAHostAndATag(): void
    {
        $clock = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-03 12:00', new DateTimeZone('UTC'));
            }
        };
        $controller = new CalendarFeedController($this->calendar(), $clock);
        $count = static fn (string $query): int => substr_count((string) $controller->ics(Request::create('/calendar.ics?'.$query))->getContent(), 'BEGIN:VEVENT');

        self::assertSame([1, 1, 0, 2, 2, 0], [$count('host=association:42'), $count('tag=jeux'), $count('host=shop:1'), $count('host=association'), $count('tag='), $count('host=association:42:x')]);
    }

    public function testTheEventsOfTheYearAreAnICalendarFile(): void
    {
        $clock = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-03 12:00', new DateTimeZone('UTC'));
            }
        };
        $response = new CalendarFeedController($this->calendar(), $clock, 'Le Club')->ics(Request::create('/calendar.ics?host=association:42'));
        $file = (string) $response->getContent();

        self::assertSame(['text/calendar; charset=utf-8', 'inline; filename=calendar.ics'], [$response->headers->get('Content-Type'), $response->headers->get('Content-Disposition')]);
        self::assertSame(1, substr_count($file, 'BEGIN:VEVENT'));
        self::assertStringContainsString("X-WR-CALNAME:Le Club\r\n", $file);
        self::assertStringContainsString("DTSTAMP:20261103T120000Z\r\n", $file);
        self::assertSame(2, substr_count((string) new CalendarFeedController($this->calendar(), $clock)->ics(Request::create('/calendar.ics'))->getContent(), 'BEGIN:VEVENT'));
        self::assertSame(0, substr_count((string) new CalendarFeedController($this->calendar(), $clock)->ics(Request::create('/calendar.ics?tag=none'))->getContent(), 'BEGIN:VEVENT'));
        self::assertStringContainsString('BEGIN:VEVENT', (string) new CalendarFeedController($this->calendar(), new NativeClock())->ics(Request::create('/calendar.ics'))->getContent());
        self::assertStringContainsString('PRODID:-//Example//X//EN', (string) new ICalendarResponse([], new DateTimeImmutable(), null, '-//Example//X//EN')->getContent());
    }

    public function testAnUnchangedFeedIsNotWrittenAgain(): void
    {
        $clock = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-03 12:00', new DateTimeZone('UTC'));
            }
        };
        $calendar = $this->calendar();
        $controller = new CalendarFeedController($calendar, $clock, 'Le Club');
        $first = $controller->ics(Request::create('/calendar.ics'));
        $tag = (string) $first->getEtag();

        self::assertSame([200, true, true], [$first->getStatusCode(), str_starts_with($tag, '"') && \strlen($tag) > 2, $first->headers->hasCacheControlDirective('public')]);
        self::assertTrue($first->headers->hasCacheControlDirective('no-cache'), 'a cache asks again before using it');

        $again = $controller->ics(Request::create('/calendar.ics', server: ['HTTP_IF_NONE_MATCH' => $tag]));
        self::assertSame([304, '', $tag], [$again->getStatusCode(), (string) $again->getContent(), $again->getEtag()]);

        self::assertNotSame($tag, $controller->ics(Request::create('/calendar.ics?tag=jeux'))->getEtag(), 'other events, another tag');
        self::assertNotSame($tag, new CalendarFeedController($calendar, $clock, 'Autre')->ics(Request::create('/calendar.ics'))->getEtag(), 'another name, another tag');
        self::assertNotSame($tag, new CalendarFeedController($this->calendar(), $clock, 'Le Club')->ics(Request::create('/calendar.ics'))->getEtag(), 'other events of the same versions, another tag');
        self::assertSame(200, $controller->ics(Request::create('/calendar.ics', server: ['HTTP_IF_NONE_MATCH' => '"other"']))->getStatusCode());
    }
}
