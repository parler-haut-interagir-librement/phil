<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Unit\Source;

use Phil\CalendarImport\Exception\SourceUnavailable;
use Phil\CalendarImport\ICalendar\ICalendarReader;
use Phil\CalendarImport\ICalendar\ReaderLimits;
use Phil\CalendarImport\Source\CalendarSource;
use Phil\CalendarImport\Source\FailureKind;
use Phil\CalendarImport\Source\Fetched;
use Phil\CalendarImport\Source\FetchValidators;
use Phil\CalendarImport\Source\IcsUrlSource;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(IcsUrlSource::class)]
#[CoversClass(Fetched::class)]
#[CoversClass(SourceUnavailable::class)]
final class IcsUrlSourceTest extends TestCase
{
    /** Public addresses of the documentation range, pinned so that no test resolves a name. */
    private const array RESOLVE = [
        'example.org' => '93.184.215.15',
        'mirror.example.org' => '93.184.215.16',
        'internal.example.org' => '10.0.0.7',
    ];

    private const string CALENDAR = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTART:20261212T100000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    /**
     * @param list<MockResponse>|callable(string, string, array<array-key, mixed>): MockResponse $responses
     */
    private function source(array|callable $responses, int $redirects = 0, int $bytes = 1_048_576): IcsUrlSource
    {
        return new IcsUrlSource(new SafeHttpClient(new MockHttpClient($responses), new Limits(maxBytes: $bytes, maxRedirects: $redirects), self::RESOLVE));
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @return list<string>
     */
    private static function headers(array $options): array
    {
        $headers = $options['headers'] ?? [];
        self::assertIsArray($headers);

        return array_values(array_filter($headers, \is_string(...)));
    }

    public function testAFailureNamesThePrivateFeedWithoutItsQuery(): void
    {
        $sources = [
            [new MockResponse('gone', ['http_code' => 404])],
            [new MockResponse(self::CALENDAR.str_repeat("X-PAD:x\r\n", 200))],
            [new MockResponse('<html></html>')],
            [new MockResponse('', ['error' => 'Connection refused'])],
        ];
        $messages = [];

        foreach ($sources as $responses) {
            try {
                $this->source($responses, bytes: 1024)->fetch($this->calendar('https://example.org:8443/feed.ics?token=s3cr3t#x'));
                self::fail('The read fails.');
            } catch (SourceUnavailable $sourceUnavailable) {
                $messages[] = $sourceUnavailable->getMessage();
                self::assertStringNotContainsString('s3cr3t', $sourceUnavailable->getMessage());
            }
        }

        self::assertSame([
            '"https://example.org:8443/feed.ics?…" answered with the status 404.',
            '"https://example.org:8443/feed.ics?…" answered with more bytes than allowed.',
            '"https://example.org:8443/feed.ics?…" is not a calendar the reader accepts: A calendar starts with BEGIN:VCALENDAR.',
            '"https://example.org:8443/feed.ics?…" could not be read: Connection refused',
        ], $messages);
    }

    public function testAFailureNamesAFeedWithAFragmentAloneWithoutIt(): void
    {
        try {
            $this->source([new MockResponse('gone', ['http_code' => 404])])->fetch($this->calendar('https://example.org/feed.ics#s3cr3t'));
            self::fail('The read fails.');
        } catch (SourceUnavailable $sourceUnavailable) {
            self::assertSame('"https://example.org/feed.ics?…" answered with the status 404.', $sourceUnavailable->getMessage());
        }
    }

    private function calendar(string $address = 'https://example.org/events.ics'): CalendarSource
    {
        return CalendarSource::of('club', $address, 'Asia/Tokyo');
    }

    public function testAFeedIsReadWithItsValidatorsAndInTheZoneOfTheSource(): void
    {
        $requests = [];
        $source = $this->source(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, self::headers($options)];

            return new MockResponse(self::CALENDAR, ['response_headers' => ['Content-Type' => 'text/calendar; charset=utf-8', 'ETag' => '"v2"', 'Last-Modified' => 'Sat, 21 Nov 2026 10:00:00 GMT']]);
        });

