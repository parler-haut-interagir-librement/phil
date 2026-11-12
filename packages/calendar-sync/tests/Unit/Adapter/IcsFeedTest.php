<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\Adapter;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarSync\Adapter\IcsFeed;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\SyncWindow;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(IcsFeed::class)]
final class IcsFeedTest extends TestCase
{
    private const array RESOLVE = ['calendar.example.com' => '93.184.215.14'];

    public function testAFeedIsReadInTheZoneOfTheConnection(): void
    {
        $recorder = new Recorder(new MockResponse("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:20261110T190000\r\nDTEND:20261110T200000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n", ['response_headers' => ['Content-Type' => 'text/calendar']]));
        $feed = $this->feed($recorder);
        $periods = [...$feed->busy(new CalendarConnection('c', 'ics', 'webcal://calendar.example.com/private/abc/basic.ics', new DateTimeZone('America/Toronto')), Credentials::none(), $this->window())];

        self::assertSame('ics', $feed->name());
        self::assertSame(['2026-11-11T00:00:00Z'], array_map(static fn (BusyPeriod $period): string => $period->start->format('Y-m-d\TH:i:s\Z'), $periods));
        self::assertSame('GET', $recorder->requests[0]['method']);
        self::assertSame('https://calendar.example.com/private/abc/basic.ics', $recorder->requests[0]['url']);
        self::assertTrue($recorder->sent(0, 'Accept: text/calendar, text/plain;q=0.5, */*;q=0.1'));
    }

    public function testTheAddressOfAFeedIsTrimmed(): void
    {
        $recorder = new Recorder(new MockResponse("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n", ['response_headers' => ['Content-Type' => 'text/calendar']]));
        [...$this->feed($recorder)->busy(new CalendarConnection('c', 'ics', ' WEBCAL://calendar.example.com/a.ics '), Credentials::none(), $this->window())];

        self::assertSame('https://calendar.example.com/a.ics', $recorder->requests[0]['url']);
    }

    public function testErrorsAreFailures(): void
    {
        foreach ([[401, ProviderFailed::unauthorized('ics', 401)], [403, ProviderFailed::unauthorized('ics', 403)], [404, ProviderFailed::status('ics', 404)]] as [$status, $failure]) {
            try {
                [...$this->feed(new Recorder(new MockResponse('', ['http_code' => $status])))->busy($this->connection(), Credentials::none(), $this->window())];
                self::fail('Read.');
            } catch (ProviderFailed $providerFailed) {
                self::assertSame($failure->getMessage(), $providerFailed->getMessage());
            }
        }
    }

    public function testAFeedOnAPrivateNetworkIsRefused(): void
    {
        $feed = new IcsFeed(new SafeHttpClient(new Recorder()->client, new Limits(), ['calendar.example.com' => '127.0.0.1']));
        $this->expectExceptionObject(ProviderFailed::unreachable('ics'));

        [...$feed->busy($this->connection(), Credentials::none(), $this->window())];
    }

    public function testAFeedCutAtItsBytesIsRefused(): void
    {
        $feed = new IcsFeed(new SafeHttpClient(new Recorder(new MockResponse(str_repeat('a', 2048)))->client, new Limits(maxBytes: 1024), self::RESOLVE));
        $this->expectExceptionObject(InvalidCalendarData::because('A calendar is 10485760 bytes at most.'));

        [...$feed->busy($this->connection(), Credentials::none(), $this->window())];
    }

    public function testTheFeedIsAnAddress(): void
    {
        $this->expectExceptionObject(InvalidSync::because('The calendar of an "ics" connection is the http or https address of a feed.'));

        [...$this->feed(new Recorder())->busy(new CalendarConnection('c', 'ics', 'ftp://calendar.example.com/a.ics'), Credentials::none(), $this->window())];
    }

    public function testTheDefaultClientRefusesWhatIsNotPublic(): void
    {
        $this->expectExceptionObject(InvalidSync::because('The calendar of an "ics" connection is the http or https address of a feed.'));

        [...new IcsFeed()->busy(new CalendarConnection('c', 'ics', 'https://localhost/a.ics'), Credentials::none(), $this->window())];
    }

    private function feed(Recorder $recorder): IcsFeed
    {
        return new IcsFeed(new SafeHttpClient($recorder->client, new Limits(), self::RESOLVE));
    }

    private function connection(): CalendarConnection
    {
        return new CalendarConnection('c', 'ics', 'https://calendar.example.com/a.ics');
    }

    private function window(): SyncWindow
    {
        return new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z'));
    }
}
