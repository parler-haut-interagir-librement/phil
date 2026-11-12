<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\Adapter;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarSync\Adapter\GoogleCalendar;
use Phil\CalendarSync\Adapter\HttpCall;
use Phil\CalendarSync\Adapter\Json;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GoogleCalendar::class)]
#[CoversClass(HttpCall::class)]
#[CoversClass(Json::class)]
final class GoogleCalendarTest extends TestCase
{
    public function testBusyTimesAreAskedByFreeBusyWithTheToken(): void
    {
        $recorder = new Recorder(new MockResponse((string) json_encode(['calendars' => ['team@group.calendar.google.com' => ['busy' => [
            ['start' => '2026-11-10T19:00:00Z', 'end' => '2026-11-10T20:00:00Z'],
            ['start' => '2026-11-11T09:00:00.000-05:00', 'end' => '2026-11-11T10:00:00-05:00'],
        ]]]])));
        $periods = [...new GoogleCalendar($recorder->client)->busy($this->connection(), Credentials::bearer('ya29.a'), $this->window())];

        self::assertSame('google', new GoogleCalendar($recorder->client)->name());
        self::assertSame([['2026-11-10T19:00:00Z', '2026-11-10T20:00:00Z'], ['2026-11-11T14:00:00Z', '2026-11-11T15:00:00Z']], $this->times($periods));
        self::assertSame('POST', $recorder->requests[0]['method']);
        self::assertSame('https://www.googleapis.com/calendar/v3/freeBusy', $recorder->requests[0]['url']);
        self::assertTrue($recorder->sent(0, 'Authorization: Bearer ya29.a'));
        self::assertSame(['timeMin' => '2026-11-01T00:00:00Z', 'timeMax' => '2026-12-01T00:00:00Z', 'items' => [['id' => 'team@group.calendar.google.com']]], json_decode($recorder->requests[0]['body'], true));
    }

    public function testTheOnlyCalendarOfTheAnswerIsReadUnderAnotherName(): void
    {
        $recorder = new Recorder(new MockResponse('{"calendars":{"jane@example.com":{"busy":[]}}}'));

        self::assertSame([], [...new GoogleCalendar($recorder->client)->busy(new CalendarConnection('c', 'google', 'primary'), Credentials::bearer('t'), $this->window())]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badAnswers(): iterable
    {
        yield 'not json' => ['<html>', 'An answer of a calendar service is JSON.'];
        yield 'not an object' => ['"busy"', 'An answer of a calendar service is a JSON object.'];
        yield 'too deep' => [str_repeat('[', 65).str_repeat(']', 65), 'An answer of a calendar service is JSON.'];
        yield 'no calendar' => ['{"calendars":{}}', 'A free/busy answer of Google names the calendar asked for.'];
        yield 'no calendars' => ['{"kind":"x"}', 'A free/busy answer of Google names the calendar asked for.'];
        yield 'two others' => ['{"calendars":{"a":{"busy":[]},"b":{"busy":[]}}}', 'A free/busy answer of Google names the calendar asked for.'];
        yield 'not a time' => ['{"calendars":{"team@group.calendar.google.com":{"busy":[{"start":"tomorrow","end":"2026-11-10T20:00:00Z"}]}}}', 'A time of a calendar service is written in RFC 3339.'];
        yield 'no offset' => ['{"calendars":{"team@group.calendar.google.com":{"busy":[{"start":"2026-11-10T19:00:00","end":"2026-11-10T20:00:00Z"}]}}}', 'A time of a calendar service is written in RFC 3339.'];
        yield 'no such day' => ['{"calendars":{"team@group.calendar.google.com":{"busy":[{"start":"2026-02-30T19:00:00Z","end":"2026-11-10T20:00:00Z"}]}}}', 'A time of a calendar service is a moment that exists.'];
        yield 'busy not an object' => ['{"calendars":{"team@group.calendar.google.com":{"busy":["x"]}}}', 'A busy time of Google has a start and an end.'];
    }

    #[DataProvider('badAnswers')]
    public function testAnAnswerThatCannotBeReadIsRefused(string $body, string $message): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because($message));

        [...new GoogleCalendar(new Recorder(new MockResponse($body))->client)->busy($this->connection(), Credentials::bearer('t'), $this->window())];
    }

    public function testAnErrorOfTheCalendarIsAFailure(): void
    {
        foreach ([
            '{"calendars":{"team@group.calendar.google.com":{"errors":[{"domain":"global","reason":"notFound"}]}}}' => 'notFound',
            '{"calendars":{"team@group.calendar.google.com":{"errors":["x"]}}}' => 'unknown',
            '{"calendars":{"team@group.calendar.google.com":{"errors":[{"reason":7}]}}}' => 'unknown',
        ] as $body => $reason) {
            try {
                [...new GoogleCalendar(new Recorder(new MockResponse($body))->client)->busy($this->connection(), Credentials::bearer('t'), $this->window())];
                self::fail('Read.');
            } catch (ProviderFailed $providerFailed) {
                self::assertSame('The calendar service "google" refused the request: '.$reason.'.', $providerFailed->getMessage());
            }
        }
    }

