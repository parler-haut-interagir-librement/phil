<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\BusyPeriods;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\Exception\UnknownConnection;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(BusyPeriod::class)]
#[CoversClass(BusyPeriods::class)]
#[CoversClass(SyncWindow::class)]
#[CoversClass(CalendarConnection::class)]
#[CoversClass(Credentials::class)]
#[CoversClass(OutboundEvent::class)]
#[CoversClass(InvalidSync::class)]
#[CoversClass(InvalidCalendarData::class)]
#[CoversClass(ProviderFailed::class)]
#[CoversClass(UnknownConnection::class)]
final class ValuesTest extends TestCase
{
    public function testABusyPeriodIsKeptInUtcAndEndsAfterItStarts(): void
    {
        $period = new BusyPeriod(new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-11T01:00:00Z'), true);

        self::assertSame('2026-11-11T00:00:00+00:00', $period->start->format('c'));
        self::assertSame('UTC', $period->end->getTimezone()->getName());
        self::assertTrue($period->tentative);
        self::assertFalse(new BusyPeriod($period->start, $period->end)->tentative);

        $this->expectExceptionObject(InvalidSync::because('A busy period ends after it starts.'));
        new BusyPeriod($period->start, $period->start);
    }

    public function testPeriodsOverlapWhenTheyShareAMoment(): void
    {
        $one = $this->period('10:00', '11:00');

        self::assertTrue($one->overlaps($this->period('10:59', '12:00')));
        self::assertTrue($this->period('10:59', '12:00')->overlaps($one));
        self::assertFalse($one->overlaps($this->period('11:00', '12:00')));
        self::assertFalse($this->period('11:00', '12:00')->overlaps($one));
    }

    public function testAPeriodIsCutToAWindow(): void
    {
        $window = new SyncWindow(new DateTimeImmutable('2026-11-10T10:00:00Z'), new DateTimeImmutable('2026-11-10T12:00:00Z'));
        $cut = $this->period('09:00', '13:00', true)->within($window);

        self::assertInstanceOf(BusyPeriod::class, $cut);
        self::assertSame(['10:00', '12:00', true], [$cut->start->format('H:i'), $cut->end->format('H:i'), $cut->tentative]);
        self::assertNull($this->period('12:00', '13:00')->within($window));
        self::assertNull($this->period('09:00', '10:00')->within($window));
    }

    public function testAWindowIsBoundedAndOrdered(): void
    {
        $from = new DateTimeImmutable('2026-11-10 00:00', new DateTimeZone('America/Toronto'));
        $window = SyncWindow::days($from, SyncWindow::MAX_DAYS);

        self::assertSame('2026-11-10T05:00:00+00:00', $window->from->format('c'));
        self::assertSame('UTC', $window->to->getTimezone()->getName());
        self::assertSame(SyncWindow::MAX_DAYS, $window->from->diff($window->to)->days);
        self::assertSame(1, SyncWindow::days($from, 1)->from->diff(SyncWindow::days($from, 1)->to)->days);
        self::assertTrue($window->holds($window->from));
        self::assertFalse($window->holds($window->to));
        self::assertFalse($window->holds($window->from->modify('-1 second')));

        foreach ([0, SyncWindow::MAX_DAYS + 1] as $days) {
            try {
                SyncWindow::days($from, $days);
                self::fail('Out of range.');
            } catch (InvalidSync $invalidSync) {
                self::assertSame('A window is 1 to 400 days.', $invalidSync->getMessage());
            }
        }

        try {
            new SyncWindow($from, $from);
            self::fail('Empty.');
        } catch (InvalidSync $invalidSync) {
            self::assertSame('A window ends after it starts.', $invalidSync->getMessage());
        }

        $this->expectExceptionObject(InvalidSync::because('A window is 400 days at most.'));
        new SyncWindow($from, $from->modify('+400 days +1 second'));
    }

    public function testPeriodsAreCutSortedAndJoined(): void
    {
        $window = new SyncWindow(new DateTimeImmutable('2026-11-10T08:00:00Z'), new DateTimeImmutable('2026-11-10T20:00:00Z'));
        $merged = BusyPeriods::merge([
            $this->period('15:00', '16:00', true),
            $this->period('07:00', '09:00'),
            $this->period('09:00', '10:00', true),
            $this->period('12:00', '13:00', true),
            $this->period('12:30', '12:45', true),
            $this->period('15:30', '17:00'),
            $this->period('19:00', '22:00', true),
            $this->period('21:00', '22:00'),
        ], $window);

        self::assertSame([
            ['08:00', '10:00', false],
            ['12:00', '13:00', true],
            ['15:00', '17:00', false],
            ['19:00', '20:00', true],
        ], array_map(static fn (BusyPeriod $period): array => [$period->start->format('H:i'), $period->end->format('H:i'), $period->tentative], $merged));
        self::assertSame([], BusyPeriods::merge([], $window));
    }

    public function testTheMostPeriodsAreMergedAndOneMoreIsRefused(): void
    {
        $window = SyncWindow::days(new DateTimeImmutable('2026-11-01T00:00:00Z'), 30);
        $periods = [];

        for ($i = 0; $i < BusyPeriods::MAX; ++$i) {
            $start = $window->from->modify(\sprintf('+%d minutes', 3 * $i));
            $periods[] = new BusyPeriod($start, $start->modify('+1 minute'));
        }

        self::assertCount(BusyPeriods::MAX, BusyPeriods::merge([...$periods, $this->period('2026-12-05 00:00', '2026-12-05 01:00')], $window));

        $this->expectExceptionObject(InvalidCalendarData::because('A calendar gives 10000 busy periods in a window at most.'));
        BusyPeriods::merge([...$periods, $periods[0]], $window);
    }

    public function testAConnectionNamesItsAdapterAndItsCalendar(): void
    {
        $connection = new CalendarConnection(str_repeat('a', 128), 'caldav', 'https://dav.example.com/cal/', new DateTimeZone('America/Toronto'));

        self::assertSame('America/Toronto', $connection->timezone->getName());
        self::assertSame('UTC', new CalendarConnection('1', 'm_y-2', str_repeat('c', 2048))->timezone->getName());
        self::assertSame('a2345678901234567890123456789012', new CalendarConnection('1', 'a2345678901234567890123456789012', 'c')->provider);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function badConnections(): iterable
    {
        yield 'empty id' => ['', 'ics', 'c', 'The identifier of a connection is 1 to 128 bytes.'];
        yield 'long id' => [str_repeat('a', 129), 'ics', 'c', 'The identifier of a connection is 1 to 128 bytes.'];
        yield 'uppercase adapter' => ['1', 'Ics', 'c', 'The name of an adapter is a lowercase word of 32 characters at most.'];
        yield 'long adapter' => ['1', str_repeat('a', 33), 'c', 'The name of an adapter is a lowercase word of 32 characters at most.'];
        yield 'adapter with a line' => ['1', "ics\n", 'c', 'The name of an adapter is a lowercase word of 32 characters at most.'];
        yield 'digit first' => ['1', '1cs', 'c', 'The name of an adapter is a lowercase word of 32 characters at most.'];
        yield 'empty calendar' => ['1', 'ics', '', 'The calendar of a connection is 1 to 2048 bytes.'];
        yield 'long calendar' => ['1', 'ics', str_repeat('c', 2049), 'The calendar of a connection is 1 to 2048 bytes.'];
        yield 'control in id' => ["a\x7F", 'ics', 'c', 'A connection holds no control character.'];
        yield 'control in calendar' => ['a', 'ics', "c\x00", 'A connection holds no control character.'];
    }

    #[DataProvider('badConnections')]
    public function testABadConnectionIsRefused(string $id, string $provider, string $calendar, string $message): void
    {
        $this->expectExceptionObject(InvalidSync::because($message));

        new CalendarConnection($id, $provider, $calendar);
    }

    public function testCredentialsGiveTheirHttpOptionsAndNeverTheirSecretElsewhere(): void
    {
        $bearer = Credentials::bearer('ya29.token');
        $basic = Credentials::basic('jane', 'app pass word');

        self::assertSame(['auth_bearer' => 'ya29.token'], $bearer->httpOptions());
        self::assertSame(['auth_basic' => ['jane', 'app pass word']], $basic->httpOptions());
        self::assertSame([], Credentials::none()->httpOptions());
        self::assertSame(['bearer', 'basic', 'none'], [$bearer->kind, $basic->kind, Credentials::none()->kind]);
        self::assertSame(['kind' => 'bearer'], $bearer->__debugInfo());
        self::assertStringNotContainsString('ya29', print_r($bearer, true));

        try {
            serialize($basic);
            self::fail('Serialised.');
        } catch (LogicException $logicException) {
            self::assertSame('Credentials are never serialised: a message carries the identifier of its connection.', $logicException->getMessage());
        }

        $this->expectExceptionObject(new LogicException('Credentials are never serialised: a message carries the identifier of its connection.'));
        new ReflectionClass(Credentials::class)->newInstanceWithoutConstructor()->__unserialize(['kind' => 'none']);
    }

    /**
     * @return iterable<string, array{callable(): Credentials, string}>
     */
    public static function badCredentials(): iterable
    {
        $token = 'An access token is a non-empty string without spaces or control characters.';
        $user = 'A user name is non-empty, without a colon; neither it nor the password holds a control character.';

        yield 'empty token' => [static fn (): Credentials => Credentials::bearer(''), $token];
        yield 'token with a space' => [static fn (): Credentials => Credentials::bearer('a b'), $token];
        yield 'token with a delete' => [static fn (): Credentials => Credentials::bearer("a\x7F"), $token];
        yield 'empty user' => [static fn (): Credentials => Credentials::basic('', 'p'), $user];
        yield 'user with a colon' => [static fn (): Credentials => Credentials::basic('a:b', 'p'), $user];
        yield 'password with a line' => [static fn (): Credentials => Credentials::basic('a', "p\n"), $user];
        yield 'user with a delete' => [static fn (): Credentials => Credentials::basic("a\x7F", 'p'), $user];
    }

    /**
     * @param callable(): Credentials $make
     */
    #[DataProvider('badCredentials')]
    public function testBadCredentialsAreRefused(callable $make, string $message): void
    {
        $this->expectExceptionObject(InvalidSync::because($message));

        $make();
    }

    public function testAnOutboundEventKeepsWhatItShows(): void
    {
        $start = new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto'));
        $event = new OutboundEvent(str_repeat('u', 255), str_repeat('t', 255), $start, new DateTimeImmutable('2026-11-11T03:00:00Z'), description: str_repeat('d', 8000), location: str_repeat('l', 255), sequence: 2);

        self::assertSame('America/Toronto', $event->end->getTimezone()->getName());
        self::assertSame('2026-11-10 22:00', $event->end->format('Y-m-d H:i'));
        self::assertSame(2, $event->sequence);
        self::assertTrue(new OutboundEvent('u', 'Day', new DateTimeImmutable('2026-11-10'), new DateTimeImmutable('2026-11-11'), true)->allDay);
    }

    /**
     * @return iterable<string, array{callable(): OutboundEvent, string}>
     */
    public static function badEvents(): iterable
    {
        $start = new DateTimeImmutable('2026-11-10T12:00:00Z');
        $end = new DateTimeImmutable('2026-11-10T13:00:00Z');
        $uid = 'The identifier of an event is 1 to 255 visible ASCII characters.';
        $title = 'The title of an event is 1 to 255 characters.';
        $texts = 'The description of an event is 8000 characters at most, its place 255.';
        $midnight = 'A whole-day event starts and ends at midnight.';

        yield 'empty uid' => [static fn (): OutboundEvent => new OutboundEvent('', 'T', $start, $end), $uid];
        yield 'long uid' => [static fn (): OutboundEvent => new OutboundEvent(str_repeat('u', 256), 'T', $start, $end), $uid];
        yield 'uid with a space' => [static fn (): OutboundEvent => new OutboundEvent('a b', 'T', $start, $end), $uid];
        yield 'blank title' => [static fn (): OutboundEvent => new OutboundEvent('u', '  ', $start, $end), $title];
        yield 'long title' => [static fn (): OutboundEvent => new OutboundEvent('u', str_repeat('é', 256), $start, $end), $title];
        yield 'long description' => [static fn (): OutboundEvent => new OutboundEvent('u', 'T', $start, $end, description: str_repeat('é', 8001)), $texts];
        yield 'long place' => [static fn (): OutboundEvent => new OutboundEvent('u', 'T', $start, $end, location: str_repeat('é', 256)), $texts];
        yield 'no length' => [static fn (): OutboundEvent => new OutboundEvent('u', 'T', $start, $start), 'An event ends after it starts.'];
        yield 'day at noon' => [static fn (): OutboundEvent => new OutboundEvent('u', 'T', $start, $end->modify('+1 day'), true), $midnight];
        yield 'day ending at noon' => [static fn (): OutboundEvent => new OutboundEvent('u', 'T', $start->setTime(0, 0), $end, true), $midnight];
        yield 'negative sequence' => [static fn (): OutboundEvent => new OutboundEvent('u', 'T', $start, $end, sequence: -1), 'The sequence of an event is 0 or more.'];
    }

    /**
     * @param callable(): OutboundEvent $make
     */
    #[DataProvider('badEvents')]
    public function testABadEventIsRefused(callable $make, string $message): void
    {
        $this->expectExceptionObject(InvalidSync::because($message));

        $make();
    }

    public function testFailuresSayTheAdapterAndTheStatusOnly(): void
    {
        $unauthorized = ProviderFailed::unauthorized('google', 401);

        self::assertSame('The calendar service "google" refused the credentials (HTTP 401).', $unauthorized->getMessage());
        self::assertSame(['google', 401], [$unauthorized->provider, $unauthorized->status]);
        self::assertTrue($unauthorized->isUnauthorized());
        self::assertTrue(ProviderFailed::unauthorized('google', 403)->isUnauthorized());
        self::assertFalse(ProviderFailed::status('caldav', 500)->isUnauthorized());
        self::assertSame('The calendar service "caldav" answered HTTP 500.', ProviderFailed::status('caldav', 500)->getMessage());
        self::assertNull(ProviderFailed::unreachable('ics')->status);
        self::assertNull(ProviderFailed::unreachable('ics')->getPrevious());
        self::assertSame('The calendar service "ics" could not be read.', ProviderFailed::unreachable('ics')->getMessage());
        self::assertSame('The calendar service "google" refused the request: notFound.', ProviderFailed::refused('google', 'notFound')->getMessage());
        self::assertSame('The calendar service "google" refused the request: unknown.', ProviderFailed::refused('google', 'not found <b>')->getMessage());
        self::assertSame('The calendar service "google" refused the request: unknown.', ProviderFailed::refused('google', '')->getMessage());
        self::assertSame('The calendar service "google" refused the request: unknown.', ProviderFailed::refused('google', str_repeat('a', 65))->getMessage());
        self::assertFalse(ProviderFailed::refused('google', 'x')->isUnauthorized());
        self::assertSame('No calendar connection "a'.str_repeat('é', 99).'".', UnknownConnection::id('a'.str_repeat('é', 100))->getMessage());
        self::assertSame('The calendar service "google" refused the request: unknown.', ProviderFailed::refused('google', "notFound\n")->getMessage());
        self::assertSame('No calendar adapter "x" to write events to.', UnknownConnection::provider('x', 'to write events to')->getMessage());
        self::assertSame('No calendar adapter "'.str_repeat('é', 100).'" r.', UnknownConnection::provider(str_repeat('é', 101), 'r')->getMessage());
    }

    public function testAPeriodOutsideTheWindowDoesNotStopTheOnesAfterIt(): void
    {
        $window = new SyncWindow(new DateTimeImmutable('2026-11-10 00:00', new DateTimeZone('UTC')), new DateTimeImmutable('2026-11-11 00:00', new DateTimeZone('UTC')));
        $merged = BusyPeriods::merge([$this->period('2026-11-20 10:00', '2026-11-20 11:00'), $this->period('10:00', '11:00')], $window);

        self::assertSame(['10:00'], array_map(static fn (BusyPeriod $period): string => $period->start->format('H:i'), $merged));
    }

    public function testAnEventHasNoSequenceByDefaultAndMeasuresItsTextsInCharacters(): void
    {
        $start = new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('2026-11-10 20:00', new DateTimeZone('UTC'));

        self::assertSame(0, new OutboundEvent('u', 'T', $start, $end)->sequence);
        self::assertSame(200, mb_strlen(new OutboundEvent('u', str_repeat('é', 200), $start, $end)->title));
        self::assertSame(5000, mb_strlen((string) new OutboundEvent('u', 'T', $start, $end, false, str_repeat('é', 5000))->description));
        self::assertSame(200, mb_strlen((string) new OutboundEvent('u', 'T', $start, $end, false, null, str_repeat('é', 200))->location));

        foreach (["u\n", str_repeat('é', 3)] as $uid) {
            try {
                new OutboundEvent($uid, 'T', $start, $end);
                self::fail('Built.');
            } catch (InvalidSync $invalidSync) {
                self::assertSame('The identifier of an event is 1 to 255 visible ASCII characters.', $invalidSync->getMessage());
            }
        }
    }

    private function period(string $start, string $end, bool $tentative = false): BusyPeriod
    {
        $day = str_contains($start, ' ') ? '' : '2026-11-10 ';

        return new BusyPeriod(new DateTimeImmutable($day.$start, new DateTimeZone('UTC')), new DateTimeImmutable($day.$end, new DateTimeZone('UTC')), $tentative);
    }
}