        $fetched = $source->fetch($this->calendar('webcal://example.org/events.ics'));

        self::assertFalse($fetched->isNotModified());
        self::assertSame('2026-12-12T10:00:00+09:00', $fetched->result?->events[0]->start->format(\DATE_ATOM));
        self::assertSame('"v2"', $fetched->validators->etag);
        self::assertSame('Sat, 21 Nov 2026 10:00:00 GMT', $fetched->validators->lastModified);
        self::assertCount(1, $requests);
        self::assertSame(['GET', 'https://example.org/events.ics'], [$requests[0][0], $requests[0][1]]);
        self::assertContains('Accept: '.IcsUrlSource::ACCEPT, $requests[0][2]);
        self::assertSame([], array_values(array_filter($requests[0][2], static fn (string $header): bool => str_starts_with($header, 'If-'))));
    }

    public function testAConditionalReadSendsTheValidatorsAndANotModifiedChangesNothing(): void
    {
        $headers = [];
        $source = $this->source(static function (string $method, string $url, array $options) use (&$headers): MockResponse {
            $headers = self::headers($options);

            return new MockResponse('', ['http_code' => 304]);
        });
        $validators = new FetchValidators('"v1"', 'Fri, 20 Nov 2026 10:00:00 GMT');

        $fetched = $source->fetch($this->calendar(), $validators);

        self::assertTrue($fetched->isNotModified());
        self::assertNull($fetched->result);
        self::assertSame($validators, $fetched->validators);
        self::assertContains('If-None-Match: "v1"', $headers);
        self::assertContains('If-Modified-Since: Fri, 20 Nov 2026 10:00:00 GMT', $headers);
    }

    public function testANotModifiedWithNewValidatorsKeepsThem(): void
    {
        $fetched = $this->source([new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"v3"']])])->fetch($this->calendar(), new FetchValidators('"v1"'));

        self::assertSame('"v3"', $fetched->validators->etag);
        self::assertNull($fetched->validators->lastModified);
    }

    /**
     * @return iterable<string, array{list<MockResponse>, FailureKind, string, ?int}>
     */
    public static function failures(): iterable
    {
        yield 'not found' => [[new MockResponse('gone', ['http_code' => 404])], FailureKind::Status, '"https://example.org/events.ics" answered with the status 404.', 404];
        yield 'server error' => [[new MockResponse('', ['http_code' => 500])], FailureKind::Status, '"https://example.org/events.ics" answered with the status 500.', 500];
        yield 'a redirect not wanted' => [[new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://mirror.example.org/events.ics']])], FailureKind::Refused, 'redirect', null];
        yield 'too large for the client' => [[new MockResponse(self::CALENDAR.str_repeat("X-PAD:x\r\n", 200))], FailureKind::TooLarge, '"https://example.org/events.ics" answered with more bytes than allowed.', null];
        yield 'not a calendar' => [[new MockResponse('<html></html>')], FailureKind::Invalid, '"https://example.org/events.ics" is not a calendar the reader accepts: A calendar starts with BEGIN:VCALENDAR.', null];
        yield 'a transport error' => [[new MockResponse('', ['error' => 'connection reset'])], FailureKind::Unreachable, 'connection reset', null];
    }

    /**
     * @param list<MockResponse> $responses
     */
    #[DataProvider('failures')]
    public function testASourceThatGivesNoCalendarSaysWhy(array $responses, FailureKind $kind, string $message, ?int $status): void
    {
        try {
            $this->source($responses, bytes: 1024)->fetch($this->calendar());
            self::fail('The source gives no calendar.');
        } catch (SourceUnavailable $sourceUnavailable) {
            self::assertSame($kind, $sourceUnavailable->kind);
            self::assertStringContainsString($message, $sourceUnavailable->getMessage());
            self::assertSame($status, $sourceUnavailable->status);
        }
    }

    public function testAPrivateNetworkIsRefused(): void
    {
        try {
            $this->source([new MockResponse(self::CALENDAR)])->fetch($this->calendar('https://internal.example.org/events.ics'));
            self::fail('A private network is refused.');
        } catch (SourceUnavailable $sourceUnavailable) {
            self::assertSame(FailureKind::Refused, $sourceUnavailable->kind);
            self::assertNotNull($sourceUnavailable->getPrevious());
        }
    }

    public function testARedirectToAPrivateNetworkIsRefusedEvenWhenRedirectsAreFollowed(): void
    {
        $this->expectExceptionObject(SourceUnavailable::of(FailureKind::Refused, '"https://internal.example.org/events.ics" is or resolves to a private, loopback, link-local or reserved network.'));

        $this->source([new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://internal.example.org/events.ics']]), new MockResponse(self::CALENDAR)], redirects: 1)->fetch($this->calendar());
    }

    public function testAWantedRedirectIsFollowed(): void
    {
        $fetched = $this->source([new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://mirror.example.org/events.ics']]), new MockResponse(self::CALENDAR)], redirects: 1)->fetch($this->calendar());

        self::assertCount(1, $fetched->result->events ?? []);
    }

    public function testACalendarPastTheBytesOfTheReaderIsTooLarge(): void
    {
        $source = new IcsUrlSource(new SafeHttpClient(new MockHttpClient([new MockResponse(self::CALENDAR)]), new Limits(), self::RESOLVE), new ICalendarReader(new ReaderLimits(bytes: 64)));

        try {
            $source->fetch($this->calendar());
            self::fail('The calendar is past the bytes of the reader.');
        } catch (SourceUnavailable $sourceUnavailable) {
            self::assertSame(FailureKind::TooLarge, $sourceUnavailable->kind);
        }
    }

    public function testACalendarPastAnotherBoundIsInvalid(): void
    {
        $source = new IcsUrlSource(new SafeHttpClient(new MockHttpClient([new MockResponse(str_replace('END:VCALENDAR', "BEGIN:VEVENT\r\nUID:b\r\nDTSTART:20261212T100000\r\nEND:VEVENT\r\nEND:VCALENDAR", self::CALENDAR))]), new Limits(), self::RESOLVE), new ICalendarReader(new ReaderLimits(events: 1)));

        try {
            $source->fetch($this->calendar());
            self::fail('The calendar is past a bound of the reader.');
        } catch (SourceUnavailable $sourceUnavailable) {
            self::assertSame(FailureKind::Invalid, $sourceUnavailable->kind);
            self::assertStringContainsString('events, 1 at most', $sourceUnavailable->getMessage());
        }
    }

    public function testASourceOnItsOwnClientReadsAsManyBytesAsItsReaderAndFollowsNoRedirect(): void
    {
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen[] = ['headers' => self::headers($options), 'timeout' => $options['timeout'] ?? null];

            return new MockResponse(self::CALENDAR, ['http_code' => 302, 'response_headers' => ['Location' => 'https://example.org/b.ics']]);
        });

        try {
            IcsUrlSource::create($client, new ReaderLimits(bytes: 2048))->fetch(CalendarSource::of('a', 'https://example.org/a.ics'));
            self::fail('No redirect is followed.');
        } catch (SourceUnavailable $sourceUnavailable) {
            self::assertSame(FailureKind::Refused, $sourceUnavailable->kind);
        }

        self::assertCount(1, $seen);
        self::assertContains('User-Agent: PhilCalendarImport/0.1', $seen[0]['headers']);
        self::assertSame(10.0, $seen[0]['timeout']);
    }

    public function testASourceOnItsOwnClientReadsAtLeastTheSmallestBodyOfTheClient(): void
    {
        $body = self::CALENDAR.str_repeat("X-PAD:x\r\n", 60);
        $fetched = IcsUrlSource::create(new MockHttpClient([new MockResponse($body)]), new ReaderLimits(bytes: 64), redirects: 2);

        try {
            $fetched->fetch(CalendarSource::of('a', 'https://example.org/a.ics'));
            self::fail('The body is past the bytes of the reader.');
        } catch (SourceUnavailable $sourceUnavailable) {
            self::assertSame(FailureKind::TooLarge, $sourceUnavailable->kind);
            self::assertStringContainsString('bytes in the text', $sourceUnavailable->getMessage());
        }
    }
}