    public function testAnEventIsReplacedUnderItsDerivedIdentifier(): void
    {
        $recorder = new Recorder(new MockResponse('{}'));
        $event = new OutboundEvent('booking-1', 'Session', new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-10 22:00', new DateTimeZone('America/Toronto')), false, 'Notes', 'Room 1', Url::fromString('https://example.com/b/1'), 2);
        new GoogleCalendar($recorder->client)->put($this->connection(), Credentials::bearer('t'), $event);
        $id = hash('sha256', 'booking-1');

        self::assertSame($id, GoogleCalendar::id('booking-1'));
        self::assertCount(1, $recorder->requests);
        self::assertSame('PUT', $recorder->requests[0]['method']);
        self::assertSame('https://www.googleapis.com/calendar/v3/calendars/team%40group.calendar.google.com/events/'.$id, $recorder->requests[0]['url']);
        self::assertSame([
            'id' => $id,
            'summary' => 'Session',
            'start' => ['dateTime' => '2026-11-10T19:00:00-05:00', 'timeZone' => 'America/Toronto'],
            'end' => ['dateTime' => '2026-11-10T22:00:00-05:00', 'timeZone' => 'America/Toronto'],
            'sequence' => 2,
            'description' => 'Notes',
            'location' => 'Room 1',
            'source' => ['title' => 'Session', 'url' => 'https://example.com/b/1'],
        ], json_decode($recorder->requests[0]['body'], true));
    }

    public function testAnEventThatDoesNotExistIsInserted(): void
    {
        $recorder = new Recorder(new MockResponse('{}', ['http_code' => 404]), new MockResponse('{}'));
        $event = new OutboundEvent('d', 'Day', new DateTimeImmutable('2026-11-10'), new DateTimeImmutable('2026-11-11'), true);
        new GoogleCalendar($recorder->client)->put($this->connection(), Credentials::bearer('t'), $event);

        self::assertSame(['PUT', 'POST'], array_column($recorder->requests, 'method'));
        self::assertSame('https://www.googleapis.com/calendar/v3/calendars/team%40group.calendar.google.com/events', $recorder->requests[1]['url']);
        self::assertSame(['id' => hash('sha256', 'd'), 'summary' => 'Day', 'start' => ['date' => '2026-11-10'], 'end' => ['date' => '2026-11-11'], 'sequence' => 0, 'iCalUID' => 'd'], json_decode($recorder->requests[1]['body'], true));
    }

    public function testAnOffsetIsWrittenInUtc(): void
    {
        $recorder = new Recorder(new MockResponse('{}'));
        new GoogleCalendar($recorder->client)->put($this->connection(), Credentials::bearer('t'), new OutboundEvent('u', 'T', new DateTimeImmutable('2026-11-10T19:00:00+02:00'), new DateTimeImmutable('2026-11-10T20:00:00+02:00')));
        $body = json_decode($recorder->requests[0]['body'], true);

        self::assertIsArray($body);
        self::assertSame(['dateTime' => '2026-11-10T19:00:00+02:00', 'timeZone' => 'UTC'], $body['start']);
    }

    public function testAnEventIsDeletedAndOneAlreadyGoneIsNoError(): void
    {
        $recorder = new Recorder(new MockResponse('', ['http_code' => 204]), new MockResponse('', ['http_code' => 410]), new MockResponse('', ['http_code' => 404]));
        $google = new GoogleCalendar($recorder->client);

        for ($i = 0; $i < 3; ++$i) {
            $google->remove($this->connection(), Credentials::bearer('t'), 'booking-1');
        }

        self::assertSame(['DELETE', 'DELETE', 'DELETE'], array_column($recorder->requests, 'method'));
        foreach ($recorder->requests as $request) {
            self::assertSame('https://www.googleapis.com/calendar/v3/calendars/team%40group.calendar.google.com/events/'.hash('sha256', 'booking-1'), $request['url']);
        }
    }

    public function testRefusedCredentialsAndErrorsAreFailures(): void
    {
        foreach ([[401, 'refused the credentials (HTTP 401)'], [403, 'refused the credentials (HTTP 403)'], [500, 'answered HTTP 500'], [302, 'answered HTTP 302'], [199, 'answered HTTP 199']] as [$status, $message]) {
            try {
                new GoogleCalendar(new Recorder(new MockResponse('', ['http_code' => $status]))->client)->remove($this->connection(), Credentials::bearer('t'), 'u');
                self::fail('Removed.');
            } catch (ProviderFailed $providerFailed) {
                self::assertStringContainsString($message, $providerFailed->getMessage());
            }
        }

        self::assertTrue(true);
    }

    private function connection(): CalendarConnection
    {
        return new CalendarConnection('c1', 'google', 'team@group.calendar.google.com');
    }

    private function window(): SyncWindow
    {
        return new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z'));
    }

    /**
     * @param list<BusyPeriod> $periods
     *
     * @return list<array{string, string}>
     */
    private function times(array $periods): array
    {
        return array_map(static fn (BusyPeriod $period): array => [$period->start->format('Y-m-d\TH:i:s\Z'), $period->end->format('Y-m-d\TH:i:s\Z')], $periods);
    }
}
