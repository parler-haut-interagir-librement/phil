<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\Adapter;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarSync\Adapter\CalDav;
use Phil\CalendarSync\Adapter\HttpCall;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\ICalendar\ICalendarReader;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(CalDav::class)]
#[CoversClass(HttpCall::class)]
final class CalDavTest extends TestCase
{
    private const array RESOLVE = ['dav.example.com' => '93.184.215.14'];

    public function testBusyTimesAreAskedByAFreeBusyReport(): void
    {
        $recorder = new Recorder(new MockResponse("BEGIN:VCALENDAR\r\nBEGIN:VFREEBUSY\r\nFREEBUSY:20261110T190000Z/20261110T200000Z\r\nEND:VFREEBUSY\r\nEND:VCALENDAR\r\n"));
        $periods = [...$this->calDav($recorder)->busy($this->connection(), Credentials::basic('jane', 'app-password'), $this->window())];

        self::assertSame('caldav', $this->calDav($recorder)->name());
        self::assertSame([['2026-11-10T19:00:00Z', '2026-11-10T20:00:00Z']], $this->times($periods));
        self::assertSame('REPORT', $recorder->requests[0]['method']);
        self::assertSame('https://dav.example.com/calendars/jane/personal/', $recorder->requests[0]['url']);
        self::assertTrue($recorder->sent(0, 'Authorization: Basic '.base64_encode('jane:app-password')));
        self::assertTrue($recorder->sent(0, 'Depth: 1'));
        self::assertTrue($recorder->sent(0, 'Content-Type: application/xml; charset=utf-8'));
        self::assertSame('<?xml version="1.0" encoding="utf-8"?><C:free-busy-query xmlns:C="urn:ietf:params:xml:ns:caldav"><C:time-range start="20261101T000000Z" end="20261201T000000Z"/></C:free-busy-query>', $recorder->requests[0]['body']);
    }

