<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\Adapter;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarSync\Adapter\HttpCall;
use Phil\CalendarSync\Adapter\Json;
use Phil\CalendarSync\Adapter\MicrosoftGraph;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(MicrosoftGraph::class)]
#[CoversClass(HttpCall::class)]
#[CoversClass(Json::class)]
final class MicrosoftGraphTest extends TestCase
{
    public function testBusyTimesAreReadFromTheCalendarViewPageByPage(): void
    {
        $recorder = new Recorder(
            new MockResponse((string) json_encode(['value' => [
                $this->item('2026-11-10T19:00:00.0000000', '2026-11-10T20:00:00.0000000', 'busy'),
                $this->item('2026-11-10T21:00:00', '2026-11-10T22:00:00', 'free'),
                $this->item('2026-11-10T21:00:00', '2026-11-10T22:00:00', 'workingElsewhere'),
                $this->item('2026-11-10T21:00:00', '2026-11-10T22:00:00', 'busy', true),
                $this->item('2026-11-11T08:00:00', '2026-11-11T09:00:00', 'tentative'),
                $this->item('2026-11-11T08:00:00', '2026-11-11T08:00:00', 'busy'),
            ], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/calendars/AAA/calendarView?$skip=500'])),
            new MockResponse((string) json_encode(['value' => [$this->item('2026-11-12T08:00:00', '2026-11-12T09:00:00', 'oof'), ['start' => ['dateTime' => '2026-11-13T08:00:00'], 'end' => ['dateTime' => '2026-11-13T09:00:00']]]])),
        );
        $periods = [...new MicrosoftGraph($recorder->client)->busy($this->connection(), Credentials::bearer('eyJ'), $this->window())];

        self::assertSame('microsoft', new MicrosoftGraph($recorder->client)->name());
        self::assertSame([
            ['2026-11-10T19:00:00Z', '2026-11-10T20:00:00Z', false],
            ['2026-11-11T08:00:00Z', '2026-11-11T09:00:00Z', true],
            ['2026-11-12T08:00:00Z', '2026-11-12T09:00:00Z', false],
            ['2026-11-13T08:00:00Z', '2026-11-13T09:00:00Z', false],
        ], array_map(static fn (BusyPeriod $period): array => [$period->start->format('Y-m-d\TH:i:s\Z'), $period->end->format('Y-m-d\TH:i:s\Z'), $period->tentative], $periods));
        self::assertSame('https://graph.microsoft.com/v1.0/me/calendars/AAA%3D/calendarView?startDateTime=2026-11-01T00:00:00Z&endDateTime=2026-12-01T00:00:00Z&$select=start%2Cend%2CshowAs%2CisCancelled&$top=500', $recorder->requests[0]['url']);
        self::assertSame('https://graph.microsoft.com/v1.0/me/calendars/AAA/calendarView?$skip=500', $recorder->requests[1]['url']);
        self::assertTrue($recorder->sent(0, 'Authorization: Bearer eyJ'));
        self::assertTrue($recorder->sent(1, 'Prefer: outlook.timezone="UTC"'));
    }

    public function testThePrimaryCalendarIsTheDefaultOne(): void
    {
        $recorder = new Recorder(new MockResponse('{"value":[]}'));
        [...new MicrosoftGraph($recorder->client)->busy(new CalendarConnection('c', 'microsoft', 'primary'), Credentials::bearer('t'), $this->window())];

        self::assertStringStartsWith('https://graph.microsoft.com/v1.0/me/calendar/calendarView?', $recorder->requests[0]['url']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badAnswers(): iterable
    {
        yield 'an event without times' => ['{"value":[{"showAs":"busy"}]}', 'An event of Microsoft Graph has a start and an end.'];
        yield 'an event not an object' => ['{"value":["x"]}', 'An event of Microsoft Graph has a start and an end.'];
        yield 'an event with a start only' => ['{"value":[{"start":{"dateTime":"2026-11-10T19:00:00"}}]}', 'An event of Microsoft Graph has a start and an end.'];
        yield 'a next page elsewhere' => ['{"value":[],"@odata.nextLink":"https://evil.example.com/next"}', 'The next page of Microsoft Graph is on graph.microsoft.com.'];
        yield 'a next page in plain http' => ['{"value":[],"@odata.nextLink":"http://graph.microsoft.com/next"}', 'The next page of Microsoft Graph is on graph.microsoft.com.'];
        yield 'a next page that is no address' => ['{"value":[],"@odata.nextLink":"next"}', 'The next page of Microsoft Graph is on graph.microsoft.com.'];
        yield 'a next page that is no text' => ['{"value":[],"@odata.nextLink":7}', 'The next page of Microsoft Graph is on graph.microsoft.com.'];
    }

    #[DataProvider('badAnswers')]
    public function testAnAnswerThatCannotBeReadIsRefused(string $body, string $message): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because($message));

        [...new MicrosoftGraph(new Recorder(new MockResponse($body))->client)->busy($this->connection(), Credentials::bearer('t'), $this->window())];
    }

    public function testPagesAreBounded(): void
    {
        $page = new MockResponse('{"value":[],"@odata.nextLink":"https://graph.microsoft.com/v1.0/next"}');
        $pages = array_map(static fn (): MockResponse => clone $page, range(1, MicrosoftGraph::MAX_PAGES + 1));
        $recorder = new Recorder(...$pages);

        try {
            [...new MicrosoftGraph($recorder->client)->busy($this->connection(), Credentials::bearer('t'), $this->window())];
            self::fail('Read.');
        } catch (InvalidCalendarData $invalidCalendarData) {
            self::assertSame('A calendar of Microsoft Graph is read in 20 pages at most.', $invalidCalendarData->getMessage());
            self::assertCount(MicrosoftGraph::MAX_PAGES, $recorder->requests);
        }

        $last = [...array_map(static fn (): MockResponse => clone $page, range(1, MicrosoftGraph::MAX_PAGES - 1)), new MockResponse('{"value":[]}')];
        self::assertSame([], [...new MicrosoftGraph(new Recorder(...$last)->client)->busy($this->connection(), Credentials::bearer('t'), $this->window())]);
    }

    public function testANewEventIsCreatedWithItsIdentifierInAnExtendedProperty(): void
    {
        $recorder = new Recorder(new MockResponse('{"value":[]}'), new MockResponse('{}', ['http_code' => 201]));
        $event = new OutboundEvent("it's-1", 'Session', new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-10 22:00', new DateTimeZone('America/Toronto')), false, 'Notes', 'Room 1', Url::fromString('https://example.com/b/1'));
        new MicrosoftGraph($recorder->client)->put($this->connection(), Credentials::bearer('t'), $event);

        self::assertSame(['GET', 'POST'], array_column($recorder->requests, 'method'));
        parse_str((string) parse_url($recorder->requests[0]['url'], \PHP_URL_QUERY), $query);
        self::assertSame(['$filter' => "singleValueExtendedProperties/Any(ep: ep/id eq '".MicrosoftGraph::PROPERTY."' and ep/value eq 'it''s-1')", '$select' => 'id', '$top' => '1'], $query);
        self::assertStringStartsWith('https://graph.microsoft.com/v1.0/me/calendars/AAA%3D/events?', $recorder->requests[0]['url']);
        self::assertSame('https://graph.microsoft.com/v1.0/me/calendars/AAA%3D/events', $recorder->requests[1]['url']);
        self::assertSame([
            'subject' => 'Session',
            'body' => ['contentType' => 'text', 'content' => "Notes\n\nhttps://example.com/b/1"],
            'start' => ['dateTime' => '2026-11-11T00:00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2026-11-11T03:00:00', 'timeZone' => 'UTC'],
            'isAllDay' => false,
            'location' => ['displayName' => 'Room 1'],
            'singleValueExtendedProperties' => [['id' => MicrosoftGraph::PROPERTY, 'value' => "it's-1"]],
        ], json_decode($recorder->requests[1]['body'], true));
    }

    public function testAnEventFoundIsUpdated(): void
    {
        $recorder = new Recorder(new MockResponse('{"value":[{"id":"EV/1"}]}'), new MockResponse('{}'));
        $event = new OutboundEvent('d', 'Day', new DateTimeImmutable('2026-11-10', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-11', new DateTimeZone('America/Toronto')), true);
        new MicrosoftGraph($recorder->client)->put($this->connection(), Credentials::bearer('t'), $event);

        self::assertSame(['GET', 'PATCH'], array_column($recorder->requests, 'method'));
        self::assertSame('https://graph.microsoft.com/v1.0/me/events/EV%2F1', $recorder->requests[1]['url']);
        self::assertSame([
            'subject' => 'Day',
            'body' => ['contentType' => 'text', 'content' => ''],
            'start' => ['dateTime' => '2026-11-10T00:00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2026-11-11T00:00:00', 'timeZone' => 'UTC'],
            'isAllDay' => true,
        ], json_decode($recorder->requests[1]['body'], true));
    }

    public function testAnEventFoundIsDeletedAndOneNotFoundIsLeftAlone(): void
    {
        $recorder = new Recorder(new MockResponse('{"value":[{"id":"EV1"}]}'), new MockResponse('', ['http_code' => 404]), new MockResponse('{"value":[{"id":""}]}'), new MockResponse('{"value":[{"id":7}]}'), new MockResponse('{"value":["x"]}'), new MockResponse('{}'));
        $graph = new MicrosoftGraph($recorder->client);

        for ($i = 0; $i < 5; ++$i) {
            $graph->remove($this->connection(), Credentials::bearer('t'), 'u');
        }

        self::assertSame(['GET', 'DELETE', 'GET', 'GET', 'GET', 'GET'], array_column($recorder->requests, 'method'));
        self::assertSame('https://graph.microsoft.com/v1.0/me/events/EV1', $recorder->requests[1]['url']);
    }

    public function testAnEventAlreadyGoneIsNoErrorWhateverTheStatusOfItsRemoval(): void
    {
        $recorder = new Recorder(new MockResponse('{"value":[{"id":"EV1"}]}'), new MockResponse('', ['http_code' => 410]));
        new MicrosoftGraph($recorder->client)->remove($this->connection(), Credentials::bearer('t'), 'u');

        self::assertSame(['GET', 'DELETE'], array_column($recorder->requests, 'method'));
    }

    public function testAnEventWithANoteAndNoAddressOrAnAddressAndNoNoteIsTrimmed(): void
    {
        $recorder = new Recorder(new MockResponse('{"value":[]}'), new MockResponse('{}'), new MockResponse('{"value":[]}'), new MockResponse('{}'));
        $zone = new DateTimeZone('UTC');
        $graph = new MicrosoftGraph($recorder->client);
        $graph->put($this->connection(), Credentials::bearer('t'), new OutboundEvent('a', 'A', new DateTimeImmutable('2026-11-10 19:00', $zone), new DateTimeImmutable('2026-11-10 20:00', $zone), false, 'Notes'));
        $graph->put($this->connection(), Credentials::bearer('t'), new OutboundEvent('b', 'B', new DateTimeImmutable('2026-11-10 19:00', $zone), new DateTimeImmutable('2026-11-10 20:00', $zone), false, null, null, Url::fromString('https://example.com/b/2')));

        foreach ([[1, 'Notes'], [3, 'https://example.com/b/2']] as [$request, $content]) {
            $resource = json_decode($recorder->requests[$request]['body'], true);

            self::assertIsArray($resource);
            self::assertSame(['contentType' => 'text', 'content' => $content], $resource['body']);
        }
    }

    public function testAnEventWithoutAStartOrAnEndIsRefused(): void
    {
        foreach (['{"value":[{"start":{"dateTime":"2026-11-10T19:00:00"},"end":"x"}]}', '{"value":[{"start":"x","end":{"dateTime":"2026-11-10T19:00:00"}}]}'] as $body) {
            try {
                [...new MicrosoftGraph(new Recorder(new MockResponse($body))->client)->busy($this->connection(), Credentials::bearer('t'), $this->window())];
                self::fail('Read.');
            } catch (InvalidCalendarData $invalidCalendarData) {
                self::assertSame('An event of Microsoft Graph has a start and an end.', $invalidCalendarData->getMessage());
            }
        }
    }

    private function connection(): CalendarConnection
    {
        return new CalendarConnection('c1', 'microsoft', 'AAA=');
    }

    private function window(): SyncWindow
    {
        return new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z'));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $start, string $end, string $showAs, bool $cancelled = false): array
    {
        return ['start' => ['dateTime' => $start, 'timeZone' => 'UTC'], 'end' => ['dateTime' => $end, 'timeZone' => 'UTC'], 'showAs' => $showAs, 'isCancelled' => $cancelled, 'subject' => 'Never read'];
    }
}
