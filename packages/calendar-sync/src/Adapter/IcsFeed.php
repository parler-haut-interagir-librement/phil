<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Adapter;

use Override;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Contract\BusySource;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\ICalendar\ICalendarReader;
use Phil\CalendarSync\SyncWindow;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The adapter `ics`: the busy periods of a calendar published as an iCalendar feed — the secret address Google,
 * Microsoft, Apple or Proton give, a `webcal://` address read as `https://` —, read by `phil/safe-http`, which refuses
 * private networks and bounds the read. It only reads: a feed is not written to.
 *
 * The connection's calendar is the address of the feed. It often is the feed's only secret: keep it as a credential.
 */
final readonly class IcsFeed implements BusySource
{
    public const string NAME = 'ics';

    private SafeHttpClient $client;

    public function __construct(?SafeHttpClient $client = null)
    {
        $this->client = $client ?? new SafeHttpClient(limits: new Limits(timeout: 10.0, maxDuration: 30.0, maxBytes: ICalendarReader::MAX_BYTES, userAgent: 'PhilCalendarSync/0.1'));
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function busy(CalendarConnection $connection, Credentials $credentials, SyncWindow $window): iterable
    {
        try {
            $response = $this->client->get($this->address($connection->calendar), new ReadRule('text/calendar, text/plain;q=0.5, */*;q=0.1'));
        } catch (SafeHttpException) {
            throw ProviderFailed::unreachable(self::NAME);
        }

        if (401 === $response->status || 403 === $response->status) {
            throw ProviderFailed::unauthorized(self::NAME, $response->status);
        }

        if (!$response->isSuccessful()) {
            throw ProviderFailed::status(self::NAME, $response->status);
        }

        if (!$response->complete) {
            throw InvalidCalendarData::because(\sprintf('A calendar is %d bytes at most.', ICalendarReader::MAX_BYTES));
        }

        return ICalendarReader::busy($response->body, $window, $connection->timezone);
    }

    /**
     * The address of the feed, `webcal://` read as `https://`.
     */
    private function address(string $calendar): Url
    {
        $calendar = preg_replace('#^webcals?://#i', 'https://', trim($calendar)) ?? '';

        try {
            return Url::fromString($calendar);
        } catch (InvalidUrl) {
            throw InvalidSync::because('The calendar of an "ics" connection is the http or https address of a feed.');
        }
    }
}