    public function testAServerWithoutTheReportIsAskedForTheTimesOfItsEvents(): void
    {
        $recorder = new Recorder();

        foreach ([400, 404, 405, 415, 422, 501] as $status) {
            $recorder = new Recorder(new MockResponse('', ['http_code' => $status]), new MockResponse($this->multistatus("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTART:20261110T190000Z\r\nDTEND:20261110T200000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n", '   ', "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:b\r\nDTSTART;TZID=America/Toronto:20261111T090000\r\nDURATION:PT1H\r\nSTATUS:TENTATIVE\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"), ['http_code' => 207]));
            $periods = [...$this->calDav($recorder)->busy($this->connection(), Credentials::bearer('t'), $this->window())];

            self::assertSame([['2026-11-10T19:00:00Z', '2026-11-10T20:00:00Z'], ['2026-11-11T14:00:00Z', '2026-11-11T15:00:00Z']], $this->times($periods), (string) $status);
            self::assertTrue($periods[1]->tentative);
        }

        self::assertSame('REPORT', $recorder->requests[1]['method']);
        self::assertTrue($recorder->sent(1, 'Depth: 1'));
        self::assertTrue($recorder->sent(1, 'Authorization: Bearer t'));
        self::assertSame(
            '<?xml version="1.0" encoding="utf-8"?><C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">'
            .'<D:prop><C:calendar-data><C:comp name="VCALENDAR"><C:comp name="VEVENT"><C:prop name="UID"/><C:prop name="DTSTART"/>'
            .'<C:prop name="DTEND"/><C:prop name="DURATION"/><C:prop name="RRULE"/><C:prop name="RDATE"/><C:prop name="EXDATE"/>'
            .'<C:prop name="RECURRENCE-ID"/><C:prop name="STATUS"/><C:prop name="TRANSP"/></C:comp></C:comp>'
            .'<C:expand start="20261101T000000Z" end="20261201T000000Z"/></C:calendar-data></D:prop>'
            .'<C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT"><C:time-range start="20261101T000000Z" end="20261201T000000Z"/>'
            .'</C:comp-filter></C:comp-filter></C:filter></C:calendar-query>',
            $recorder->requests[1]['body'],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badMultistatus(): iterable
    {
        yield 'empty' => [''];
        yield 'not xml' => ['<multistatus'];
        yield 'doctype' => ['<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x "y">]><d:multistatus xmlns:d="DAV:"/>'];
        yield 'doctype in any case' => ['<?xml version="1.0"?><!doctype d><d:multistatus xmlns:d="DAV:"/>'];
    }

    #[DataProvider('badMultistatus')]
    public function testAMultistatusThatCannotBeReadIsRefused(string $body): void
    {
        $recorder = new Recorder(new MockResponse('', ['http_code' => 501]), new MockResponse($body, ['http_code' => 207]));
        $this->expectExceptionObject(InvalidCalendarData::because('A CalDAV answer is an XML multistatus without a document type.'));

        [...$this->calDav($recorder)->busy($this->connection(), Credentials::none(), $this->window())];
    }

    public function testTheRepeatedEventsOfAllTheCalendarDataShareOneBudget(): void
    {
        $repeated = "BEGIN:VCALENDAR\r\n".str_repeat("BEGIN:VEVENT\r\nDTSTART:20261101T000000Z\r\nRRULE:FREQ=YEARLY;COUNT=1\r\nEND:VEVENT\r\n", ICalendarReader::MAX_RULES / 2)."END:VCALENDAR\r\n";
        $recorder = new Recorder(new MockResponse('', ['http_code' => 501]), new MockResponse($this->multistatus($repeated, $repeated, $repeated), ['http_code' => 207]));
        $this->expectExceptionObject(InvalidCalendarData::because('A calendar repeats 1000 events at most.'));

        [...$this->calDav($recorder)->busy($this->connection(), Credentials::none(), $this->window())];
    }

    public function testAnEventIsPutUnderAHashedNameAndDeleted(): void
    {
        $recorder = new Recorder(new MockResponse('', ['http_code' => 201]), new MockResponse('', ['http_code' => 204]), new MockResponse('', ['http_code' => 404]), new MockResponse('', ['http_code' => 410]));
        $calDav = $this->calDav($recorder);
        $event = new OutboundEvent('../../evil', 'Session', new DateTimeImmutable('2026-11-10T19:00:00Z'), new DateTimeImmutable('2026-11-10T20:00:00Z'));
        $calDav->put(new CalendarConnection('c', 'caldav', 'https://dav.example.com/calendars/jane/personal'), Credentials::bearer('t'), $event);
        $calDav->remove($this->connection(), Credentials::bearer('t'), '../../evil');
        $calDav->remove($this->connection(), Credentials::bearer('t'), '../../evil');
        $calDav->remove($this->connection(), Credentials::bearer('t'), '../../evil');

        $resource = 'https://dav.example.com/calendars/jane/personal/'.hash('sha256', '../../evil').'.ics';

        self::assertSame(['PUT', 'DELETE', 'DELETE', 'DELETE'], array_column($recorder->requests, 'method'));
        self::assertSame([$resource, $resource], [$recorder->requests[0]['url'], $recorder->requests[1]['url']]);
        self::assertTrue($recorder->sent(0, 'Content-Type: text/calendar; charset=utf-8'));
        self::assertStringContainsString("UID:../../evil\r\nDTSTAMP:20261101T120000Z\r\nDTSTART:20261110T190000Z", $recorder->requests[0]['body']);
    }

    public function testAnAnswerToAWriteThatIsAnErrorIsAFailure(): void
    {
        $this->expectExceptionObject(ProviderFailed::status('caldav', 412));

        $this->calDav(new Recorder(new MockResponse('', ['http_code' => 412])))->put($this->connection(), Credentials::none(), new OutboundEvent('u', 'T', new DateTimeImmutable('2026-11-10T19:00:00Z'), new DateTimeImmutable('2026-11-10T20:00:00Z')));
    }

    public function testARefusedReportIsAFailure(): void
    {
        $this->expectExceptionObject(ProviderFailed::unauthorized('caldav', 401));

        [...$this->calDav(new Recorder(new MockResponse('', ['http_code' => 401])))->busy($this->connection(), Credentials::none(), $this->window())];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCollections(): iterable
    {
        yield 'plain http' => ['http://dav.example.com/cal/'];
        yield 'a query' => ['https://dav.example.com/cal/?a=b'];
        yield 'a fragment' => ['https://dav.example.com/cal/#a'];
        yield 'an ip' => ['https://10.0.0.1/cal/'];
        yield 'not an address' => ['calendar'];
    }

    #[DataProvider('badCollections')]
    public function testACollectionIsAnHttpsAddress(string $calendar): void
    {
        $recorder = new Recorder();
        $this->expectExceptionObject(InvalidSync::because('The calendar of a "caldav" connection is the https address of a collection.'));

        try {
            [...$this->calDav($recorder)->busy(new CalendarConnection('c', 'caldav', $calendar), Credentials::none(), $this->window())];
        } finally {
            self::assertSame([], $recorder->requests);
        }
    }

    public function testAHostOnAPrivateNetworkIsRefused(): void
    {
        $recorder = new Recorder(new MockResponse('BEGIN:VCALENDAR'));
        $calDav = new CalDav($recorder->client, ['dav.example.com' => '10.0.0.1']);
        $this->expectExceptionObject(ProviderFailed::unreachable('caldav'));

        try {
            [...$calDav->busy($this->connection(), Credentials::basic('jane', 'secret'), $this->window())];
        } finally {
            self::assertSame([], $recorder->requests);
        }
    }

    public function testARedirectIsNotFollowed(): void
    {
        $recorder = new Recorder(new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://other.example.com/']]));
        $this->expectExceptionObject(ProviderFailed::status('caldav', 301));

        try {
            [...$this->calDav($recorder)->busy($this->connection(), Credentials::basic('jane', 'secret'), $this->window())];
        } finally {
            self::assertCount(1, $recorder->requests);
        }
    }

    public function testAnAnswerTooLargeIsRefused(): void
    {
        $recorder = new Recorder(new MockResponse([str_repeat('a', HttpCall::MAX_BYTES), 'b']));
        $this->expectExceptionObject(InvalidCalendarData::because('An answer of a calendar service is 10485760 bytes at most.'));

        [...$this->calDav($recorder)->busy($this->connection(), Credentials::none(), $this->window())];
    }

    public function testAnAnswerOfTheMostBytesIsRead(): void
    {
        $calendar = "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n";
        $recorder = new Recorder(new MockResponse([$calendar, str_repeat("\r\n", (HttpCall::MAX_BYTES - \strlen($calendar)) / 2)]));

        self::assertSame([], [...$this->calDav($recorder)->busy($this->connection(), Credentials::none(), $this->window())]);
    }

    public function testATransportErrorIsAFailureThatHidesTheAddress(): void
    {
        $recorder = new Recorder(new MockResponse('', ['error' => 'Could not resolve https://dav.example.com/secret']));

        try {
            [...$this->calDav($recorder)->busy($this->connection(), Credentials::none(), $this->window())];
            self::fail('Read.');
        } catch (ProviderFailed $providerFailed) {
            self::assertSame('The calendar service "caldav" could not be read.', $providerFailed->getMessage());
            self::assertNull($providerFailed->getPrevious());
        }
    }

    private function calDav(Recorder $recorder): CalDav
    {
        return new CalDav($recorder->client, self::RESOLVE, new MockClock(new DateTimeImmutable('2026-11-01T12:00:00Z')));
    }

    private function connection(): CalendarConnection
    {
        return new CalendarConnection('c1', 'caldav', 'https://dav.example.com/calendars/jane/personal/', new DateTimeZone('America/Toronto'));
    }

    private function window(): SyncWindow
    {
        return new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z'));
    }

    private function multistatus(string ...$calendars): string
    {
        $responses = array_map(static fn (string $calendar): string => '<d:response><d:href>/x.ics</d:href><d:propstat><d:prop><c:calendar-data>'.htmlspecialchars($calendar, \ENT_XML1 | \ENT_QUOTES).'</c:calendar-data></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>', $calendars);

        return '<?xml version="1.0" encoding="utf-8"?><d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'.implode('', $responses).'<d:response><d:href>/y</d:href><x:calendar-data xmlns:x="urn:other">BEGIN:VCALENDAR</x:calendar-data></d:response></d:multistatus>';
    }

    /**
     * @param array<BusyPeriod> $periods
     *
     * @return array<array{string, string}>
     */
    private function times(array $periods): array
    {
        return array_map(static fn (BusyPeriod $period): array => [$period->start->format('Y-m-d\TH:i:s\Z'), $period->end->format('Y-m-d\TH:i:s\Z')], $periods);
    }
}
