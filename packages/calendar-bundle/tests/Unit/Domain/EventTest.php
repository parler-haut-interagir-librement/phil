<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\Recurrence\RecurrenceRule;
use Phil\Url\Exception\InvalidUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Event::class)]
#[CoversClass(EventHost::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(InvalidEvent::class)]
final class EventTest extends TestCase
{
    private static function at(string $moment, string $zone = 'America/Toronto'): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone($zone));
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<string>
     */
    private static function starts(array $occurrences): array
    {
        return array_map(static fn (Occurrence $occurrence): string => $occurrence->start->format('Y-m-d H:i'), $occurrences);
    }

    public function testAnEventIsScheduledInItsTimeZone(): void
    {
        $event = Event::schedule('  Soirée de jeux ', self::at('2026-11-10 19:00'), self::at('2026-11-10 22:00'));

        self::assertSame(['Soirée de jeux', 'America/Toronto', '2026-11-10T19:00:00-05:00', '2026-11-10T22:00:00-05:00', false, false, 1], [$event->title(), $event->timezone()->getName(), $event->start()->format('c'), $event->end()->format('c'), $event->isAllDay(), $event->isCancelled(), $event->version()]);
        self::assertSame([null, null, null, null, [], []], [$event->description(), $event->location(), $event->url(), $event->rule(), $event->exceptions(), $event->tags()]);
        self::assertSame(36, \strlen($event->id));
        self::assertSame('2026-11-11T03:00:00+00:00', $event->seriesEndsAt()?->format('c'));
        self::assertSame('Europe/Paris', Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 22:00'), timezone: 'Europe/Paris')->start()->getTimezone()->getName());
        self::assertSame('2026-11-11 01:00', Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 22:00'), timezone: 'Europe/Paris')->start()->format('Y-m-d H:i'));
    }

    public function testAWholeDayEventEndsOnItsLastDay(): void
    {
        $festival = Event::schedule('Festival', self::at('2026-07-10 15:00'), self::at('2026-07-12 09:00'), allDay: true);

        self::assertSame(['2026-07-10 00:00', '2026-07-12 00:00', true], [$festival->start()->format('Y-m-d H:i'), $festival->end()->format('Y-m-d H:i'), $festival->isAllDay()]);
        self::assertSame('2026-07-13T04:00:00+00:00', $festival->seriesEndsAt()?->format('c'));
        self::assertSame('2026-07-10', Event::schedule('Un jour', self::at('2026-07-10'), self::at('2026-07-10'), true)->end()->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function badTimes(): iterable
    {
        yield 'an end at its start' => ['2026-11-10 19:00', '2026-11-10 19:00', false, 'An event ends after it starts; a whole-day event, on its first day or later.'];
        yield 'an end before its start' => ['2026-11-10 19:00', '2026-11-10 18:00', false, 'An event ends after it starts; a whole-day event, on its first day or later.'];
        yield 'a last day before the first' => ['2026-07-10', '2026-07-09 23:00', true, 'An event ends after it starts; a whole-day event, on its first day or later.'];
    }

    #[DataProvider('badTimes')]
    public function testAnEventEndsAfterItStarts(string $start, string $end, bool $allDay, string $message): void
    {
        $this->expectException(InvalidEvent::class);
        $this->expectExceptionMessageIsOrContains($message);

        Event::schedule('Atelier', self::at($start), self::at($end), $allDay);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function badEvents(): iterable
    {
        yield 'no title' => [static fn (): Event => Event::schedule('  ', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00')), 'An event has a title.'];
        yield 'a long title' => [static fn (): Event => Event::schedule(str_repeat('é', 201), self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00')), 'The title of an event is 200 characters or fewer.'];
        yield 'an unknown time zone' => [static fn (): Event => Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'), timezone: 'Mars/Olympus'), '"Mars/Olympus" is not a time zone.'];
        yield 'a long unknown time zone' => [static fn (): Event => Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'), timezone: str_repeat('é', 150)), '"'.str_repeat('é', 100).'" is not a time zone.'];
        yield 'a long place' => [static function (): void { Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->describe(null, str_repeat('a', 256)); }, 'The location of an event is 255 characters or fewer.'];
        yield 'a page that is no address' => [static function (): void { Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->describe(null, null, 'example.com'); }, 'The page of an event is an http or https address.'];
        yield 'a page on an IP address' => [static function (): void { Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->describe(null, null, 'http://192.168.0.1/admin'); }, 'The page of an event is an http or https address.'];
        yield 'a page with a space' => [static function (): void { Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->describe(null, null, 'https://exa mple.com'); }, 'The page of an event is an http or https address.'];
        yield 'a page of another scheme' => [static function (): void { Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->describe(null, null, 'ftp://example.com'); }, 'The page of an event is an http or https address.'];
        yield 'a long page' => [static function (): void { Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->describe(null, null, 'https://example.com/'.str_repeat('a', 2029)); }, 'The page of an event is 2048 characters or fewer.'];
        yield 'a long description' => [static function (): void { Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->describe(str_repeat('a', 65536)); }, 'The description of an event is 65535 characters or fewer.'];
        yield 'a host without kind' => [static fn (): EventHost => new EventHost(Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00')), '', '42'), 'The kind of a host is 1 to 64 letters, digits, "_", ".", ":" or "-".'];
        yield 'a host with a space' => [static fn (): EventHost => new EventHost(Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00')), 'association', '4 2'), 'The reference of a host is 1 to 64 letters, digits, "_", ".", ":" or "-".'];
        yield 'a host too long' => [static fn (): EventHost => new EventHost(Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00')), str_repeat('a', 65), '42'), 'The kind of a host is 1 to 64 letters, digits, "_", ".", ":" or "-".'];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('badEvents')]
    public function testWhatCannotBeIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidEvent::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAnEventIsDescribedRenamedAndTagged(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'));
        $event->rename(' Soirée ');
        $event->describe(' Apportez vos jeux. ', ' Café X ', ' https://EXAMPLE.com/jeux ');
        $event->tag([' jeux ', 'jeux', '', str_repeat('a', 101), 'soirée', str_repeat('é', 100)]);

        self::assertSame(['Soirée', 'Apportez vos jeux.', 'Café X', 'https://example.com/jeux', ['jeux', 'soirée', str_repeat('é', 100)]], [$event->title(), $event->description(), $event->location(), $event->url(), $event->tags()]);

        $event->describe('', '', '');
        self::assertSame([null, null, null], [$event->description(), $event->location(), $event->url()]);
        self::assertSame(str_repeat('a', 200), Event::schedule(str_repeat('a', 200), self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'))->title());
        $event->describe(str_repeat('a', 65535), str_repeat('a', 255), 'http://example.com/'.str_repeat('a', 2029));
        self::assertSame(2048, \strlen((string) $event->url()));
    }

    public function testAnEventIsCancelledAndRestored(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'));
        $event->cancel();
        self::assertTrue($event->isCancelled());
        $event->restore();
        self::assertFalse($event->isCancelled());
    }

    public function testAnEventIsRescheduled(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'));
        $event->reschedule(self::at('2026-11-12 10:00', 'Europe/Paris'), self::at('2026-11-12 12:00', 'Europe/Paris'));

        self::assertSame(['America/Toronto', '2026-11-12 04:00', '2026-11-12 06:00'], [$event->timezone()->getName(), $event->start()->format('Y-m-d H:i'), $event->end()->format('Y-m-d H:i')]);

        $event->reschedule(self::at('2026-11-12 10:00', 'Europe/Paris'), self::at('2026-11-12 12:00', 'Europe/Paris'), timezone: 'Europe/Paris');
        self::assertSame(['Europe/Paris', '2026-11-12 10:00'], [$event->timezone()->getName(), $event->start()->format('Y-m-d H:i')]);
    }

    public function testAnEndlessEventHasAThousandOccurrencesAtMost(): void
    {
        $event = Event::schedule(str_repeat('é', 200), self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=DAILY'));

        self::assertSame(200, mb_strlen($event->title()));
        self::assertCount(1000, $event->occurrences(self::at('2026-01-01 00:00'), self::at('2031-01-01 00:00')));
        self::assertCount(2, $event->occurrences(self::at('2026-01-01 00:00'), self::at('2031-01-01 00:00'), 2));
    }

    public function testARepeatingEventHasItsOccurrences(): void
    {
        $event = Event::schedule('Soirée', self::at('2026-11-10 19:00'), self::at('2026-11-10 22:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU;COUNT=4'));
        $event->except(self::at('2026-12-08 19:00'));
        $event->except(new DateTimeImmutable('2026-12-09 00:00 UTC'));

        $occurrences = $event->occurrences(self::at('2026-11-01 00:00'), self::at('2027-06-01 00:00'));

        self::assertSame(['2026-11-10 19:00', '2027-01-12 19:00', '2027-02-09 19:00'], self::starts($occurrences));
        self::assertSame(['2026-11-10 22:00'], [$occurrences[0]->end->format('Y-m-d H:i')]);
        self::assertSame([$event->id.'@20261111T000000Z', $event], [$occurrences[0]->id(), $occurrences[0]->event]);
        self::assertSame(['2026-12-08T19:00:00-05:00'], array_map(static fn (DateTimeImmutable $exception): string => $exception->format('c'), $event->exceptions()));
        self::assertSame('2027-02-10T03:00:00+00:00', $event->seriesEndsAt()?->format('c'));
        self::assertSame('FREQ=MONTHLY;COUNT=4;BYDAY=2TU', (string) $event->rule());

        $event->except(self::at('2027-01-12 19:00'));
        self::assertCount(2, $event->exceptions());

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY'));
        self::assertSame([[], null], [$event->exceptions(), $event->seriesEndsAt()]);
        self::assertCount(3, $event->occurrences(self::at('2026-11-10 21:00'), self::at('2026-11-24 19:00')));
        self::assertSame(['2026-11-10 19:00'], self::starts($event->occurrences(self::at('2026-11-10 21:59'), self::at('2026-11-10 23:00'))));
        self::assertSame([], $event->occurrences(self::at('2026-11-10 22:00'), self::at('2026-11-17 18:59')));
        self::assertCount(2, $event->occurrences(self::at('2026-11-01'), self::at('2027-11-01'), limit: 2));

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20261124'));
        self::assertSame('2026-11-25T03:00:00+00:00', $event->seriesEndsAt()?->format('c'));
        $event->repeat(null);
        self::assertSame('2026-11-11T03:00:00+00:00', $event->seriesEndsAt()?->format('c'));
    }

    public function testTheEndOfASeriesIsReadBackFromTheEndOfItsRule(): void
    {
        $event = Event::schedule('Soirée', self::at('2026-01-01 10:00'), self::at('2026-01-01 11:00'));

        $event->repeat(RecurrenceRule::parse('FREQ=DAILY;UNTIL=99991231'));
        self::assertSame('9999-12-31T16:00:00+00:00', $event->seriesEndsAt()?->format('c'), 'the last day the occurrences can have');

        $event->repeat(RecurrenceRule::parse('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29;UNTIL=21030101'));
        self::assertSame('2096-02-29T16:00:00+00:00', $event->seriesEndsAt()?->format('c'), 'no February 29 from 2097 through 2103');

        $event->except(self::at('2096-02-29 10:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29;UNTIL=21030101'));
        self::assertSame('2092-02-29T16:00:00+00:00', $event->seriesEndsAt()?->format('c'), 'an occurrence taken away is no end');

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20251231'));
        self::assertSame('2026-01-01T16:00:00+00:00', $event->seriesEndsAt()?->format('c'), 'an end before the start leaves the first occurrence');
    }

    /**
     * The occurrences are read from the event as it is now: its rule, its start, its time zone and the occurrences
     * taken away, each changed after a read.
     */
    public function testTheOccurrencesFollowEachChangeOfTheEvent(): void
    {
        $event = Event::schedule('Soirée', self::at('2026-03-02 10:00'), self::at('2026-03-02 11:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));

        $read = static fn (): array => array_map(static fn (Occurrence $occurrence): string => $occurrence->start->format('c'), $event->occurrences(self::at('2026-03-01'), self::at('2026-04-01')));

        self::assertSame(['2026-03-02T10:00:00-05:00', '2026-03-09T10:00:00-04:00', '2026-03-16T10:00:00-04:00'], $read());

        $event->repeat(RecurrenceRule::parse('FREQ=DAILY;COUNT=2'));
        self::assertSame(['2026-03-02T10:00:00-05:00', '2026-03-03T10:00:00-05:00'], $read(), 'another rule');

        $event->except(self::at('2026-03-03 10:00'));
        self::assertSame(['2026-03-02T10:00:00-05:00'], $read(), 'an occurrence taken away');

        $event->reschedule(self::at('2026-03-02 12:00'), self::at('2026-03-02 13:00'));
        self::assertSame(['2026-03-02T12:00:00-05:00', '2026-03-03T12:00:00-05:00'], $read(), 'another start');

        $event->reschedule(self::at('2026-03-02 12:00'), self::at('2026-03-02 13:00'), false, 'Europe/Paris');
        self::assertSame(['2026-03-02T18:00:00+01:00', '2026-03-03T18:00:00+01:00'], $read(), 'another time zone');
    }

    /**
     * The end of the series is that of the event as it is now: its rule, start, end, whole days or not, time zone and
     * the occurrences taken away.
     */
    public function testTheEndOfASeriesFollowsEachChangeOfTheEvent(): void
    {
        $event = Event::schedule('Soirée', self::at('2026-03-02 10:00'), self::at('2026-03-02 11:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=4'));

        $end = static fn (): ?string => $event->seriesEndsAt()?->format('c');

        self::assertSame('2026-03-23T15:00:00+00:00', $end());

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));
        self::assertSame('2026-03-16T15:00:00+00:00', $end(), 'another rule');

        $event->reschedule(self::at('2026-03-02 12:00'), self::at('2026-03-02 13:00'));
        self::assertSame('2026-03-16T17:00:00+00:00', $end(), 'another start');

        $event->reschedule(self::at('2026-03-02 12:00'), self::at('2026-03-02 14:00'));
        self::assertSame('2026-03-16T18:00:00+00:00', $end(), 'another end');

        $event->reschedule(self::at('2026-03-02 00:00'), self::at('2026-03-03 00:00'));
        self::assertSame('2026-03-17T04:00:00+00:00', $end(), 'a time');
        $event->reschedule(self::at('2026-03-02 00:00'), self::at('2026-03-03 00:00'), true);
        self::assertSame('2026-03-18T04:00:00+00:00', $end(), 'whole days');

        $event->reschedule(self::at('2026-03-02 10:00'), self::at('2026-03-02 11:00'));
        $event->reschedule(self::at('2026-03-02 10:00'), self::at('2026-03-02 11:00'), false, 'Europe/Paris');
        self::assertSame('2026-03-16T16:00:00+00:00', $end(), 'another time zone, across a change of time only one has');

        $event->except(self::at('2026-03-16 16:00', 'Europe/Paris'));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));
        self::assertSame('2026-03-09T16:00:00+00:00', $end(), 'an occurrence taken away');
    }

    public function testTheOccurrencesTakenAwayStayWhileTheRuleStaysTheSame(): void
    {
        $event = Event::schedule('Soirée', self::at('2026-11-10 19:00'), self::at('2026-11-10 22:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY'));
        $event->except(self::at('2026-11-17 19:00'));

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY'));
        self::assertSame(['2026-11-17T19:00:00-05:00'], array_map(static fn (DateTimeImmutable $exception): string => $exception->format('c'), $event->exceptions()));
        self::assertSame(['2026-11-10 19:00', '2026-11-24 19:00'], self::starts($event->occurrences(self::at('2026-11-01'), self::at('2026-11-30'))));

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;INTERVAL=1'));
        self::assertCount(1, $event->exceptions());

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=10'));
        self::assertSame([], $event->exceptions());

        $event->except(self::at('2026-11-17 19:00'));
        $event->repeat(null);
        self::assertSame([], $event->exceptions());

        $once = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 22:00'));
        $once->except(self::at('2026-11-10 19:00'));
        $once->repeat(null);
        self::assertCount(1, $once->exceptions());
    }

    /**
     * @return iterable<string, array{string, string, string, bool, string, string, list<string>}>
     */
    public static function farPeriods(): iterable
    {
        yield 'a daily rule thirty years on' => ['2000-01-01 10:00', '2000-01-01 11:00', 'FREQ=DAILY', false, '2030-01-01 00:00', '2030-01-03 00:00', ['2030-01-01 10:00', '2030-01-02 10:00']];
        yield 'an occurrence that started before the period' => ['2000-01-01 22:00', '2000-01-04 02:00', 'FREQ=WEEKLY', false, '2040-01-02 00:00', '2040-01-02 12:00', ['2039-12-31 22:00']];
        yield 'a whole-day series' => ['2010-07-10', '2010-07-12', 'FREQ=YEARLY', true, '2050-07-12 23:00', '2050-07-20 00:00', ['2050-07-10 00:00']];
        yield 'the second Tuesday, far away' => ['2026-01-13 19:00', '2026-01-13 22:00', 'FREQ=MONTHLY;BYDAY=2TU', false, '2526-11-01 00:00', '2526-12-01 00:00', ['2526-11-12 19:00']];
    }

    /**
     * @param list<string> $starts
     */
    #[DataProvider('farPeriods')]
    public function testAPeriodFarFromTheFirstOccurrenceHasItsOccurrences(string $start, string $end, string $rule, bool $allDay, string $from, string $through, array $starts): void
    {
        $event = Event::schedule('Soirée', self::at($start), self::at($end), $allDay);
        $event->repeat(RecurrenceRule::parse($rule));

        self::assertSame($starts, self::starts($event->occurrences(self::at($from), self::at($through))));
    }

    /**
     * A period given in UTC is read far enough back across a change of time: the day of the change lasts an hour
     * more, and the spare days before the period may hold one occurrence more than they have days.
     */
    public function testAPeriodInAnotherTimeZoneIsReadAcrossAChangeOfTime(): void
    {
        $day = Event::schedule('Journée', self::at('2026-10-25 00:00'), self::at('2026-10-25 23:59'));
        $day->repeat(RecurrenceRule::parse('FREQ=DAILY'));

        self::assertSame(['2026-11-01 00:00', '2026-11-02 00:00'], self::starts($day->occurrences(self::at('2026-11-02 04:30', 'UTC'), self::at('2026-11-02 06:00', 'UTC'))), 'the day time falls back ends at 04:59 UTC');

        $morning = Event::schedule('Café', self::at('2026-03-01 10:00'), self::at('2026-03-01 10:15'));
        $morning->repeat(RecurrenceRule::parse('FREQ=DAILY'));

        self::assertSame(['2026-03-10 10:00'], self::starts($morning->occurrences(self::at('2026-03-09 14:30', 'UTC'), self::at('2026-03-10 23:00', 'UTC'), 1)), 'three occurrences over before the period, in its two spare days');
    }

    public function testAnImageIsAnAddressOrAPathOfTheSite(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00'));

        foreach (['/affiches/atelier.png', 'https://example.com/a.png', 'http://example.com/a.png'] as $image) {
            $event->illustrate($image);
            self::assertSame($image, $event->image());
        }

        // An address is kept as a browser reads it.
        $event->illustrate('HTTPS://Example.COM:443/a/../affiche.png');
        self::assertSame('https://example.com/affiche.png', $event->image());

        foreach (['//evil.example/x.png', '/\\evil.example/x.png', '/affiches\\..\\x.png', 'affiches/x.png', 'javascript:alert(1)', '\\\\evil.example\\x.png', 'https://127.0.0.1/x.png', 'https://localhost/x.png', 'https:///x.png'] as $image) {
            try {
                $event->illustrate($image);
                self::fail(\sprintf('"%s" is refused.', $image));
            } catch (InvalidEvent $invalid) {
                self::assertSame('The image of an event is an http or https address, or a path from the root.', $invalid->getMessage());
                self::assertInstanceOf(InvalidUrl::class, $invalid->getPrevious(), 'phil/url says why');
            }
        }

        $event->illustrate(null);
        self::assertNull($event->image());
    }

    public function testAWholeDayOccurrenceOverlapsItsLastDay(): void
    {
        $festival = Event::schedule('Festival', self::at('2026-07-10'), self::at('2026-07-12'), true);

        self::assertCount(1, $festival->occurrences(self::at('2026-07-12 23:59'), self::at('2026-07-20')));
        self::assertSame([], $festival->occurrences(self::at('2026-07-13 00:00'), self::at('2026-07-20')));
        self::assertCount(1, $festival->occurrences(self::at('2026-07-01'), self::at('2026-07-10 00:00')));
        self::assertSame([], $festival->occurrences(self::at('2026-07-01'), self::at('2026-07-09 23:59')));
    }

    public function testDatesAddedAreOccurrencesOfTheEventLength(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 21:00'));
        $event->addDate(self::at('2026-11-20 18:00'));
        $event->addDate(new DateTimeImmutable('2026-11-15 00:00 UTC'));
        $event->addDate(self::at('2026-11-20 18:00'));

        self::assertSame(['2026-11-14T19:00:00-05:00', '2026-11-20T18:00:00-05:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format('c'), $event->addedDates()));
        self::assertSame(['2026-11-10 19:00', '2026-11-14 19:00', '2026-11-20 18:00'], self::starts($event->occurrences(self::at('2026-11-01'), self::at('2026-12-01'))));
        self::assertSame('2026-11-20 20:00', $event->occurrences(self::at('2026-11-20'), self::at('2026-11-21'))[0]->end->format('Y-m-d H:i'));
        self::assertSame('2026-11-21T01:00:00+00:00', $event->seriesEndsAt()?->format('c'));

        $event->except(self::at('2026-11-14 19:00'));
        self::assertSame(['2026-11-10 19:00', '2026-11-20 18:00'], self::starts($event->occurrences(self::at('2026-11-01'), self::at('2026-12-01'))), 'taken away');

        $event->removeDate(self::at('2026-11-20 18:00'));
        $event->removeDate(self::at('2026-11-30 18:00'));
        self::assertSame(['2026-11-10 19:00'], self::starts($event->occurrences(self::at('2026-11-01'), self::at('2026-12-01'))), 'taken back');
        self::assertSame('2026-11-15T02:00:00+00:00', $event->seriesEndsAt()?->format('c'));

        $event->removeDate(self::at('2026-11-14 19:00'));
        self::assertSame([], $event->addedDates());
        self::assertSame('2026-11-11T02:00:00+00:00', $event->seriesEndsAt()?->format('c'));
    }

    public function testDatesAddedStayWithAnotherRuleAndGoWhenTheStartPassesThem(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 21:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=2'));
        $event->addDate(self::at('2026-12-24 19:00'));
        $event->addDate(self::at('2026-11-12 19:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));

        self::assertSame(['2026-11-10 19:00', '2026-11-12 19:00', '2026-11-17 19:00', '2026-11-24 19:00', '2026-12-24 19:00'], self::starts($event->occurrences(self::at('2026-11-01'), self::at('2027-01-01'))));
        self::assertSame('2026-12-25T02:00:00+00:00', $event->seriesEndsAt()?->format('c'));

        $event->reschedule(self::at('2026-11-12 19:00'), self::at('2026-11-12 21:00'));
        self::assertSame(['2026-12-24T19:00:00-05:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format('c'), $event->addedDates()));

        $event->reschedule(self::at('2026-12-24 19:00'), self::at('2026-12-24 21:00'));
        self::assertSame([], $event->addedDates());

        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY'));
        $event->addDate(self::at('2027-01-02 10:00'));
        self::assertNull($event->seriesEndsAt());
    }

    public function testAWholeDayEventAddsTheDayOfTheMoment(): void
    {
        $event = Event::schedule('Festival', self::at('2026-07-10'), self::at('2026-07-11'), true);
        $event->addDate(new DateTimeImmutable('2026-08-01 02:00 UTC'));

        self::assertSame(['2026-07-31T00:00:00-04:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format('c'), $event->addedDates()));
        self::assertSame(['2026-07-31 00:00'], self::starts($event->occurrences(self::at('2026-07-20'), self::at('2026-08-31'))));
        self::assertSame('2026-08-01 00:00', $event->occurrences(self::at('2026-07-20'), self::at('2026-08-31'))[0]->end->format('Y-m-d H:i'));
        self::assertSame('2026-08-02T04:00:00+00:00', $event->seriesEndsAt()?->format('c'));
    }

    public function testADateAddedIsAfterTheStartAndBounded(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 21:00'));

        foreach ([self::at('2026-11-10 19:00'), self::at('2026-11-09 19:00')] as $moment) {
            try {
                $event->addDate($moment);
                self::fail('Added.');
            } catch (InvalidEvent $invalidEvent) {
                self::assertSame('A date added to an event is after its start.', $invalidEvent->getMessage());
            }
        }

        for ($day = 1; $day <= Event::ADDED_DATES; ++$day) {
            $event->addDate(self::at('2026-11-10 19:00')->modify(\sprintf('+%d days', $day)));
        }

        $event->addDate(self::at('2026-11-11 19:00'));
        self::assertCount(Event::ADDED_DATES, $event->addedDates());

        $this->expectExceptionObject(InvalidEvent::because('An event has 1000 dates added at most.'));
        $event->addDate(self::at('2030-01-01 19:00'));
    }

    public function testDatesAddedKeepTheLengthOfTheEventAtAnyTimeOfDay(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00:00'), self::at('2026-11-10 21:00:00'));
        $event->addDate(self::at('2026-11-12 19:30:45'));
        $event->addDate(self::at('2026-11-13 18:59:59'));
        $event->addDate(self::at('2026-11-14 19:00:01'));

        $ends = array_map(static fn (Occurrence $occurrence): string => $occurrence->end->format('Y-m-d H:i:s'), $event->occurrences(self::at('2026-11-11'), self::at('2026-11-20')));

        self::assertSame(['2026-11-12 21:30:45', '2026-11-13 20:59:59', '2026-11-14 21:00:01'], $ends);
    }

    public function testTakingBackAnEarlierDateLeavesTheOthersInAList(): void
    {
        $event = Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 21:00'));
        $event->addDate(self::at('2026-11-12 19:00'));
        $event->addDate(self::at('2026-11-13 19:00'));
        $event->removeDate(self::at('2026-11-12 19:00'));

        self::assertSame(['2026-11-13T19:00:00-05:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format('c'), $event->addedDates()));
        self::assertSame([0], array_keys($event->addedDates()));
    }

    public function testAHostIsAKindAndAReference(): void
    {
        $host = new EventHost(Event::schedule('Atelier', self::at('2026-11-10 19:00'), self::at('2026-11-10 20:00')), 'association', 'club:42.a-b_c');

        self::assertSame([true, false, false, 36], [$host->is('association', 'club:42.a-b_c'), $host->is('shop', 'club:42.a-b_c'), $host->is('association', '43'), \strlen($host->id)]);
        self::assertSame('a', new EventHost($host->event, str_repeat('a', 64), 'a')->reference);
    }
}
